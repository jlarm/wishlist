<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductMetadataScraper
{
    /**
     * Maximum number of candidate images returned to the client.
     */
    private const MAX_IMAGES = 8;

    /**
     * Maximum redirect hops to follow before giving up (each is SSRF-checked).
     */
    private const MAX_REDIRECTS = 4;

    /**
     * Fetch and parse the page metadata for the given product URL.
     *
     * Tries a direct request first (free, fast). When that comes back blocked
     * or without usable metadata, it falls back to the ScrapingBee proxy if an
     * API key is configured. Always returns a normalised shape; it never throws.
     *
     * @return array{title: ?string, description: ?string, price: ?string, images: list<string>}
     */
    public function fetch(string $url): array
    {
        $empty = ['title' => null, 'description' => null, 'price' => null, 'images' => []];

        if (! $this->isSafeUrl($url)) {
            return $empty;
        }

        $result = $this->parseOrEmpty($this->fetchDirect($url), $url);

        // Big retailers (Amazon, Walmart, etc.) block direct server requests,
        // so retry through the proxy only when the cheap path found nothing.
        if ($this->isEmpty($result) && $this->proxyKey() !== null) {
            $proxied = $this->parseOrEmpty($this->fetchViaProxy($url), $url);

            if (! $this->isEmpty($proxied)) {
                $result = $proxied;
            }
        }

        return $result;
    }

    /**
     * Parse the HTML, returning an empty payload when it is missing or the page
     * is a bot-detection challenge rather than the real product page.
     *
     * @return array{title: ?string, description: ?string, price: ?string, images: list<string>}
     */
    private function parseOrEmpty(?string $html, string $url): array
    {
        $empty = ['title' => null, 'description' => null, 'price' => null, 'images' => []];

        if ($html === null) {
            return $empty;
        }

        $result = $this->parse($html, $url);

        return $this->looksBlocked($result) ? $empty : $result;
    }

    /**
     * Detect bot-wall / captcha pages so their junk titles never leak through.
     *
     * @param  array{title: ?string, description: ?string, price: ?string, images: list<string>}  $result
     */
    private function looksBlocked(array $result): bool
    {
        $title = Str::lower((string) $result['title']);

        if ($title === '') {
            return false;
        }

        $signatures = [
            'robot or human',
            'are you a robot',
            'are you human',
            'captcha',
            'access denied',
            'access to this page has been denied',
            'attention required',
            'pardon our interruption',
            'just a moment',
            'verify you are a human',
            'human verification',
            'security check',
            'request blocked',
            'before you continue',
        ];

        return Str::contains($title, $signatures);
    }

    /**
     * Fetch the page HTML directly, returning null when blocked or non-HTML.
     *
     * Redirects are followed manually so every hop is re-checked against the
     * SSRF guard — Laravel's HTTP client would otherwise auto-follow a public
     * URL straight to an internal host (cloud metadata, localhost, etc.).
     */
    private function fetchDirect(string $url): ?string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (! $this->isSafeUrl($url)) {
                return null;
            }

            try {
                $response = Http::timeout(8)
                    ->connectTimeout(4)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.9',
                    ])
                    ->withOptions(['allow_redirects' => false])
                    ->get($url);
            } catch (\Throwable) {
                return null;
            }

            if ($response->redirect()) {
                $location = $this->toAbsoluteUrl((string) $response->header('Location'), $url);

                if ($location === null) {
                    return null;
                }

                $url = $location;

                continue;
            }

            if (! $response->successful() || ! Str::contains($response->header('Content-Type'), 'html')) {
                return null;
            }

            return $response->body();
        }

        return null;
    }

    /**
     * Fetch the page HTML through ScrapingBee's proxy, returning null on failure.
     */
    private function fetchViaProxy(string $url): ?string
    {
        try {
            $response = Http::timeout(40)->get('https://app.scrapingbee.com/api/v1/', [
                'api_key' => $this->proxyKey(),
                'url' => $url,
                'render_js' => config('services.scrapingbee.render_js') ? 'true' : 'false',
                'premium_proxy' => config('services.scrapingbee.premium_proxy') ? 'true' : 'false',
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() ? $response->body() : null;
    }

    /**
     * The configured ScrapingBee API key, or null when the fallback is disabled.
     */
    private function proxyKey(): ?string
    {
        $key = config('services.scrapingbee.key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Whether a parsed result carries no usable metadata.
     *
     * @param  array{title: ?string, description: ?string, price: ?string, images: list<string>}  $result
     */
    private function isEmpty(array $result): bool
    {
        return $result['title'] === null && $result['images'] === [];
    }

    /**
     * Parse the HTML body into normalised metadata.
     *
     * @return array{title: ?string, description: ?string, price: ?string, images: list<string>}
     */
    private function parse(string $html, string $baseUrl): array
    {
        $document = new DOMDocument;

        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();

        $meta = $this->collectMetaTags($document);

        $title = $meta['og:title']
            ?? $meta['twitter:title']
            ?? $this->firstTagContent($document, 'title');

        $description = $meta['og:description']
            ?? $meta['twitter:description']
            ?? $meta['description']
            ?? null;

        $price = $meta['og:price:amount']
            ?? $meta['product:price:amount']
            ?? $meta['twitter:data1']
            ?? null;

        return [
            'title' => $this->clean($title),
            'description' => $this->clean($description),
            'price' => $this->cleanPrice($price),
            'images' => $this->collectImages($document, $meta, $baseUrl),
        ];
    }

    /**
     * Get the trimmed text content of the first element with the given tag.
     */
    private function firstTagContent(DOMDocument $document, string $tag): ?string
    {
        $element = $document->getElementsByTagName($tag)->item(0);

        return $element?->textContent;
    }

    /**
     * Build a map of meta tag names/properties to their content values.
     *
     * @return array<string, string>
     */
    private function collectMetaTags(DOMDocument $document): array
    {
        $tags = [];

        foreach ($document->getElementsByTagName('meta') as $meta) {
            /** @var DOMElement $meta */
            $key = $meta->getAttribute('property') ?: $meta->getAttribute('name') ?: $meta->getAttribute('itemprop');
            $content = $meta->getAttribute('content');

            if ($key !== '' && $content !== '' && ! isset($tags[strtolower($key)])) {
                $tags[strtolower($key)] = $content;
            }
        }

        return $tags;
    }

    /**
     * Gather candidate image URLs, preferring social/meta images over inline tags.
     *
     * @param  array<string, string>  $meta
     * @return list<string>
     */
    private function collectImages(DOMDocument $document, array $meta, string $baseUrl): array
    {
        $candidates = [];

        foreach (['og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src'] as $key) {
            if (isset($meta[$key])) {
                $candidates[] = $meta[$key];
            }
        }

        foreach ($document->getElementsByTagName('link') as $link) {
            /** @var DOMElement $link */
            if (Str::contains(strtolower($link->getAttribute('rel')), 'image_src')) {
                $candidates[] = $link->getAttribute('href');
            }
        }

        foreach ($document->getElementsByTagName('img') as $img) {
            /** @var DOMElement $img */
            $candidates[] = $img->getAttribute('src');
        }

        $images = [];

        foreach ($candidates as $candidate) {
            $absolute = $this->toAbsoluteUrl($candidate, $baseUrl);

            if ($absolute !== null && ! in_array($absolute, $images, true)) {
                $images[] = $absolute;
            }

            if (count($images) >= self::MAX_IMAGES) {
                break;
            }
        }

        return $images;
    }

    /**
     * Resolve a possibly-relative image reference to an absolute http(s) URL.
     */
    private function toAbsoluteUrl(string $value, string $baseUrl): ?string
    {
        $value = trim($value);

        if ($value === '' || Str::startsWith($value, 'data:')) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $base = parse_url($baseUrl);

        if ($base === false || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        $origin = "{$base['scheme']}://{$base['host']}".(isset($base['port']) ? ":{$base['port']}" : '');

        if (Str::startsWith($value, '//')) {
            return "{$base['scheme']}:{$value}";
        }

        if (Str::startsWith($value, '/')) {
            return $origin.$value;
        }

        $path = isset($base['path']) ? Str::beforeLast($base['path'], '/') : '';

        return "{$origin}{$path}/{$value}";
    }

    /**
     * Guard against SSRF by allowing only public http(s) hosts.
     *
     * Resolves both IPv4 (A) and IPv6 (AAAA) records and rejects the URL if any
     * resolved address is private or reserved. A literal IP host is checked
     * directly. Note: this does not fully close DNS-rebinding (the IP is
     * re-resolved at connect time), which is an acceptable residual risk for an
     * authenticated, invite-only app.
     */
    private function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = trim($parts['host'], '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIp($host);
        }

        $ips = $this->resolveHost($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a hostname to its A and AAAA addresses.
     *
     * @return list<string>
     */
    private function resolveHost(string $host): array
    {
        $ips = [];

        $a = @gethostbynamel($host);

        if (is_array($a)) {
            $ips = $a;
        }

        $aaaa = @dns_get_record($host, DNS_AAAA);

        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return $ips;
    }

    /**
     * Whether an IP address is a routable public address (not private/reserved).
     */
    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Trim and decode entities from a metadata string.
     */
    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));

        return $value === '' ? null : $value;
    }

    /**
     * Reduce a price string to a bare numeric value the form input accepts.
     */
    private function cleanPrice(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        preg_match('/\d+(?:[.,]\d+)?/', $value, $matches);

        return isset($matches[0]) ? str_replace(',', '.', $matches[0]) : null;
    }
}

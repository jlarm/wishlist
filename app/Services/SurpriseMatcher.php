<?php

namespace App\Services;

use App\Models\WishlistItem;
use Illuminate\Support\Collection;

class SurpriseMatcher
{
    /**
     * Query parameters that only track where a click came from, so two links
     * to the same product still match when they differ only in these.
     *
     * @var list<string>
     */
    private const TRACKING_PARAMETERS = [
        'ref', 'ref_', 'tag', 'th', 'psc', 'linkCode', 'fbclid', 'gclid', 'msclkid', 'srsltid', 'mc_cid', 'mc_eid', 'campaign', 'source',
    ];

    /**
     * The shortest normalized title that can match by being contained in
     * another, so short words like "lego" don't flag every set.
     */
    private const MIN_CONTAINED_TITLE_LENGTH = 10;

    /**
     * How alike (in percent) two normalized titles must be to count as one.
     */
    private const TITLE_SIMILARITY_THRESHOLD = 85;

    /**
     * Pair each of the owner's own wishes with a surprise on the same list
     * that looks like the same thing, available as the "surpriseMatch"
     * relation (null when there is none).
     *
     * IMPORTANT: Only call this for admins — it puts surprises on the wishes.
     *
     * @param  Collection<int, WishlistItem>  $items
     */
    public function attachMatches(Collection $items): void
    {
        $surprises = $items->filter(fn (WishlistItem $item): bool => $item->isSurprise());

        $items
            ->reject(fn (WishlistItem $item): bool => $item->isSurprise())
            ->each(fn (WishlistItem $wish) => $wish->setRelation(
                'surpriseMatch',
                $surprises->first(fn (WishlistItem $surprise): bool => $this->isMatch($wish, $surprise)),
            ));
    }

    /**
     * Whether two items look like the same product: the same link once
     * tracking noise is stripped, or near-identical titles.
     */
    public function isMatch(WishlistItem $first, WishlistItem $second): bool
    {
        $firstUrl = $this->normalizeUrl($first->url);

        if ($firstUrl !== null && $firstUrl === $this->normalizeUrl($second->url)) {
            return true;
        }

        return $this->titlesMatch($this->normalizeTitle($first->title), $this->normalizeTitle($second->title));
    }

    /**
     * Reduce a product link to what identifies the product: host without
     * "www.", path without a trailing slash, and only meaningful query
     * parameters, sorted. Amazon links collapse to their product code.
     */
    private function normalizeUrl(?string $url): ?string
    {
        $parts = $url === null ? false : parse_url(trim($url));

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $host = preg_replace('/^www\./', '', mb_strtolower($parts['host']));
        $path = rtrim($parts['path'] ?? '', '/');

        if (str_contains($host, 'amazon.') && preg_match('~/(?:dp|gp/product)/([A-Z0-9]{10})~i', $path, $match)) {
            return 'amazon/'.mb_strtoupper($match[1]);
        }

        parse_str($parts['query'] ?? '', $query);

        $query = array_filter(
            $query,
            fn (string|int $key): bool => ! str_starts_with((string) $key, 'utm_')
                && ! in_array((string) $key, self::TRACKING_PARAMETERS, true),
            ARRAY_FILTER_USE_KEY,
        );
        ksort($query);

        return $host.$path.($query === [] ? '' : '?'.http_build_query($query));
    }

    /**
     * Lowercase a title down to its words, ignoring punctuation and spacing.
     */
    private function normalizeTitle(string $title): string
    {
        $words = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($title));

        return trim((string) $words);
    }

    /**
     * Whether two normalized titles name the same thing: equal, one wholly
     * containing the other (e.g. "lego castle" and "lego castle set"), or
     * nearly the same.
     */
    private function titlesMatch(string $first, string $second): bool
    {
        if ($first === '' || $second === '') {
            return false;
        }

        if ($first === $second) {
            return true;
        }

        $shorter = mb_strlen($first) <= mb_strlen($second) ? $first : $second;
        $longer = $shorter === $first ? $second : $first;

        if (mb_strlen($shorter) >= self::MIN_CONTAINED_TITLE_LENGTH
            && preg_match('/\b'.preg_quote($shorter, '/').'\b/u', $longer) === 1) {
            return true;
        }

        similar_text($first, $second, $percent);

        return $percent >= self::TITLE_SIMILARITY_THRESHOLD;
    }
}

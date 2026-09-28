/**
 * Product details the bookmarklet reads off the store page the user is on.
 */
export type SharedProduct = {
    title: string | null;
    description: string | null;
    price: string | null;
    images: string[];
};

/**
 * Runs on the store's page (not ours) when the bookmarklet is clicked. It
 * reads the product details straight from the page the browser has already
 * loaded, so stores that block our server (Amazon, Walmart…) still work, then
 * opens the add form with the details in the URL fragment.
 *
 * It is serialised with toString(), so it must stay self-contained: no
 * imports or references to anything outside the function body.
 */
function scrapeProductPage(target: string): void {
    const meta = (...names: string[]): string | null => {
        for (const name of names) {
            const content = document
                .querySelector(
                    `meta[property="${name}"],meta[name="${name}"],meta[itemprop="${name}"]`,
                )
                ?.getAttribute('content')
                ?.trim();

            if (content) {
                return content;
            }
        }

        return null;
    };

    const textOf = (selector: string): string | null =>
        document.querySelector(selector)?.textContent?.trim() || null;

    // Structured data may hold the product at the top level, in an array or
    // inside an @graph, so walk it all looking for the first Product.
    let product: Record<string, any> | null = null;

    const visit = (node: unknown): void => {
        if (product || !node || typeof node !== 'object') {
            return;
        }

        if (Array.isArray(node)) {
            node.forEach(visit);

            return;
        }

        const record = node as Record<string, any>;
        const types = [record['@type']].flat().map((type) => String(type));

        if (types.includes('Product') || types.includes('ProductGroup')) {
            product = record;

            return;
        }

        visit(record['@graph']);
    };

    document
        .querySelectorAll('script[type="application/ld+json"]')
        .forEach((script) => {
            try {
                visit(JSON.parse(script.textContent ?? ''));
            } catch {
                // Broken JSON-LD is common; the meta tags are the fallback.
            }
        });

    const found = product as Record<string, any> | null;

    const decode = (value: unknown): string | null => {
        if (typeof value !== 'string' || !value.trim()) {
            return null;
        }

        try {
            return (
                new DOMParser()
                    .parseFromString(value, 'text/html')
                    .documentElement.textContent?.trim() || null
            );
        } catch {
            return value.trim();
        }
    };

    // "$1,299.99", "1.299,99 €", "12,99" → "1299.99", "1299.99", "12.99".
    const toPrice = (value: unknown): string | null => {
        const match = String(value ?? '')
            .replace(/\s/g, '')
            .match(/\d[\d.,]*\d|\d/);

        if (!match) {
            return null;
        }

        const digits = match[0];
        const separator = Math.max(
            digits.lastIndexOf('.'),
            digits.lastIndexOf(','),
        );
        const hasCents = separator >= 0 && digits.length - separator - 1 <= 2;
        const whole = (hasCents ? digits.slice(0, separator) : digits).replace(
            /[.,]/g,
            '',
        );

        return hasCents ? `${whole}.${digits.slice(separator + 1)}` : whole;
    };

    const variant = [found?.hasVariant].flat()[0];
    const offer = [found?.offers ?? variant?.offers].flat()[0];
    const priceElement = document.querySelector('[itemprop="price"]');

    const price = toPrice(
        offer?.price ??
            offer?.lowPrice ??
            offer?.priceSpecification?.price ??
            meta('og:price:amount', 'product:price:amount') ??
            priceElement?.getAttribute('content') ??
            priceElement?.textContent ??
            textOf(
                '#corePrice_feature_div .a-offscreen, .a-price .a-offscreen',
            ),
    );

    const title =
        decode(found?.name) ??
        decode(meta('og:title', 'twitter:title')) ??
        textOf('#productTitle') ??
        (document.title.trim() || null);

    const description =
        decode(found?.description) ??
        decode(meta('og:description', 'twitter:description', 'description'));

    // Declared product images first, then the biggest pictures on screen.
    const candidates: unknown[] = [
        ...[found?.image ?? variant?.image].flat(),
        meta('og:image:secure_url', 'og:image', 'twitter:image'),
        document.querySelector('link[rel="image_src"]')?.getAttribute('href'),
        document.querySelector('#landingImage')?.getAttribute('data-old-hires'),
        ...Array.from(document.images)
            .filter((image) => image.width >= 150 && image.height >= 150)
            .sort((a, b) => b.width * b.height - a.width * a.height)
            .map((image) => image.currentSrc || image.src),
    ];

    const images: string[] = [];

    for (const candidate of candidates) {
        const source =
            candidate && typeof candidate === 'object'
                ? (candidate as Record<string, unknown>).url
                : candidate;

        if (typeof source !== 'string' || !source) {
            continue;
        }

        try {
            const absolute = new URL(source, location.href).href;

            if (
                /^https?:/.test(absolute) &&
                absolute.length <= 2048 &&
                !images.includes(absolute)
            ) {
                images.push(absolute);
            }
        } catch {
            // Not a usable URL.
        }

        if (images.length >= 8) {
            break;
        }
    }

    const details = {
        title: title?.slice(0, 255) ?? null,
        description: description?.slice(0, 2000) ?? null,
        price,
        images,
    };

    // url/title also go in the query so the form still works when the
    // fragment is lost (e.g. a detour through the login page).
    const destination =
        `${target}?url=${encodeURIComponent(location.href)}` +
        `&title=${encodeURIComponent(details.title ?? '')}` +
        `#product=${encodeURIComponent(JSON.stringify(details))}`;

    // A new tab keeps the store page open; fall back if popups are blocked.
    if (!window.open(destination, '_blank')) {
        location.href = destination;
    }
}

/**
 * The javascript: link users drag to their bookmarks bar. The whole script
 * is percent-encoded because browsers decode javascript: URLs before running
 * them, which would otherwise mangle any "%" in the code.
 */
export function bookmarkletHref(target: string): string {
    const script = `(${scrapeProductPage.toString()})(${JSON.stringify(target)})`;

    return `javascript:${encodeURIComponent(script)}`;
}

/**
 * Read and sanitise the product details the bookmarklet put in the URL
 * fragment. The fragment is attacker-controllable, so every field is checked
 * and clamped to what the form accepts.
 */
export function readSharedProduct(hash: string): SharedProduct | null {
    const encoded = new URLSearchParams(hash.replace(/^#/, '')).get('product');

    if (!encoded) {
        return null;
    }

    let data: Record<string, unknown>;

    try {
        data = JSON.parse(encoded);
    } catch {
        return null;
    }

    if (!data || typeof data !== 'object') {
        return null;
    }

    const text = (value: unknown, max: number): string | null =>
        typeof value === 'string' && value.trim()
            ? value.trim().slice(0, max)
            : null;

    const price = text(data.price, 20);

    return {
        title: text(data.title, 255),
        description: text(data.description, 5000),
        price: price && /^\d+(\.\d{1,2})?$/.test(price) ? price : null,
        images: (Array.isArray(data.images) ? data.images : [])
            .filter(
                (image): image is string =>
                    typeof image === 'string' &&
                    /^https?:\/\//i.test(image) &&
                    image.length <= 2048,
            )
            .slice(0, 8),
    };
}

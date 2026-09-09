<?php

namespace App\Creeping\Fetching;

/**
 * What a digest keeps, for one kind of page.
 *
 * A shop page hides its truth in schema.org markup and a handful of meta
 * tags; a changelog puts it in plain prose under version headings. The two
 * want different parts of the same document, and — because every character
 * kept is a character paid for — different budgets.
 *
 * @see PageDigest::fromHtml()
 */
final readonly class DigestProfile
{
    /**
     * @param  array<int, string>  $noise  Elements to delete before reading the visible text.
     * @param  array<int, string>  $structuredTypes  schema.org types worth keeping, lowercased. Empty skips JSON-LD entirely.
     * @param  array<int, string>  $meta  `<meta>` names and properties worth keeping.
     * @param  array<int, string>  $itemProps  Microdata properties worth keeping.
     * @param  int  $structuredBudget  The most schema.org data to send, in characters.
     * @param  int  $metaBudget  The most metadata to send, in characters.
     */
    private function __construct(
        public array $noise,
        public array $structuredTypes,
        public array $meta,
        public array $itemProps,
        public int $structuredBudget,
        public int $metaBudget,
    ) {}

    /**
     * A single product page.
     *
     * Structured data first: a shop that publishes a Product node has told us
     * the price on purpose, and it is worth more than any amount of prose.
     */
    public static function product(): self
    {
        return new self(
            noise: [
                'script', 'style', 'noscript', 'svg', 'iframe', 'template',
                'nav', 'footer', 'header', 'aside', 'form', 'button', 'select',
            ],
            structuredTypes: ['product', 'offer', 'aggregateoffer', 'aggregaterating'],
            meta: [
                'og:title', 'og:description', 'og:image', 'og:url', 'og:site_name',
                'product:price:amount', 'product:price:currency', 'product:availability',
                'product:brand', 'product:retailer_item_id',
                'twitter:title', 'twitter:image', 'description',
            ],
            itemProps: [
                'name', 'price', 'priceCurrency', 'availability', 'sku', 'brand',
                'ratingValue', 'reviewCount', 'image',
            ],
            structuredBudget: 8000,
            metaBudget: 2000,
        );
    }

    /**
     * A release or changelog page.
     *
     * Nobody marks a changelog up in schema.org, so JSON-LD is skipped
     * outright and nearly the whole budget goes to the text — which is where
     * the versions, dates and feature descriptions actually are.
     *
     * `header` survives the cull here: a release entry very often wraps its
     * version and date in one, and losing those would leave a list of
     * features belonging to nothing.
     */
    public static function changelog(): self
    {
        return new self(
            noise: [
                'script', 'style', 'noscript', 'svg', 'iframe', 'template',
                'nav', 'footer', 'aside', 'form', 'button', 'select',
            ],
            structuredTypes: [],
            meta: ['og:title', 'og:description', 'og:url', 'og:site_name', 'description'],
            itemProps: [],
            structuredBudget: 0,
            metaBudget: 1000,
        );
    }
}

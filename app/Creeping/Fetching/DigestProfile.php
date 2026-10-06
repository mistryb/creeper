<?php

namespace App\Creeping\Fetching;

/**
 * What a digest keeps, for one kind of page.
 *
 * A watched page and a business's homepage want different parts of the same
 * document, and — because every character kept is a character paid for —
 * different budgets.
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
     * A competitor's page, read for whatever the user asked to watch.
     *
     * The page could be pricing, a changelog, a homepage or anything else, so
     * this keeps the schema.org types that describe what a company sells and
     * ships, and gives most of the budget to the visible text. `header`
     * survives the cull because a release or a plan often carries its name
     * and date in one.
     */
    public static function page(): self
    {
        return new self(
            noise: [
                'script', 'style', 'noscript', 'svg', 'iframe', 'template',
                'nav', 'footer', 'aside', 'form', 'select',
            ],
            structuredTypes: [
                'product', 'offer', 'aggregateoffer', 'aggregaterating', 'softwareapplication',
                'service', 'organization', 'webpage',
            ],
            meta: [
                'og:title', 'og:description', 'og:url', 'og:site_name', 'description',
                'product:price:amount', 'product:price:currency', 'product:availability',
                'twitter:title', 'twitter:description',
            ],
            itemProps: ['name', 'price', 'priceCurrency', 'availability', 'description'],
            structuredBudget: 4000,
            metaBudget: 1500,
        );
    }

    /**
     * A business's own website, read to understand what it sells and to whom.
     *
     * Organisation and product markup is kept because a site that publishes
     * it has described itself on purpose; the rest of the budget goes to the
     * visible text, where the pitch actually is.
     */
    public static function homepage(): self
    {
        return new self(
            noise: [
                'script', 'style', 'noscript', 'svg', 'iframe', 'template',
                'nav', 'footer', 'aside', 'form', 'button', 'select',
            ],
            structuredTypes: ['organization', 'localbusiness', 'corporation', 'product', 'offer', 'service', 'website'],
            meta: ['og:title', 'og:description', 'og:url', 'og:site_name', 'description', 'twitter:title', 'twitter:description'],
            itemProps: ['name', 'description', 'price', 'priceCurrency'],
            structuredBudget: 3000,
            metaBudget: 1000,
        );
    }
}

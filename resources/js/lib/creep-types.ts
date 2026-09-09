import type { CreepType } from '@/types';

/**
 * How each kind of creeping is talked about on screen.
 *
 * The backend owns which types exist and what they are called; every other
 * word a screen needs — the pitch on the new-target form, what the page it
 * watches is called, what counts as news — lives here, so the two creepers
 * read as two products rather than one product with a mode switch.
 */
export type CreepTypeCopy = {
    /** The pitch on the new-target form. */
    pitch: string;
    urlLabel: string;
    urlHint: string;
    urlPlaceholder: string;
    /** The heading over the last reading. */
    readingTitle: string;
    /** What Creeper will and won't call a change. */
    changeHint: string;
    /** Shown while the first reading is still outstanding. */
    waiting: string;
};

const copy: Record<CreepType, CreepTypeCopy> = {
    product: {
        pitch: 'Watch the price, the stock and the rating on one shop page.',
        urlLabel: 'Product URL',
        urlHint:
            'The page for a single product, not a search or category listing.',
        urlPlaceholder: 'https://example.com/products/kettle',
        readingTitle: 'The product',
        changeHint: 'Price moves and stock flips only — not review counts.',
        waiting:
            "Creeper hasn't managed to read this page yet. The first result usually lands within a minute.",
    },
    changelog: {
        pitch: "Watch a product's changelog and see the features it ships.",
        urlLabel: 'Changelog URL',
        urlHint:
            'The page a product publishes its releases on — "changelog", "releases", "what\'s new".',
        urlPlaceholder: 'https://example.com/changelog',
        readingTitle: 'The changelog',
        changeHint: 'A release appearing that was not on the page last time.',
        waiting:
            "Creeper hasn't managed to read this changelog yet. The first result usually lands within a minute.",
    },
};

export function creepTypeCopy(type: CreepType): CreepTypeCopy {
    return copy[type] ?? copy.product;
}

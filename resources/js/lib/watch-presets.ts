/**
 * Starting points for "what should Creeper watch for?". Picking one fills the
 * box; the words are the user's to change. All screen copy for the presets
 * lives here.
 */
import type { PageCategory } from '@/types';

export type WatchPreset = {
    label: string;
    watchFor: string;
    /** What kind of page it is, for slicing a competitor's activity. */
    category: PageCategory;
    /** An example URL for the kind of page the preset expects. */
    urlPlaceholder: string;
};

export const WATCH_PRESETS: WatchPreset[] = [
    {
        label: 'Changelog',
        category: 'releases',
        watchFor:
            'New releases: the version number, the date, and the headline features or fixes in each one.',
        urlPlaceholder: 'https://example.com/changelog',
    },
    {
        label: 'Pricing page',
        category: 'pricing',
        watchFor:
            "Each plan's name and price, what's included in it, and any plan that is added or removed.",
        urlPlaceholder: 'https://example.com/pricing',
    },
    {
        label: 'Home page',
        category: 'messaging',
        watchFor:
            'The main headline and tagline, who they say the product is for, and any new product or feature they announce.',
        urlPlaceholder: 'https://example.com',
    },
];

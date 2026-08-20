import { formatDistanceToNow } from 'date-fns';

/**
 * Prices are stored in minor units. Formatting here rather than on the server
 * means the currency renders in the reader's locale, not the server's.
 */
export function formatPrice(
    amount: number | null | undefined,
    currency: string | null | undefined,
): string {
    if (amount === null || amount === undefined) {
        return '—';
    }

    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: currency ?? 'USD',
        }).format(amount / 100);
    } catch {
        // An unknown currency code shouldn't blank out the price.
        return (amount / 100).toFixed(2);
    }
}

/** The difference between two prices, already formatted and signed. */
export function formatPriceDelta(
    from: number | null | undefined,
    to: number | null | undefined,
    currency: string | null | undefined,
): string | null {
    if (
        from === null ||
        from === undefined ||
        to === null ||
        to === undefined
    ) {
        return null;
    }

    const delta = to - from;

    if (delta === 0) {
        return null;
    }

    return `${delta > 0 ? '+' : '−'}${formatPrice(Math.abs(delta), currency)}`;
}

export function formatRelative(date: string | null | undefined): string {
    if (!date) {
        return 'never';
    }

    return formatDistanceToNow(new Date(date), { addSuffix: true });
}

export function formatDateTime(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(date));
}

export function formatDuration(milliseconds: number | null): string {
    if (milliseconds === null) {
        return '—';
    }

    if (milliseconds < 1000) {
        return `${milliseconds}ms`;
    }

    return `${(milliseconds / 1000).toFixed(1)}s`;
}

/** The hostname, for showing a URL without its query-string noise. */
export function hostOf(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

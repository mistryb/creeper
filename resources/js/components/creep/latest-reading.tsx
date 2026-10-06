import type { WatchedPage } from '@/types';

/**
 * The one line of a page's latest reading worth scanning in a list: its first
 * fact, and how many more there are.
 *
 * Sized to sit in a table cell or a sidebar row, so every list of watched
 * pages reports the same thing the same way.
 */
export function LatestReading({ watchedPage }: { watchedPage: WatchedPage }) {
    const snapshot = watchedPage.latest_snapshot;
    const [first, ...rest] = snapshot?.facts ?? [];

    if (!first) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0.5">
            <span className="truncate text-sm">
                <span className="text-muted-foreground">{first.label}:</span>{' '}
                <span className="font-mono font-medium">{first.value}</span>
            </span>
            {rest.length > 0 && (
                <span className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground tabular-nums">
                    +{rest.length} more
                </span>
            )}
        </div>
    );
}

import { AvailabilityBadge } from '@/components/creep/status-badges';
import { formatPrice } from '@/lib/format';
import type { CreepTarget } from '@/types';

/**
 * The one reading a list of targets is worth scanning, which is a different
 * reading per type: what the thing costs, or which version it is on.
 *
 * Sized to sit in a table cell or a sidebar row, so both lists of targets
 * report the same thing the same way.
 */
export function LatestReading({ target }: { target: CreepTarget }) {
    if (target.type === 'changelog') {
        const snapshot = target.latest_changelog_snapshot;

        if (!snapshot) {
            return <span className="text-muted-foreground">—</span>;
        }

        return (
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-mono text-sm font-medium">
                    {snapshot.latest_version ?? '—'}
                </span>
                <span className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground tabular-nums">
                    {snapshot.feature_count.toLocaleString()} listed
                </span>
            </div>
        );
    }

    const snapshot = target.latest_snapshot;

    return (
        <div className="flex flex-wrap items-center gap-2">
            {snapshot && (
                <AvailabilityBadge
                    availability={snapshot.availability}
                    label={snapshot.availability_label}
                />
            )}
            <span className="font-mono text-sm font-medium tabular-nums">
                {formatPrice(snapshot?.price_amount, snapshot?.currency)}
            </span>
        </div>
    );
}

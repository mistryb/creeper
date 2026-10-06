import { cn } from '@/lib/utils';

/**
 * A count set against the largest in its column: the figure in mono, and a
 * thin ribbon bar behind it for scanning a list by eye. One hue only — it says
 * "how much", never "good" or "bad", so unlike a Meter it never turns amber.
 */
export function Tally({
    value,
    max,
    label,
    className,
}: {
    value: number;
    max: number;
    /** What the number counts, for a screen reader. */
    label?: string;
    className?: string;
}) {
    const percent = max > 0 ? Math.min(100, (value / max) * 100) : 0;

    return (
        <div
            className={cn('flex min-w-16 items-center gap-2', className)}
            aria-label={
                label ? `${value.toLocaleString()} ${label}` : undefined
            }
        >
            <span className="w-6 text-right font-mono text-sm tabular-nums">
                {value.toLocaleString()}
            </span>
            <span aria-hidden className="h-1.5 flex-1 bg-rule">
                {value > 0 && (
                    <span
                        className="block h-full bg-ribbon"
                        style={{ width: `${Math.max(percent, 4)}%` }}
                    />
                )}
            </span>
        </div>
    );
}

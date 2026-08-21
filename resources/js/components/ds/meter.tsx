import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * A usage bar drawn as a ruled gauge: square, outlined in ink, filled with the
 * ribbon. It turns amber once the allowance is nearly gone and red once it is
 * past — and the figures above it say the same thing in words, so the colour is
 * a reinforcement rather than the message.
 */
export function Meter({
    label,
    value,
    max,
    valueLabel,
    className,
}: {
    label: ReactNode;
    value: number;
    max: number;
    /** The figures, spelled out. Falls back to "value of max". */
    valueLabel?: ReactNode;
    className?: string;
}) {
    const ratio = max > 0 ? value / max : 0;
    const percent = Math.min(100, Math.max(0, ratio * 100));

    const fill =
        ratio >= 1
            ? 'bg-ribbon-red'
            : ratio >= 0.85
              ? 'bg-ribbon-amber'
              : 'bg-ribbon';

    return (
        <div className={cn('space-y-1.5', className)}>
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <span className="label-micro text-muted-foreground">
                    {label}
                </span>
                <span className="font-mono text-xs tabular-nums">
                    {valueLabel ?? (
                        <>
                            {value.toLocaleString()} of {max.toLocaleString()}
                        </>
                    )}
                </span>
            </div>

            <div
                className="h-2.5 overflow-hidden border border-ink bg-white"
                role="progressbar"
                aria-valuenow={value}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-label={typeof label === 'string' ? label : undefined}
            >
                <div
                    className={cn('h-full', fill)}
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

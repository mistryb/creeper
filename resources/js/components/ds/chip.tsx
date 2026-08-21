import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * A punched tag: a short mono token, on or off. Chips describe configuration —
 * the fields being watched, the schedules on offer — where a badge describes
 * state. The lit state is an ink-green outline on white, not a fill, so a row
 * of chips stays readable as a set.
 */
export function Chip({
    children,
    on = false,
    className,
}: {
    children: ReactNode;
    on?: boolean;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'border px-2 py-1 font-mono text-[0.6875rem] tracking-[0.08em] whitespace-nowrap',
                on
                    ? 'border-ribbon bg-white text-ribbon'
                    : 'border-rule bg-paper-lit text-ink-soft',
                className,
            )}
        >
            {children}
        </span>
    );
}

/** A run of chips, wrapping. */
export function ChipRow({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-wrap gap-1.5', className)}>
            {children}
        </div>
    );
}

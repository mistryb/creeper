import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * The emphatic container: a box framed in ink with a hard offset shadow, so it
 * sits on the paper like something printed and set down rather than something
 * floating. Use it for the one surface on a page that should be read first —
 * a hero, a chart, a receipt. Everything quieter is a `Card`.
 */
export function Panel({
    className,
    children,
    lifted = true,
}: {
    className?: string;
    children: ReactNode;
    /** The offset shadow. Off for a panel that sits inside another panel. */
    lifted?: boolean;
}) {
    return (
        <div
            className={cn(
                'border border-ink bg-card',
                lifted && 'shadow-stamp',
                className,
            )}
        >
            {children}
        </div>
    );
}

/**
 * A panel's title bar: the strip of green-bar paper across the top, with the
 * three-dot chrome on the left and an optional status on the right. It names
 * what the panel is showing in the same breath as a window title.
 */
export function PanelBar({
    title,
    meta,
    className,
}: {
    title: ReactNode;
    /** Right-aligned status, e.g. a count or a timestamp. */
    meta?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-3 border-b border-ink bg-greenbar px-3.5 py-2 label-micro text-ribbon',
                className,
            )}
        >
            <span className="flex gap-1.5" aria-hidden="true">
                <i className="size-2 rounded-full border border-ribbon bg-ribbon" />
                <i className="size-2 rounded-full border border-ribbon" />
                <i className="size-2 rounded-full border border-ribbon" />
            </span>
            <span>{title}</span>
            {meta && (
                <>
                    <span className="flex-1" />
                    <span className="truncate">{meta}</span>
                </>
            )}
        </div>
    );
}

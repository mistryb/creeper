import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * A blank form waiting to be filled in: dashed rule, the mark in the middle,
 * and a way forward. Every empty surface in the app uses this, so "nothing
 * here yet" always looks deliberate rather than broken.
 */
export function EmptyState({
    icon: Icon,
    title,
    children,
    actions,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    /** One or two sentences on what would fill this space. */
    children?: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center gap-4 border border-dashed border-rule bg-card px-6 py-14 text-center',
                className,
            )}
        >
            {Icon && (
                <Icon
                    aria-hidden
                    className="size-7 text-ribbon"
                    strokeWidth={1.5}
                />
            )}

            <div className="space-y-1.5">
                <p className="label-mono uppercase">{title}</p>
                {children && (
                    <p className="mx-auto max-w-sm text-sm text-muted-foreground">
                        {children}
                    </p>
                )}
            </div>

            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

/**
 * The same idea at list scale: one quiet line where a card's contents would
 * be. Too small an area to justify the dashed frame.
 */
export function EmptyLine({ children }: { children: ReactNode }) {
    return (
        <p className="py-6 text-center font-mono text-xs tracking-[0.08em] text-muted-foreground">
            {children}
        </p>
    );
}

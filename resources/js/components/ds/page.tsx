import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * The sheet a page is printed on. Thin on purpose: its whole job is to keep
 * every screen's margins and vertical rhythm identical, so a new page never
 * has to re-decide its padding.
 */
export function Page({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-6 px-4 py-6 sm:px-6 sm:py-8', className)}>
            {children}
        </div>
    );
}

import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * The sheet a page is printed on. The app layout already holds every page in
 * one narrow centred column, so this only keeps the vertical rhythm the same
 * from screen to screen.
 */
export function Page({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return <div className={cn('space-y-8 pb-6', className)}>{children}</div>;
}

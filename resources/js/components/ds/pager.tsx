import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * Previous and next under a paginated list, with the range it is showing
 * printed between them. Takes the `links` and `meta` a paginated resource
 * collection already carries; a missing link is a disabled key, not a hidden
 * one, so the pair never shifts about.
 */
export function Pager({
    links,
    meta,
    className,
}: Pick<Paginated<unknown>, 'links' | 'meta'> & { className?: string }) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label="Pages"
            className={cn('flex items-center justify-between gap-4', className)}
        >
            <PageKey href={links.prev} rel="prev">
                <ChevronLeft aria-hidden />
                Previous
            </PageKey>

            <p className="font-mono text-xs text-muted-foreground tabular-nums">
                {meta.from}–{meta.to} of {meta.total}
            </p>

            <PageKey href={links.next} rel="next">
                Next
                <ChevronRight aria-hidden />
            </PageKey>
        </nav>
    );
}

function PageKey({
    href,
    rel,
    children,
}: {
    href: string | null;
    rel: 'prev' | 'next';
    children: React.ReactNode;
}) {
    if (href === null) {
        return (
            <Button variant="outline" size="sm" disabled>
                {children}
            </Button>
        );
    }

    return (
        <Button asChild variant="outline" size="sm">
            <Link href={href} rel={rel} preserveScroll>
                {children}
            </Link>
        </Button>
    );
}

import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Page, SectionHeading } from '@/components/ds';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { overview } from '@/routes/admin';
import { index as businesses } from '@/routes/admin/businesses';
import { index as users } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'Overview', href: overview(), icon: null },
    { title: 'Users', href: users(), icon: null },
    { title: 'Companies', href: businesses(), icon: null },
];

/**
 * The admin panel, inside the same single column as the rest of the app: a
 * heading, a row of tabs, and the screen — the settings layout's shape, so
 * it reads as part of the product rather than a second one.
 */
export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <Page>
            <SectionHeading title="Admin" note="Everyone on this install" />

            <nav
                className="flex gap-5 border-b border-rule label-micro"
                aria-label="Admin"
            >
                {navItems.map((item, index) => {
                    // The overview's URL is the parent of the others, so it is
                    // only current on itself.
                    const current =
                        index === 0
                            ? isCurrentUrl(item.href)
                            : isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={toUrl(item.href)}
                            href={item.href}
                            prefetch
                            aria-current={current ? 'page' : undefined}
                            className={cn(
                                '-mb-px border-b-2 pb-2.5 whitespace-nowrap transition-colors',
                                current
                                    ? 'border-ribbon text-ribbon'
                                    : 'border-transparent text-muted-foreground hover:text-ink',
                            )}
                        >
                            {item.title}
                        </Link>
                    );
                })}
            </nav>

            <section className="space-y-8">{children}</section>
        </Page>
    );
}

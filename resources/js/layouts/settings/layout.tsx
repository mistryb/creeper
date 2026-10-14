import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Page, SectionHeading } from '@/components/ds';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { index as apiKeys } from '@/routes/api-keys';
import { edit as editBilling } from '@/routes/billing';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'Profile', href: edit(), icon: null },
    { title: 'Security', href: editSecurity(), icon: null },
    { title: 'API keys', href: apiKeys(), icon: null },
];

/**
 * Settings, in the same single column as everything else: a heading, a row of
 * tabs, and the form.
 */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { billing } = usePage().props;

    // A self-hosted install has nothing to pay for, so no tab to pay with.
    const items: NavItem[] = billing
        ? [...navItems, { title: 'Billing', href: editBilling(), icon: null }]
        : navItems;

    return (
        <Page>
            <SectionHeading title="Settings" note="Your account, your keys" />

            <nav
                className="flex gap-5 border-b border-rule label-micro"
                aria-label="Settings"
            >
                {items.map((item, index) => {
                    const current = isCurrentOrParentUrl(item.href);

                    return (
                        <Link
                            key={`${toUrl(item.href)}-${index}`}
                            href={item.href}
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

            <section className="space-y-10">{children}</section>
        </Page>
    );
}

import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Page, SectionHeading } from '@/components/ds';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editApiKey } from '@/routes/api-key';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'Profile', href: edit(), icon: null },
    { title: 'Security', href: editSecurity(), icon: null },
    { title: 'API key', href: editApiKey(), icon: null },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <Page>
            <SectionHeading title="Settings" note="Your account, your key" />

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-12">
                {/*
                 * Filing tabs down the side: the current one is inked green and
                 * carries a rule on its leading edge, so the active row is
                 * marked by position as well as by colour.
                 */}
                <nav
                    className="flex shrink-0 overflow-x-auto border-b border-rule lg:w-44 lg:flex-col lg:border-b-0 lg:border-l lg:border-rule"
                    aria-label="Settings"
                >
                    {navItems.map((item, index) => {
                        const current = isCurrentOrParentUrl(item.href);

                        return (
                            <Link
                                key={`${toUrl(item.href)}-${index}`}
                                href={item.href}
                                aria-current={current ? 'page' : undefined}
                                className={cn(
                                    'border-b-2 px-3.5 py-2.5 label-micro whitespace-nowrap transition-colors lg:-ml-px lg:border-b-0 lg:border-l-2',
                                    current
                                        ? 'border-ribbon bg-greenbar/60 text-ribbon'
                                        : 'border-transparent text-muted-foreground hover:bg-greenbar/40 hover:text-foreground',
                                )}
                            >
                                {item.title}
                            </Link>
                        );
                    })}
                </nav>

                <div className="min-w-0 flex-1">
                    <section className="max-w-xl space-y-10">
                        {children}
                    </section>
                </div>
            </div>
        </Page>
    );
}

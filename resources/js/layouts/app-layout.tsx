import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { CompanyMenu } from '@/components/company-menu';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { navPill } from '@/lib/nav-pill';
import { cn } from '@/lib/utils';
import { dashboard, logout } from '@/routes';
import { index as competitors } from '@/routes/businesses/competitors';
import { index as digests } from '@/routes/businesses/digests';
import { edit as settings } from '@/routes/profile';
import type { RouteDefinition } from '@/wayfinder';

/**
 * The whole signed-in app sits in one narrow column in the middle of the
 * page: the wordmark, a single row of words to get around, the page, and a
 * quiet line at the bottom. There is no sidebar and no bar across the top —
 * the column is the only furniture.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, businessChooser } = usePage().props;
    const company = businessChooser.current;

    return (
        <div className="min-h-screen bg-paper font-sans text-ink">
            <div className="mx-auto flex min-h-screen w-full max-w-2xl flex-col px-4 sm:px-6">
                <header className="flex flex-col items-center gap-4 pt-8 pb-10 sm:pt-12">
                    <Link
                        href={dashboard()}
                        className="inline-flex items-center gap-2 label-micro text-[0.8125rem] font-semibold tracking-[0.22em] text-ink"
                    >
                        <AppLogoIcon className="size-4" />
                        Creeper
                    </Link>

                    {/*
                     * Which company everything below is about. Its own line,
                     * not a pill in the bar: it changes what the bar's links
                     * show rather than being somewhere to go.
                     */}
                    <div className="label-micro">
                        <CompanyMenu />
                    </div>

                    {/*
                     * A contained bar, not one across the page: it is exactly
                     * as wide as the column it heads. Its rounded corner is
                     * set explicitly because the radius scale is zeroed —
                     * like the pills, a deliberate exception (see JS rules).
                     */}
                    <nav
                        aria-label="Main"
                        className="flex w-full flex-wrap items-center justify-center gap-1 rounded-[1.25rem] border border-rule bg-card px-3 py-2.5 label-micro"
                    >
                        {company && (
                            <>
                                <NavLink href={digests(company.id)}>
                                    Digests
                                </NavLink>
                                <NavLink href={competitors(company.id)}>
                                    Competitors
                                </NavLink>
                            </>
                        )}
                        <NavLink href={settings()}>Settings</NavLink>
                    </nav>
                </header>

                <main className="flex-1">{children}</main>

                <footer className="flex flex-wrap items-center justify-center gap-x-3 py-10 label-micro text-muted-foreground">
                    <span>{auth.user.email}</span>
                    <span aria-hidden>·</span>
                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => router.flushAll()}
                        className="uppercase hover:text-ink"
                        data-test="logout-button"
                    >
                        Log out
                    </Link>
                </footer>
            </div>
        </div>
    );
}

function NavLink({
    href,
    children,
}: {
    href: RouteDefinition<'get'>;
    children: ReactNode;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const current = isCurrentOrParentUrl(href);

    return (
        <Link
            href={href}
            prefetch
            aria-current={current ? 'page' : undefined}
            className={cn(
                navPill,
                current
                    ? 'border-ink bg-ink text-paper-lit'
                    : 'border-rule text-muted-foreground hover:border-ink hover:text-ink',
            )}
        >
            {children}
        </Link>
    );
}

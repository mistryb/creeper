import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { navPill } from '@/lib/nav-pill';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

/**
 * The chrome the public pages share, headed the same way as the signed-in
 * app: the wordmark centred, then a contained, rounded bar of pills exactly as
 * wide as the landing page's column. Pages own everything below it, so a wider
 * page like the deploy walkthrough keeps its own margins.
 *
 * The chrome itself offers no way to sign in: the public pages are written for
 * someone who does not have an account yet, and each sends that visitor to the
 * call to action in its own copy. A page that wants a way back in passes one
 * through `nav` — the homepage does. Someone who is already signed in gets a
 * link back to the dashboard instead.
 */
export default function MarketingLayout({
    children,
    nav,
}: {
    children: ReactNode;
    /** Page-specific links, placed at the start of the header bar. */
    nav?: ReactNode;
}) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-screen bg-paper font-sans text-ink">
            <header className="mx-auto flex w-full max-w-3xl flex-col items-center gap-4 px-4 pt-8 sm:px-10 sm:pt-12">
                <Link
                    href="/"
                    className="inline-flex items-center gap-2 label-micro text-[0.8125rem] font-semibold tracking-[0.22em] text-ink"
                >
                    <AppLogoIcon className="size-4" />
                    Creeper
                </Link>

                {(nav || auth.user) && (
                    <nav
                        aria-label="Main"
                        className="flex w-full flex-wrap items-center justify-center gap-1 rounded-card border border-rule bg-card px-3 py-2.5 label-micro"
                    >
                        {nav}
                        {auth.user && (
                            <Link
                                href={dashboard()}
                                className={cn(
                                    navPill,
                                    'border-ink bg-ink text-paper-lit hover:bg-ink-lift',
                                )}
                            >
                                Dashboard
                            </Link>
                        )}
                    </nav>
                )}
            </header>

            <main>{children}</main>

            <footer className="flex flex-wrap items-center justify-center py-10 label-micro text-muted-foreground">
                <span>Creeper · a small tool for keeping tabs</span>
            </footer>
        </div>
    );
}

/** A pill in the header bar linking to a section of the current page. */
export function MarketingNavLink({
    href,
    children,
}: {
    href: string;
    children: ReactNode;
}) {
    return (
        <a
            href={href}
            className={cn(
                navPill,
                'border-rule text-muted-foreground hover:border-ink hover:text-ink',
            )}
        >
            {children}
        </a>
    );
}

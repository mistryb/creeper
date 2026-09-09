import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard } from '@/routes';

/**
 * The chrome the public pages share: an ink header rule with the wordmark and
 * the footer strip. Pages own everything between them, so the landing page can
 * run full-bleed sections while a narrower page like the deploy walkthrough
 * keeps the same margins.
 *
 * The public pages do not offer a way to sign in. They are written for someone
 * who does not have an account yet, and every page sends that visitor to the
 * same place — the sign-up call to action in its own copy — rather than
 * competing with it from the chrome. Someone who is already signed in gets a
 * link back to the dashboard instead.
 */
export default function MarketingLayout({
    children,
    nav,
}: {
    children: ReactNode;
    /** Page-specific links, placed at the end of the header nav. */
    nav?: ReactNode;
}) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-screen bg-paper font-sans text-ink">
            <header className="bg-ink text-paper-lit">
                <div className="mx-auto flex min-h-10 w-full max-w-5xl items-center justify-between gap-4 px-4 sm:px-10">
                    <Link
                        href="/"
                        className="inline-flex items-center gap-2 label-micro text-[0.8125rem] font-semibold tracking-[0.22em]"
                    >
                        <AppLogoIcon className="size-4" />
                        Creeper
                    </Link>

                    <nav className="flex items-center gap-3 label-micro sm:gap-7">
                        {nav}
                        {auth.user && (
                            <Link
                                href={dashboard()}
                                className="bg-paper-lit px-3 py-1.5 text-ink transition-colors hover:bg-white"
                            >
                                Dashboard
                            </Link>
                        )}
                    </nav>
                </div>
            </header>

            <main>{children}</main>

            <footer className="bg-ink text-paper-lit/55">
                <div className="mx-auto flex min-h-12 w-full max-w-5xl items-center justify-between gap-4 border-t border-paper-lit/20 px-4 label-micro sm:px-10">
                    <span>Creeper · a small tool for keeping tabs</span>
                </div>
            </footer>
        </div>
    );
}

/** A header link to a section of the current page. */
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
            className="hidden text-paper-lit/70 transition-colors hover:text-paper-lit sm:inline"
        >
            {children}
        </a>
    );
}

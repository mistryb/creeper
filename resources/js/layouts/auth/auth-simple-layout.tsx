import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Panel, PanelBar } from '@/components/ds';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * The way in. A single framed panel on paper, titled like the landing page's
 * hero, so signing in looks like the same product as the page that sold it.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-paper p-6 md:p-10">
            <div className="w-full max-w-sm">
                <Panel>
                    <PanelBar title="creeper" />

                    <div className="space-y-6 p-6 sm:p-8">
                        <div className="flex flex-col items-center gap-3 text-center">
                            <Link
                                href={home()}
                                className="text-ribbon transition-colors hover:text-ribbon-lit"
                            >
                                <AppLogoIcon className="size-7" />
                                <span className="sr-only">Creeper home</span>
                            </Link>

                            <div className="space-y-1.5">
                                <h1 className="display-dot text-xl">{title}</h1>
                                {description && (
                                    <p className="text-sm text-muted-foreground">
                                        {description}
                                    </p>
                                )}
                            </div>
                        </div>

                        {children}
                    </div>
                </Panel>
            </div>
        </div>
    );
}

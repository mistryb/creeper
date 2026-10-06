import { Link } from '@inertiajs/react';
import { CircleAlert, CircleCheck } from 'lucide-react';
import { formatRelative } from '@/lib/format';
import { index as businessAnalysis } from '@/routes/businesses/analysis';
import { show as showCompetitor } from '@/routes/competitors';
import { index as competitorAnalysis } from '@/routes/competitors/analysis';
import { create as watchPage } from '@/routes/competitors/watched-pages';
import { show as showPage } from '@/routes/watched-pages';
import type { DashboardGap } from '@/types';

/**
 * What is missing or broken, each with the way to fix it. The comparison is
 * only as good as what feeds it, and this is where that is said out loud.
 */
export function Gaps({ gaps }: { gaps: DashboardGap[] }) {
    if (gaps.length === 0) {
        return (
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <CircleCheck aria-hidden className="size-4 text-ribbon" />
                Nothing missing. Every competitor is watched and analysed, and
                every page is being read.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-rule">
            {gaps.map((gap) => {
                const { message, action, href } = describe(gap);

                return (
                    <li
                        key={`${gap.type}-${gap.business_id ?? gap.competitor_id ?? gap.watched_page_id}`}
                        className="flex flex-wrap items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                    >
                        <span className="flex min-w-0 items-start gap-2.5 text-sm">
                            <CircleAlert
                                aria-hidden
                                className="mt-0.5 size-4 shrink-0 text-ribbon-amber"
                            />
                            {message}
                        </span>
                        <Link
                            href={href}
                            className="label-micro text-ribbon underline decoration-rule underline-offset-4 hover:decoration-ribbon"
                        >
                            {action}
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}

function describe(gap: DashboardGap) {
    const since = gap.since ? formatRelative(gap.since) : '';

    switch (gap.type) {
        case 'business_unanalysed':
            return {
                message: `${gap.name} has never been analysed, so the comparison has only your description to go on.`,
                action: 'Analyse',
                href: businessAnalysis(gap.business_id!),
            };
        case 'business_stale':
            return {
                message: `${gap.name}'s own analysis was last run ${since}.`,
                action: 'Run again',
                href: businessAnalysis(gap.business_id!),
            };
        case 'competitor_unwatched':
            return {
                message: `No pages of ${gap.name}'s are being watched.`,
                action: 'Watch a page',
                href: watchPage(gap.competitor_id!),
            };
        case 'competitor_unanalysed':
            return {
                message: `${gap.name} has never been analysed.`,
                action: 'Analyse',
                href: competitorAnalysis(gap.competitor_id!),
            };
        case 'competitor_stale':
            return {
                message: `${gap.name} was last analysed ${since}.`,
                action: 'Run again',
                href: competitorAnalysis(gap.competitor_id!),
            };
        case 'page_parked':
            return {
                message: `${gap.name} kept failing, so Creeper stopped reading it.`,
                action: 'Fix',
                href: showPage(gap.watched_page_id!),
            };
        case 'page_keyless':
            return {
                message: `${gap.name} has no API key to read it with.`,
                action: 'Choose a key',
                href: showPage(gap.watched_page_id!),
            };
        default:
            return {
                message: gap.name,
                action: 'Open',
                href: showCompetitor(gap.competitor_id!),
            };
    }
}

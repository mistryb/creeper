import { Head, Link, usePoll } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
import { useEffect } from 'react';
import { ChangeList } from '@/components/creep/change-list';
import {
    ActivityFilters,
    ActivityTable,
} from '@/components/dashboard/activity';
import { ComparisonMatrix } from '@/components/dashboard/comparison-matrix';
import { Gaps } from '@/components/dashboard/gaps';
import { PositioningMap } from '@/components/dashboard/positioning-map';
import { WhereYouStand } from '@/components/dashboard/where-you-stand';
import { EmptyState, Page, SectionHeading, StatTile } from '@/components/ds';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { create as createBusiness } from '@/routes/businesses';
import { create as createCompetitor } from '@/routes/businesses/competitors';
import type {
    Business,
    CompetitorActivity,
    CreepChange,
    DashboardFilters,
    DashboardGap,
    LandscapeAnalysis,
    ResourceCollection,
    SelectOption,
} from '@/types';

type Props = {
    /** The business picked in the sidebar chooser; null before there is one. */
    business: { data: Business } | null;
    filters: DashboardFilters;
    categories: SelectOption[];
    competitors: { id: number; name: string }[];
    stats: {
        competitors: number;
        watchedPages: number;
        changes: number;
        parked: number;
    };
    landscape: { data: LandscapeAnalysis } | null;
    latestLandscapeRun: { data: LandscapeAnalysis } | null;
    isComparing: boolean;
    apiKeys: SelectOption[];
    activity: CompetitorActivity[];
    changes: ResourceCollection<CreepChange>;
    gaps: DashboardGap[];
};

export default function Dashboard(props: Props) {
    if (props.business === null) {
        return (
            <>
                <Head title="Dashboard" />
                <Page>
                    <SectionHeading title="Dashboard" />
                    <EmptyState
                        icon={Building2}
                        title="No business yet"
                        actions={
                            <Button asChild>
                                <Link href={createBusiness()}>
                                    <Plus aria-hidden />
                                    Set up your business
                                </Link>
                            </Button>
                        }
                    >
                        Describe your business first. Then add the competitors
                        you want Creeper to set you against.
                    </EmptyState>
                </Page>
            </>
        );
    }

    return <BusinessDashboard {...props} business={props.business} />;
}

function BusinessDashboard({
    business: { data: business },
    filters,
    categories,
    competitors,
    stats,
    landscape,
    latestLandscapeRun,
    isComparing,
    apiKeys,
    activity,
    changes,
    gaps,
}: Props & { business: { data: Business } }) {
    // A comparison takes a minute or two; check back while one is going.
    const { start, stop } = usePoll(
        3000,
        { only: ['landscape', 'latestLandscapeRun', 'isComparing'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isComparing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [isComparing, start, stop]);

    const report = landscape?.data.report ?? null;
    const filteredTo = competitors.find(
        (competitor) => competitor.id === filters.competitor,
    );

    return (
        <>
            <Head title="Dashboard" />

            <Page>
                <SectionHeading
                    title="Dashboard"
                    note={`${business.name} against ${stats.competitors === 1 ? '1 competitor' : `${stats.competitors} competitors`}`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={createCompetitor(business.id)}>
                                <Plus aria-hidden />
                                New competitor
                            </Link>
                        </Button>
                    }
                />

                <WhereYouStand
                    business={business}
                    landscape={landscape?.data ?? null}
                    latestRun={latestLandscapeRun?.data ?? null}
                    isComparing={isComparing}
                    apiKeys={apiKeys}
                    hasCompetitors={stats.competitors > 0}
                />

                {report && (
                    <section className="space-y-4">
                        <SectionHeading
                            as="h2"
                            size="sm"
                            title="How you compare"
                            note={`${report.dimensions.length} dimensions chosen for your market`}
                        />

                        <ComparisonMatrix report={report} />

                        {report.map && report.map.points.length > 1 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Positioning</CardTitle>
                                    <CardDescription>
                                        {report.map.x_axis} against{' '}
                                        {report.map.y_axis.toLowerCase()}.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="max-w-3xl">
                                    <PositioningMap map={report.map} />
                                </CardContent>
                            </Card>
                        )}
                    </section>
                )}

                <section className="space-y-4">
                    <SectionHeading
                        as="h2"
                        size="sm"
                        title="Who's moving"
                        note={`Changes on their pages in the last ${filters.window} days`}
                        actions={
                            <ActivityFilters
                                filters={filters}
                                competitors={competitors}
                                categories={categories}
                            />
                        }
                    />

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatTile
                            label="Competitors"
                            value={stats.competitors.toLocaleString()}
                        />
                        <StatTile
                            label="Pages watched"
                            value={stats.watchedPages.toLocaleString()}
                        />
                        <StatTile
                            label={`Changes · ${filters.window}d`}
                            value={stats.changes.toLocaleString()}
                            tone="ribbon"
                        />
                        <StatTile
                            label="Parked"
                            value={stats.parked.toLocaleString()}
                            tone={stats.parked > 0 ? 'warn' : 'default'}
                            note={
                                stats.parked > 0 ? 'needs a look' : 'all clear'
                            }
                        />
                    </div>

                    <ActivityTable
                        activity={activity}
                        filters={filters}
                        categories={categories}
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle>What changed</CardTitle>
                            <CardDescription>
                                {[
                                    filteredTo?.name ?? 'Every competitor',
                                    filters.category
                                        ? categories
                                              .find(
                                                  (category) =>
                                                      category.value ===
                                                      filters.category,
                                              )
                                              ?.label.toLowerCase()
                                        : null,
                                    `last ${filters.window} days`,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                                . Newest first.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChangeList
                                changes={changes.data}
                                showPage
                                emptyMessage="Nothing moved in this window"
                            />
                        </CardContent>
                    </Card>
                </section>

                <section className="space-y-4">
                    <SectionHeading
                        as="h2"
                        size="sm"
                        title="What's missing"
                        note="Gaps that make the comparison less sure"
                    />
                    <Card>
                        <CardContent>
                            <Gaps gaps={gaps} />
                        </CardContent>
                    </Card>
                </section>
            </Page>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

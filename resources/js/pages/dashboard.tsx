import { Head, Link } from '@inertiajs/react';
import { Bug, Plus } from 'lucide-react';
import { ChangeList } from '@/components/creep/change-list';
import { LatestReading } from '@/components/creep/latest-reading';
import { RunStatusBadge } from '@/components/creep/status-badges';
import {
    EmptyLine,
    EmptyState,
    Page,
    SectionHeading,
    StatTile,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRelative, hostOf } from '@/lib/format';
import { dashboard } from '@/routes';
import { create, index, show } from '@/routes/creep-targets';
import type {
    CreepChange,
    CreepRun,
    CreepTarget,
    ResourceCollection,
} from '@/types';

type Props = {
    stats: {
        targets: number;
        active: number;
        failing: number;
        changesThisWeek: number;
    };
    recentChanges: ResourceCollection<CreepChange>;
    recentRuns: ResourceCollection<CreepRun>;
    watchlist: ResourceCollection<CreepTarget>;
};

export default function Dashboard({
    stats,
    recentChanges,
    recentRuns,
    watchlist,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />

            <Page>
                <SectionHeading
                    title="Dashboard"
                    note="What Creeper has been up to"
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden />
                                New target
                            </Link>
                        </Button>
                    }
                />

                {stats.targets === 0 ? (
                    <EmptyState
                        icon={Bug}
                        title="Nothing to creep"
                        actions={
                            <>
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus aria-hidden />
                                        Add your first target
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={index()}>View targets</Link>
                                </Button>
                            </>
                        }
                    >
                        Point Creeper at a product page and it will track the
                        price and the stock, and tell you when either moves.
                    </EmptyState>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatTile
                                label="Targets"
                                value={stats.targets.toLocaleString()}
                            />
                            <StatTile
                                label="Active"
                                value={stats.active.toLocaleString()}
                                tone="ribbon"
                            />
                            <StatTile
                                label="Changes this week"
                                value={stats.changesThisWeek.toLocaleString()}
                            />
                            <StatTile
                                label="Parked"
                                value={stats.failing.toLocaleString()}
                                tone={stats.failing > 0 ? 'warn' : 'default'}
                                note={
                                    stats.failing > 0
                                        ? 'needs a look'
                                        : 'all clear'
                                }
                            />
                        </div>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Recent changes</CardTitle>
                                    <CardDescription>
                                        What moved across everything you watch.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ChangeList
                                        changes={recentChanges.data}
                                        showTarget
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Watchlist</CardTitle>
                                    <CardDescription>
                                        Your five most recent targets.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Watchlist targets={watchlist.data} />
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Recent runs</CardTitle>
                                <CardDescription>
                                    The last ten times Creeper went looking.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <RecentRuns runs={recentRuns.data} />
                            </CardContent>
                        </Card>
                    </>
                )}
            </Page>
        </>
    );
}

function Watchlist({ targets }: { targets: CreepTarget[] }) {
    if (targets.length === 0) {
        return <EmptyLine>Nothing here yet</EmptyLine>;
    }

    return (
        <ul className="divide-y divide-rule">
            {targets.map((target) => (
                <li
                    key={target.id}
                    className="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                    <div className="min-w-0">
                        <Link
                            href={show(target.id)}
                            className="block truncate text-sm font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                            prefetch
                        >
                            {target.latest_snapshot?.title ??
                                target.latest_changelog_snapshot?.product ??
                                target.display_name}
                        </Link>
                        <p className="truncate font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                            {hostOf(target.url)}
                        </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-3">
                        <LatestReading target={target} />
                    </div>
                </li>
            ))}
        </ul>
    );
}

function RecentRuns({ runs }: { runs: CreepRun[] }) {
    if (runs.length === 0) {
        return <EmptyLine>No runs yet</EmptyLine>;
    }

    return (
        <ul className="divide-y divide-rule">
            {runs.map((run) => (
                <li
                    key={run.id}
                    className="flex flex-wrap items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                >
                    <div className="flex min-w-0 items-center gap-3">
                        <RunStatusBadge
                            status={run.status}
                            label={run.status_label}
                        />
                        {run.target && (
                            <Link
                                href={show(run.target.id)}
                                className="truncate text-sm underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                            >
                                {run.target.display_name}
                            </Link>
                        )}
                    </div>
                    <span className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                        {formatRelative(run.started_at)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

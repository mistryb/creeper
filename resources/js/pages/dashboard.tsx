import { Head, Link } from '@inertiajs/react';
import { Bug, Plus } from 'lucide-react';
import { ChangeList } from '@/components/creep/change-list';
import {
    AvailabilityBadge,
    RunStatusBadge,
} from '@/components/creep/status-badges';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatPrice, formatRelative, hostOf } from '@/lib/format';
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

            <div className="space-y-6 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Dashboard"
                        description="What Creeper has been up to."
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden />
                            New target
                        </Link>
                    </Button>
                </div>

                {stats.targets === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatTile label="Targets" value={stats.targets} />
                            <StatTile label="Active" value={stats.active} />
                            <StatTile
                                label="Changes this week"
                                value={stats.changesThisWeek}
                            />
                            <StatTile
                                label="Parked"
                                value={stats.failing}
                                tone={stats.failing > 0 ? 'warning' : 'default'}
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
            </div>
        </>
    );
}

function StatTile({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: number;
    tone?: 'default' | 'warning';
}) {
    return (
        <div className="rounded-xl border border-border p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p
                className={
                    tone === 'warning'
                        ? 'mt-1 text-3xl font-semibold text-amber-600 tabular-nums dark:text-amber-400'
                        : 'mt-1 text-3xl font-semibold tabular-nums'
                }
            >
                {value.toLocaleString()}
            </p>
        </div>
    );
}

function Watchlist({ targets }: { targets: CreepTarget[] }) {
    if (targets.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-muted-foreground">
                Nothing here yet.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border">
            {targets.map((target) => (
                <li
                    key={target.id}
                    className="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <div className="min-w-0">
                        <Link
                            href={show(target.id)}
                            className="block truncate text-sm font-medium underline-offset-4 hover:underline"
                            prefetch
                        >
                            {target.latest_snapshot?.title ??
                                target.display_name}
                        </Link>
                        <p className="truncate text-xs text-muted-foreground">
                            {hostOf(target.url)}
                        </p>
                    </div>

                    <div className="flex shrink-0 items-center gap-3">
                        {target.latest_snapshot && (
                            <AvailabilityBadge
                                availability={
                                    target.latest_snapshot.availability
                                }
                                label={
                                    target.latest_snapshot.availability_label
                                }
                            />
                        )}
                        <span className="text-sm font-medium tabular-nums">
                            {formatPrice(
                                target.latest_snapshot?.price_amount,
                                target.latest_snapshot?.currency,
                            )}
                        </span>
                    </div>
                </li>
            ))}
        </ul>
    );
}

function RecentRuns({ runs }: { runs: CreepRun[] }) {
    if (runs.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-muted-foreground">
                No runs yet.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border">
            {runs.map((run) => (
                <li
                    key={run.id}
                    className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                >
                    <div className="flex min-w-0 items-center gap-3">
                        <RunStatusBadge
                            status={run.status}
                            label={run.status_label}
                        />
                        {run.target && (
                            <Link
                                href={show(run.target.id)}
                                className="truncate text-sm underline-offset-4 hover:underline"
                            >
                                {run.target.display_name}
                            </Link>
                        )}
                    </div>
                    <span className="text-xs text-muted-foreground">
                        {formatRelative(run.started_at)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center">
            <Bug aria-hidden className="size-8 text-muted-foreground" />
            <div className="space-y-1">
                <p className="font-medium">Creeper has nothing to creep.</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    Point it at a product page and it will track the price and
                    the stock, and tell you when either moves.
                </p>
            </div>
            <div className="flex gap-2">
                <Button asChild>
                    <Link href={create()}>
                        <Plus aria-hidden />
                        Add your first target
                    </Link>
                </Button>
                <Button variant="outline" asChild>
                    <Link href={index()}>View targets</Link>
                </Button>
            </div>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

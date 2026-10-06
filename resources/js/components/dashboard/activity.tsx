import { Link, router } from '@inertiajs/react';
import { EmptyLine, Tally } from '@/components/ds';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatRelative } from '@/lib/format';
import { dashboard } from '@/routes';
import { show as showCompetitor } from '@/routes/competitors';
import type {
    CompetitorActivity,
    DashboardFilters,
    PageCategory,
    SelectOption,
} from '@/types';

/** Everything that changes when a filter does. */
const FILTERED_PROPS = ['filters', 'stats', 'activity', 'changes'];

const ALL = 'all';

/**
 * Re-read the filtered parts of the dashboard, and nothing else — the
 * comparison above does not depend on them.
 */
export function applyFilters(filters: DashboardFilters): void {
    router.get(
        dashboard.url({
            query: {
                window: filters.window,
                competitor: filters.competitor ?? undefined,
                category: filters.category ?? undefined,
            },
        }),
        {},
        {
            only: FILTERED_PROPS,
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

/**
 * The filters, in one row above everything they narrow: the window, one
 * competitor, one kind of page.
 */
export function ActivityFilters({
    filters,
    competitors,
    categories,
}: {
    filters: DashboardFilters;
    competitors: { id: number; name: string }[];
    categories: SelectOption[];
}) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={String(filters.window)}
                onValueChange={(value) =>
                    value &&
                    applyFilters({
                        ...filters,
                        window: Number(value) as DashboardFilters['window'],
                    })
                }
                aria-label="Window"
            >
                {[7, 30, 90].map((days) => (
                    <ToggleGroupItem
                        key={days}
                        value={String(days)}
                        className="px-3 font-mono text-xs"
                    >
                        {days}d
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            <Select
                value={filters.competitor ? String(filters.competitor) : ALL}
                onValueChange={(value) =>
                    applyFilters({
                        ...filters,
                        competitor: value === ALL ? null : Number(value),
                    })
                }
            >
                <SelectTrigger
                    aria-label="Competitor"
                    size="sm"
                    className="w-44"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Every competitor</SelectItem>
                    {competitors.map((competitor) => (
                        <SelectItem
                            key={competitor.id}
                            value={String(competitor.id)}
                        >
                            {competitor.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filters.category ?? ALL}
                onValueChange={(value) =>
                    applyFilters({
                        ...filters,
                        category:
                            value === ALL ? null : (value as PageCategory),
                    })
                }
            >
                <SelectTrigger
                    aria-label="Kind of page"
                    size="sm"
                    className="w-48"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Every kind of page</SelectItem>
                    {categories.map((category) => (
                        <SelectItem key={category.value} value={category.value}>
                            {category.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

/**
 * Who moved, and on what kind of page. Clicking a count narrows the feed to
 * exactly those changes.
 */
export function ActivityTable({
    activity,
    filters,
    categories,
}: {
    activity: CompetitorActivity[];
    filters: DashboardFilters;
    categories: SelectOption[];
}) {
    if (activity.length === 0) {
        return <EmptyLine>No competitors yet</EmptyLine>;
    }

    const categoryMax = Math.max(
        1,
        ...activity.flatMap((row) => Object.values(row.by_category)),
    );
    const totalMax = Math.max(1, ...activity.map((row) => row.total));

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableCaption className="sr-only">
                    Changes per competitor over the last {filters.window} days
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead scope="col">Competitor</TableHead>
                        {categories.map((category) => (
                            <TableHead key={category.value} scope="col">
                                {category.label}
                            </TableHead>
                        ))}
                        <TableHead scope="col">Total</TableHead>
                        <TableHead scope="col">Last change</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {activity.map((row) => (
                        <TableRow key={row.competitor_id}>
                            <TableCell>
                                <Link
                                    href={showCompetitor(row.competitor_id)}
                                    className="font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                >
                                    {row.name}
                                </Link>
                                <p className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                    {row.watched_pages === 1
                                        ? '1 page'
                                        : `${row.watched_pages} pages`}
                                </p>
                            </TableCell>
                            {categories.map((category) => {
                                const count =
                                    row.by_category[
                                        category.value as PageCategory
                                    ] ?? 0;

                                return (
                                    <TableCell key={category.value}>
                                        <button
                                            type="button"
                                            disabled={count === 0}
                                            onClick={() =>
                                                applyFilters({
                                                    ...filters,
                                                    competitor:
                                                        row.competitor_id,
                                                    category:
                                                        category.value as PageCategory,
                                                })
                                            }
                                            className="w-full text-left disabled:cursor-default"
                                            aria-label={`Show ${row.name}'s ${category.label.toLowerCase()} changes`}
                                        >
                                            <Tally
                                                value={count}
                                                max={categoryMax}
                                            />
                                        </button>
                                    </TableCell>
                                );
                            })}
                            <TableCell>
                                <Tally
                                    value={row.total}
                                    max={totalMax}
                                    label="changes"
                                />
                            </TableCell>
                            <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                {row.last_change_at
                                    ? formatRelative(row.last_change_at)
                                    : 'quiet'}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

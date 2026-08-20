import { Head, Link } from '@inertiajs/react';
import { Bug, Plus } from 'lucide-react';
import {
    AvailabilityBadge,
    TargetStatusBadge,
} from '@/components/creep/status-badges';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatPrice, formatRelative, hostOf } from '@/lib/format';
import { create, index, show } from '@/routes/creep-targets';
import type { CreepTarget, PaginatedCollection } from '@/types';

export default function CreepTargetsIndex({
    targets,
}: {
    targets: PaginatedCollection<CreepTarget>;
}) {
    return (
        <>
            <Head title="Creep targets" />

            <div className="px-4 py-6">
                <div className="mb-8 flex items-start justify-between gap-4">
                    <Heading
                        title="Creep targets"
                        description="Everything Creeper is keeping an eye on."
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden />
                            New target
                        </Link>
                    </Button>
                </div>

                {targets.data.length === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-xl border border-border">
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    Creep targets
                                </caption>
                                <thead className="border-b border-border bg-muted/40 text-left">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Target
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Price
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Stock
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Status
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-4 py-3 font-medium"
                                        >
                                            Last crept
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {targets.data.map((target) => (
                                        <tr
                                            key={target.id}
                                            className="transition-colors hover:bg-muted/40"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={show(target.id)}
                                                    className="font-medium text-foreground underline-offset-4 hover:underline"
                                                    prefetch
                                                >
                                                    {target.latest_snapshot
                                                        ?.title ??
                                                        target.display_name}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {hostOf(target.url)}
                                                    <span aria-hidden> · </span>
                                                    {target.frequency_label.toLowerCase()}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3 font-medium tabular-nums">
                                                {formatPrice(
                                                    target.latest_snapshot
                                                        ?.price_amount,
                                                    target.latest_snapshot
                                                        ?.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {target.latest_snapshot ? (
                                                    <AvailabilityBadge
                                                        availability={
                                                            target
                                                                .latest_snapshot
                                                                .availability
                                                        }
                                                        label={
                                                            target
                                                                .latest_snapshot
                                                                .availability_label
                                                        }
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <TargetStatusBadge
                                                    status={target.status}
                                                    label={target.status_label}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {formatRelative(
                                                    target.last_crept_at,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {targets.meta.last_page > 1 && (
                            <nav
                                aria-label="Pagination"
                                className="mt-4 flex items-center justify-between text-sm"
                            >
                                <p className="text-muted-foreground">
                                    Page {targets.meta.current_page} of{' '}
                                    {targets.meta.last_page}
                                </p>

                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        disabled={!targets.links.prev}
                                    >
                                        <Link
                                            href={targets.links.prev ?? index()}
                                        >
                                            Previous
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        disabled={!targets.links.next}
                                    >
                                        <Link
                                            href={targets.links.next ?? index()}
                                        >
                                            Next
                                        </Link>
                                    </Button>
                                </div>
                            </nav>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

function EmptyState() {
    return (
        <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed border-border px-6 py-16 text-center">
            <Bug aria-hidden className="size-8 text-muted-foreground" />
            <div className="space-y-1">
                <p className="font-medium">Nothing is being crept yet.</p>
                <p className="max-w-sm text-sm text-muted-foreground">
                    Give Creeper a product URL and it will watch the price and
                    the stock for you.
                </p>
            </div>
            <Button asChild>
                <Link href={create()}>
                    <Plus aria-hidden />
                    Add your first target
                </Link>
            </Button>
        </div>
    );
}

CreepTargetsIndex.layout = {
    breadcrumbs: [{ title: 'Creep targets', href: index() }],
};

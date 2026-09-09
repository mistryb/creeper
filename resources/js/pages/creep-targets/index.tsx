import { Head, Link } from '@inertiajs/react';
import { Bug, Plus } from 'lucide-react';
import { LatestReading } from '@/components/creep/latest-reading';
import { PauseButton } from '@/components/creep/pause-button';
import { TargetStatusBadge } from '@/components/creep/status-badges';
import { EmptyState, Page, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatRelative, hostOf } from '@/lib/format';
import { create, index, show } from '@/routes/creep-targets';
import type { CreepTarget, PaginatedCollection } from '@/types';

export default function CreepTargetsIndex({
    targets,
}: {
    targets: PaginatedCollection<CreepTarget>;
}) {
    const { current_page: page, last_page: lastPage, total } = targets.meta;

    return (
        <>
            <Head title="Creep targets" />

            <Page>
                <SectionHeading
                    title="Creep targets"
                    note={
                        total === 1
                            ? '1 page on watch'
                            : `${total.toLocaleString()} pages on watch`
                    }
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden />
                                New target
                            </Link>
                        </Button>
                    }
                />

                {targets.data.length === 0 ? (
                    <EmptyState
                        icon={Bug}
                        title="Nothing is being crept"
                        actions={
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus aria-hidden />
                                    Add your first target
                                </Link>
                            </Button>
                        }
                    >
                        Point Creeper at a product page or a changelog, and it
                        will watch it for you.
                    </EmptyState>
                ) : (
                    <>
                        <Table>
                            <TableCaption className="sr-only">
                                Creep targets
                            </TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead scope="col">Target</TableHead>
                                    <TableHead scope="col">Latest</TableHead>
                                    <TableHead scope="col">Status</TableHead>
                                    <TableHead scope="col">
                                        Last crept
                                    </TableHead>
                                    <TableHead scope="col">
                                        <span className="sr-only">
                                            Pause or resume
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {targets.data.map((target) => (
                                    <TableRow key={target.id}>
                                        <TableCell>
                                            <Link
                                                href={show(target.id)}
                                                className="font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                                prefetch
                                            >
                                                {target.latest_snapshot
                                                    ?.title ??
                                                    target
                                                        .latest_changelog_snapshot
                                                        ?.product ??
                                                    target.display_name}
                                            </Link>
                                            <p className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                                {hostOf(target.url)}
                                                <span aria-hidden> · </span>
                                                {target.type_label.toLowerCase()}
                                                <span aria-hidden> · </span>
                                                {target.frequency_label.toLowerCase()}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <LatestReading target={target} />
                                        </TableCell>
                                        <TableCell>
                                            <TargetStatusBadge
                                                status={target.status}
                                                label={target.status_label}
                                            />
                                        </TableCell>
                                        <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                            {formatRelative(
                                                target.last_crept_at,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <PauseButton
                                                target={target}
                                                variant="ghost"
                                                compact
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {lastPage > 1 && (
                            <nav
                                aria-label="Pagination"
                                className="flex items-center justify-between gap-4"
                            >
                                <p className="label-micro text-muted-foreground">
                                    Page {page} of {lastPage}
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
            </Page>
        </>
    );
}

CreepTargetsIndex.layout = {
    breadcrumbs: [{ title: 'Creep targets', href: index() }],
};

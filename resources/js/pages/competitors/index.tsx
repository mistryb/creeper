import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Plus, Users } from 'lucide-react';
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
import { create, index } from '@/routes/businesses/competitors';
import { show } from '@/routes/competitors';
import type { Business, Competitor, ResourceCollection } from '@/types';

export default function CompetitorsIndex({
    business: { data: business },
    competitors,
}: {
    business: { data: Business };
    competitors: ResourceCollection<Competitor>;
}) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Competitors', href: index(business.id) }],
    });

    const count = competitors.data.length;

    return (
        <>
            <Head title="Competitors" />

            <Page>
                <SectionHeading
                    title="Competitors"
                    note={`${count === 1 ? '1 competitor' : `${count} competitors`} of ${business.name}`}
                    actions={
                        <Button asChild>
                            <Link href={create(business.id)}>
                                <Plus aria-hidden />
                                Add a competitor
                            </Link>
                        </Button>
                    }
                />

                {count === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No competitors yet"
                        actions={
                            <Button asChild>
                                <Link href={create(business.id)}>
                                    <Plus aria-hidden />
                                    Add one yourself
                                </Link>
                            </Button>
                        }
                    >
                        Creeper looks for them when a company is set up, and
                        again whenever you ask from the dashboard. Anyone it
                        misses, add here.
                    </EmptyState>
                ) : (
                    <Table>
                        <TableCaption className="sr-only">
                            Competitors of {business.name}
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">Competitor</TableHead>
                                <TableHead scope="col">Why</TableHead>
                                <TableHead scope="col">Source</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {competitors.data.map((competitor) => (
                                <TableRow key={competitor.id}>
                                    <TableCell className="whitespace-normal">
                                        <Link
                                            href={show(competitor.id)}
                                            className="font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                            prefetch
                                        >
                                            {competitor.name}
                                        </Link>
                                        {competitor.url && (
                                            <p className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                                {hostOf(competitor.url)}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-xl text-sm whitespace-normal text-muted-foreground">
                                        {competitor.reason ??
                                            competitor.description ??
                                            '—'}
                                    </TableCell>
                                    <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                        {competitor.discovered_at
                                            ? `found ${formatRelative(competitor.discovered_at)}`
                                            : 'added by you'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Page>
        </>
    );
}

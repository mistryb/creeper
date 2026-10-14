import { Head, Link } from '@inertiajs/react';
import { Building2, Plus } from 'lucide-react';
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
import { create, index, show } from '@/routes/businesses';
import type { Business, ResourceCollection } from '@/types';

export default function BusinessesIndex({
    businesses,
}: {
    businesses: ResourceCollection<Business>;
}) {
    const count = businesses.data.length;

    return (
        <>
            <Head title="Companies" />

            <Page>
                <SectionHeading
                    title="Companies"
                    note={count === 1 ? '1 company' : `${count} companies`}
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden />
                                New company
                            </Link>
                        </Button>
                    }
                />

                {count === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="No company yet"
                        actions={
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus aria-hidden />
                                    Set up your company
                                </Link>
                            </Button>
                        }
                    >
                        Describe your company. Creeper finds who you are up
                        against and writes you a digest on a schedule.
                    </EmptyState>
                ) : (
                    <Table>
                        <TableCaption className="sr-only">
                            Companies
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">Company</TableHead>
                                <TableHead scope="col">Last updated</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {businesses.data.map((business) => (
                                <TableRow key={business.id}>
                                    <TableCell className="max-w-xl whitespace-normal">
                                        <Link
                                            href={show(business.id)}
                                            className="font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                            prefetch
                                        >
                                            {business.name}
                                        </Link>
                                        {business.url && (
                                            <p className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                                {hostOf(business.url)}
                                            </p>
                                        )}
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {business.description}
                                        </p>
                                    </TableCell>
                                    <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                        {formatRelative(business.updated_at)}
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

BusinessesIndex.layout = {
    breadcrumbs: [{ title: 'Companies', href: index() }],
};

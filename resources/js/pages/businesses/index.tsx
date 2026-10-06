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
            <Head title="Businesses" />

            <Page>
                <SectionHeading
                    title="Businesses"
                    note={count === 1 ? '1 business' : `${count} businesses`}
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus aria-hidden />
                                New business
                            </Link>
                        </Button>
                    }
                />

                {count === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="No business yet"
                        actions={
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus aria-hidden />
                                    Set up your business
                                </Link>
                            </Button>
                        }
                    >
                        Describe your business, and Creeper will know what to
                        look for when it watches your competitors.
                    </EmptyState>
                ) : (
                    <Table>
                        <TableCaption className="sr-only">
                            Businesses
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">Business</TableHead>
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
    breadcrumbs: [{ title: 'Businesses', href: index() }],
};

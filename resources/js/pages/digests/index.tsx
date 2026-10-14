import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { RunStatusBadge } from '@/components/digest/status-badge';
import { EmptyState, Page, SectionHeading } from '@/components/ds';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatRelative } from '@/lib/format';
import { index } from '@/routes/businesses/digests';
import { show } from '@/routes/digests';
import type { Business, Digest, ResourceCollection } from '@/types';

export default function DigestsIndex({
    business: { data: business },
    digests,
}: {
    business: { data: Business };
    digests: ResourceCollection<Digest>;
}) {
    setLayoutProps({
        breadcrumbs: [{ title: 'Digests', href: index(business.id) }],
    });

    const count = digests.data.length;

    return (
        <>
            <Head title="Digests" />

            <Page>
                <SectionHeading
                    title="Digests"
                    note={`${count === 1 ? '1 digest' : `${count} digests`} about ${business.name}'s competitors`}
                />

                {count === 0 ? (
                    <EmptyState icon={Mail} title="No digests yet">
                        The first one goes out{' '}
                        {business.next_digest_at
                            ? formatDateTime(business.next_digest_at)
                            : 'on the schedule'}
                        . Every one sent is kept here.
                    </EmptyState>
                ) : (
                    <Table>
                        <TableCaption className="sr-only">
                            Digests about {business.name}&rsquo;s competitors
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
                                <TableHead scope="col">Period</TableHead>
                                <TableHead scope="col">Headline</TableHead>
                                <TableHead scope="col">Status</TableHead>
                                <TableHead scope="col">Sent</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {digests.data.map((digest) => (
                                <TableRow key={digest.id}>
                                    <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] whitespace-normal tabular-nums">
                                        <Link
                                            href={show(digest.id)}
                                            className="underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                            prefetch
                                        >
                                            {formatDateTime(
                                                digest.period_start,
                                            )}{' '}
                                            to{' '}
                                            {formatDateTime(digest.period_end)}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="max-w-xl whitespace-normal">
                                        {digest.report?.headline ?? (
                                            <span className="text-muted-foreground">
                                                {digest.error ?? '—'}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <RunStatusBadge
                                            status={digest.status}
                                            label={digest.status_label}
                                        />
                                    </TableCell>
                                    <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                        {formatRelative(digest.sent_at)}
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

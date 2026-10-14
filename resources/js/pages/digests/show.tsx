import { Head, setLayoutProps } from '@inertiajs/react';
import { DigestReportView } from '@/components/digest/digest-report';
import { RunStatusBadge } from '@/components/digest/status-badge';
import { Page, ReceiptRow, SectionHeading } from '@/components/ds';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime, formatRelative } from '@/lib/format';
import { index } from '@/routes/businesses/digests';
import { show } from '@/routes/digests';
import type { Business, Competitor, Digest, ResourceCollection } from '@/types';

export default function ShowDigest({
    business: { data: business },
    digest: { data: digest },
    competitors,
}: {
    business: { data: Business };
    digest: { data: Digest };
    competitors: ResourceCollection<Competitor>;
}) {
    const title = `${formatDateTime(digest.period_start)} to ${formatDateTime(digest.period_end)}`;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Digests', href: index(business.id) },
            { title, href: show(digest.id) },
        ],
    });

    return (
        <>
            <Head title={`Digest · ${title}`} />

            <Page>
                <SectionHeading
                    title="Competitor digest"
                    note={title}
                    description={
                        <RunStatusBadge
                            status={digest.status}
                            label={digest.status_label}
                        />
                    }
                />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div>
                        {digest.report ? (
                            <DigestReportView
                                report={digest.report}
                                competitors={competitors.data}
                            />
                        ) : digest.error ? (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    This digest was not written
                                </AlertTitle>
                                <AlertDescription>
                                    {digest.error}
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Still being written.
                            </p>
                        )}
                    </div>

                    <Card className="self-start">
                        <CardHeader>
                            <CardTitle>About this digest</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="divide-y divide-rule">
                                <ReceiptRow
                                    labelAs="dt"
                                    valueAs="dd"
                                    label="Company"
                                    value={business.name}
                                />
                                <ReceiptRow
                                    labelAs="dt"
                                    valueAs="dd"
                                    label="Sent"
                                    value={formatRelative(digest.sent_at)}
                                />
                                <ReceiptRow
                                    labelAs="dt"
                                    valueAs="dd"
                                    label="Written by"
                                    value={
                                        digest.provider
                                            ? `${digest.provider}${digest.model ? ` · ${digest.model}` : ''}`
                                            : '—'
                                    }
                                />
                                <ReceiptRow
                                    labelAs="dt"
                                    valueAs="dd"
                                    label="Tokens"
                                    value={
                                        digest.prompt_tokens !== null
                                            ? `${digest.prompt_tokens.toLocaleString()} in · ${(digest.completion_tokens ?? 0).toLocaleString()} out`
                                            : '—'
                                    }
                                />
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </Page>
        </>
    );
}

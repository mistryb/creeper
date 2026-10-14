import { Form, Head, Link, usePoll } from '@inertiajs/react';
import {
    Building2,
    ExternalLink,
    Mail,
    Plus,
    Search,
    Users,
} from 'lucide-react';
import { useEffect } from 'react';
import CompetitorDiscoveryController from '@/actions/App/Http/Controllers/CompetitorDiscoveryController';
import DigestController from '@/actions/App/Http/Controllers/DigestController';
import { DigestReportView } from '@/components/digest/digest-report';
import { RunStatusBadge } from '@/components/digest/status-badge';
import {
    EmptyLine,
    EmptyState,
    Page,
    ReceiptRow,
    SectionHeading,
} from '@/components/ds';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime, formatRelative, hostOf } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as apiKeysIndex } from '@/routes/api-keys';
import {
    create as createBusiness,
    show as showBusiness,
} from '@/routes/businesses';
import { create as createCompetitor } from '@/routes/businesses/competitors';
import { index as digestsIndex } from '@/routes/businesses/digests';
import { show as showCompetitor } from '@/routes/competitors';
import { show as showDigest } from '@/routes/digests';
import type {
    Business,
    Competitor,
    CompetitorDiscovery,
    Digest,
    ResourceCollection,
    SelectOption,
} from '@/types';

type Props = {
    /** The company picked in the sidebar chooser; null before there is one. */
    business: { data: Business } | null;
    frequencies: SelectOption[];
    competitors: ResourceCollection<Competitor>;
    /** The newest digest that was written and sent. */
    digest: { data: Digest } | null;
    /** The newest run of any kind, so a failure explains itself. */
    latestDigestRun: { data: Digest } | null;
    isDigesting: boolean;
    latestDiscovery: { data: CompetitorDiscovery } | null;
    isDiscovering: boolean;
    hasApiKeys: boolean;
};

export default function Dashboard(props: Props) {
    if (props.business === null) {
        return (
            <>
                <Head title="Dashboard" />
                <Page>
                    <SectionHeading title="Dashboard" />
                    <EmptyState
                        icon={Building2}
                        title="No company yet"
                        actions={
                            <Button asChild>
                                <Link href={createBusiness()}>
                                    <Plus aria-hidden />
                                    Set up your company
                                </Link>
                            </Button>
                        }
                    >
                        Describe your company. Creeper finds who you are up
                        against and emails you what they are doing, on a
                        schedule.
                    </EmptyState>
                </Page>
            </>
        );
    }

    return <CompanyDashboard {...props} business={props.business} />;
}

function CompanyDashboard({
    business: { data: business },
    competitors,
    digest,
    latestDigestRun,
    isDigesting,
    latestDiscovery,
    isDiscovering,
    hasApiKeys,
}: Props & { business: { data: Business } }) {
    // Finding competitors and writing a digest each take a minute or two;
    // check back while either is going.
    const busy = isDigesting || isDiscovering;

    const { start, stop } = usePoll(
        3000,
        {
            only: [
                'competitors',
                'digest',
                'latestDigestRun',
                'isDigesting',
                'latestDiscovery',
                'isDiscovering',
            ],
        },
        { autoStart: false },
    );

    useEffect(() => {
        if (busy) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [busy, start, stop]);

    const count = competitors.data.length;
    const keyless = business.api_key_id === null;

    return (
        <>
            <Head title="Dashboard" />

            <Page>
                <SectionHeading
                    title={business.name}
                    note={`${count === 1 ? '1 competitor' : `${count} competitors`} · digest ${business.digest_frequency_label.toLowerCase()}`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={showBusiness(business.id)} prefetch>
                                Company settings
                            </Link>
                        </Button>
                    }
                />

                {keyless && (
                    <Alert variant="destructive">
                        <AlertTitle>This company has no API key</AlertTitle>
                        <AlertDescription>
                            The key it was spending has been removed, so nothing
                            can be found or written until you pick another one
                            in{' '}
                            <Link
                                href={showBusiness(business.id)}
                                className="underline underline-offset-4"
                            >
                                company settings
                            </Link>
                            {!hasApiKeys && (
                                <>
                                    {' '}
                                    (you will need to{' '}
                                    <Link
                                        href={apiKeysIndex()}
                                        className="underline underline-offset-4"
                                    >
                                        add one
                                    </Link>{' '}
                                    first)
                                </>
                            )}
                            .
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <section className="space-y-4">
                        <SectionHeading
                            as="h2"
                            size="sm"
                            title="Latest digest"
                            note={
                                digest
                                    ? `Covering up to ${formatDateTime(digest.data.period_end)}`
                                    : 'None written yet'
                            }
                            actions={
                                <>
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={digestsIndex(business.id)}
                                            prefetch
                                        >
                                            All digests
                                        </Link>
                                    </Button>
                                    <Form
                                        {...DigestController.store.form(
                                            business.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    isDigesting ||
                                                    keyless ||
                                                    count === 0
                                                }
                                            >
                                                <Mail aria-hidden />
                                                {isDigesting
                                                    ? 'Writing…'
                                                    : 'Send a digest now'}
                                            </Button>
                                        )}
                                    </Form>
                                </>
                            }
                        />

                        <LatestDigest
                            business={business}
                            competitors={competitors.data}
                            digest={digest?.data ?? null}
                            latestRun={latestDigestRun?.data ?? null}
                            isDigesting={isDigesting}
                        />
                    </section>

                    <section className="space-y-4">
                        <SectionHeading
                            as="h2"
                            size="sm"
                            title="Competitors"
                            note={
                                isDiscovering
                                    ? 'Creeper is looking…'
                                    : 'Found by Creeper, or added by you'
                            }
                            actions={
                                <>
                                    <Form
                                        {...CompetitorDiscoveryController.store.form(
                                            business.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    processing ||
                                                    isDiscovering ||
                                                    keyless
                                                }
                                            >
                                                <Search aria-hidden />
                                                {isDiscovering
                                                    ? 'Looking…'
                                                    : count === 0
                                                      ? 'Find competitors'
                                                      : 'Find more'}
                                            </Button>
                                        )}
                                    </Form>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link
                                            href={createCompetitor(business.id)}
                                        >
                                            <Plus aria-hidden />
                                            Add
                                        </Link>
                                    </Button>
                                </>
                            }
                        />

                        <CompetitorList
                            competitors={competitors.data}
                            isDiscovering={isDiscovering}
                            latestDiscovery={latestDiscovery?.data ?? null}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Schedule</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="divide-y divide-rule">
                                    <ReceiptRow
                                        labelAs="dt"
                                        valueAs="dd"
                                        label="Cadence"
                                        value={business.digest_frequency_label}
                                    />
                                    <ReceiptRow
                                        labelAs="dt"
                                        valueAs="dd"
                                        label="Next digest"
                                        value={
                                            business.next_digest_at
                                                ? formatDateTime(
                                                      business.next_digest_at,
                                                  )
                                                : '—'
                                        }
                                    />
                                    <ReceiptRow
                                        labelAs="dt"
                                        valueAs="dd"
                                        label="Last sent"
                                        value={formatRelative(
                                            business.last_digest_at,
                                        )}
                                    />
                                    <ReceiptRow
                                        labelAs="dt"
                                        valueAs="dd"
                                        label="Spends"
                                        value={
                                            business.api_key_label ?? 'no key'
                                        }
                                    />
                                </dl>
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </Page>
        </>
    );
}

function LatestDigest({
    business,
    competitors,
    digest,
    latestRun,
    isDigesting,
}: {
    business: Business;
    competitors: Competitor[];
    digest: Digest | null;
    latestRun: Digest | null;
    isDigesting: boolean;
}) {
    // A run newer than the digest on screen that went wrong is worth a word,
    // so a failure never hides behind an older success.
    const failure =
        latestRun?.status === 'failed' && latestRun.id !== digest?.id
            ? latestRun
            : null;

    return (
        <div className="space-y-4">
            {failure && (
                <Alert variant="destructive">
                    <AlertTitle>
                        The last digest could not be written
                    </AlertTitle>
                    <AlertDescription>{failure.error}</AlertDescription>
                </Alert>
            )}

            {isDigesting && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <RunStatusBadge status="running" label="Writing" />
                        </CardTitle>
                        <CardDescription>
                            Researching every competitor. This takes a minute or
                            two; the digest is emailed to you when it is done.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <Skeleton className="h-4 w-2/3" />
                        <Skeleton className="h-4 w-1/2" />
                        <Skeleton className="h-4 w-3/5" />
                    </CardContent>
                </Card>
            )}

            {digest ? (
                <Card>
                    <CardHeader>
                        <CardTitle>
                            <Link
                                href={showDigest(digest.id)}
                                className="underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                prefetch
                            >
                                {formatDateTime(digest.period_start)} to{' '}
                                {formatDateTime(digest.period_end)}
                            </Link>
                        </CardTitle>
                        <CardDescription>
                            Sent {formatRelative(digest.sent_at)}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DigestReportView
                            report={digest.report!}
                            competitors={competitors}
                        />
                    </CardContent>
                </Card>
            ) : (
                !isDigesting && (
                    <EmptyState icon={Mail} title="No digest yet">
                        The first one goes out{' '}
                        {business.next_digest_at
                            ? formatDateTime(business.next_digest_at)
                            : 'on the schedule'}
                        , or send one now.
                    </EmptyState>
                )
            )}
        </div>
    );
}

function CompetitorList({
    competitors,
    isDiscovering,
    latestDiscovery,
}: {
    competitors: Competitor[];
    isDiscovering: boolean;
    latestDiscovery: CompetitorDiscovery | null;
}) {
    return (
        <div className="space-y-3">
            {latestDiscovery?.status === 'failed' && (
                <Alert variant="destructive">
                    <AlertTitle>
                        Creeper could not look for competitors
                    </AlertTitle>
                    <AlertDescription>{latestDiscovery.error}</AlertDescription>
                </Alert>
            )}

            <div className="border border-rule bg-card shadow-xs">
                {competitors.length === 0 ? (
                    isDiscovering ? (
                        <div className="space-y-3 p-4">
                            <Skeleton className="h-4 w-1/2" />
                            <Skeleton className="h-4 w-2/3" />
                            <Skeleton className="h-4 w-2/5" />
                        </div>
                    ) : (
                        <EmptyLine>
                            <Users aria-hidden className="mr-2 inline size-4" />
                            Nobody yet
                        </EmptyLine>
                    )
                ) : (
                    <ul>
                        {competitors.map((competitor) => (
                            <li
                                key={competitor.id}
                                className="space-y-1 border-b border-rule px-4 py-3 last:border-b-0"
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <Link
                                        href={showCompetitor(competitor.id)}
                                        className="font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                        prefetch
                                    >
                                        {competitor.name}
                                    </Link>
                                    {competitor.url && (
                                        <a
                                            href={competitor.url}
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            className="inline-flex items-center gap-1.5 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                                        >
                                            {hostOf(competitor.url)}
                                            <ExternalLink
                                                aria-hidden
                                                className="size-3"
                                            />
                                        </a>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {competitor.reason ??
                                        competitor.description ??
                                        'Added by you.'}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

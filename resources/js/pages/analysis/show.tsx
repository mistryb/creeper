import { Form, Head, Link, setLayoutProps, usePoll } from '@inertiajs/react';
import { KeyRound, LoaderCircle, RefreshCw, ScanSearch } from 'lucide-react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import BusinessAnalysisController from '@/actions/App/Http/Controllers/BusinessAnalysisController';
import CompetitorAnalysisController from '@/actions/App/Http/Controllers/CompetitorAnalysisController';
import { RunStatusBadge } from '@/components/creep/status-badges';
import {
    Chip,
    EmptyState,
    Page,
    Panel,
    PanelBar,
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
import { formatRelative, hostOf } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as apiKeysSettings } from '@/routes/api-keys';
import {
    index as businessesIndex,
    show as showBusiness,
} from '@/routes/businesses';
import { index as businessAnalysis } from '@/routes/businesses/analysis';
import { index as competitorsIndex } from '@/routes/businesses/competitors';
import { show as showCompetitor } from '@/routes/competitors';
import { index as competitorAnalysis } from '@/routes/competitors/analysis';
import type {
    AnalysisSubject,
    Business,
    BusinessAnalysis,
    BusinessAnalysisReport,
    ResourceCollection,
    SelectOption,
} from '@/types';

/**
 * Where an analysis lives, and where its run button posts, for each kind of
 * subject. A business's own analysis and a competitor's share everything else.
 */
const routesFor = {
    business: {
        index: businessAnalysis,
        store: BusinessAnalysisController.store,
    },
    competitor: {
        index: competitorAnalysis,
        store: CompetitorAnalysisController.store,
    },
} as const;

export default function AnalysisShow({
    subject,
    business: { data: business },
    analysis,
    history,
    isAnalyzing,
    apiKeys,
}: {
    subject: AnalysisSubject;
    business: { data: Business };
    analysis: { data: BusinessAnalysis } | null;
    history: ResourceCollection<BusinessAnalysis>;
    isAnalyzing: boolean;
    apiKeys: SelectOption[];
}) {
    const routes = routesFor[subject.type];

    setLayoutProps({
        breadcrumbs:
            subject.type === 'business'
                ? [
                      { title: 'Businesses', href: businessesIndex() },
                      { title: business.name, href: showBusiness(business.id) },
                      { title: 'Analysis', href: routes.index(subject.id) },
                  ]
                : [
                      {
                          title: 'Competitors',
                          href: competitorsIndex(business.id),
                      },
                      { title: subject.name, href: showCompetitor(subject.id) },
                      { title: 'Analysis', href: routes.index(subject.id) },
                  ],
    });

    // A run takes a minute or two; check back while one is going, and stop
    // the moment it has finished.
    const { start, stop } = usePoll(
        3000,
        { only: ['analysis', 'history', 'isAnalyzing'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isAnalyzing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [isAnalyzing, start, stop]);

    const selected = analysis?.data ?? null;

    return (
        <>
            <Head title={`${subject.name} analysis`} />

            <Page>
                <SectionHeading
                    title="Analysis"
                    note={
                        subject.type === 'business'
                            ? subject.name
                            : `${subject.name} · competitor of ${business.name}`
                    }
                    actions={
                        <RunAnalysis
                            subject={subject}
                            apiKeys={apiKeys}
                            isAnalyzing={isAnalyzing}
                            hasRun={history.data.length > 0}
                        />
                    }
                />

                {selected === null ? (
                    <EmptyState
                        icon={ScanSearch}
                        title="No analysis yet"
                        actions={
                            apiKeys.length === 0 && (
                                <Button asChild>
                                    <Link href={apiKeysSettings()}>
                                        <KeyRound aria-hidden />
                                        Add an API key
                                    </Link>
                                </Button>
                            )
                        }
                    >
                        {subject.type === 'business'
                            ? `Creeper reads your description${subject.url ? ' and your website' : ''}, then writes up what you sell, who to, how you stand apart, and where you are strong or exposed.`
                            : `Creeper reads what you know about ${subject.name}${subject.url ? ' and their website' : ''}, then writes up what they sell, who to, how they stand apart from you, and where they are strong or exposed.`}
                    </EmptyState>
                ) : (
                    <Selected analysis={selected} />
                )}

                {history.data.length > 0 && (
                    <History
                        subject={subject}
                        history={history.data}
                        selectedId={selected?.id ?? null}
                    />
                )}
            </Page>
        </>
    );
}

/**
 * The run button, with the key it will spend. Each run is paid for with one of
 * the user's own keys, so the choice sits right next to the button.
 */
function RunAnalysis({
    subject,
    apiKeys,
    isAnalyzing,
    hasRun,
}: {
    subject: AnalysisSubject;
    apiKeys: SelectOption[];
    isAnalyzing: boolean;
    hasRun: boolean;
}) {
    if (apiKeys.length === 0) {
        return null;
    }

    return (
        <Form
            {...routesFor[subject.type].store.form(subject.id)}
            options={{ preserveScroll: true }}
            className="flex flex-wrap items-center gap-2"
        >
            {({ processing }) => (
                <>
                    {apiKeys.length > 1 && (
                        <Select
                            name="api_key_id"
                            defaultValue={apiKeys[0].value}
                        >
                            <SelectTrigger
                                aria-label="API key to spend"
                                className="w-64"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {apiKeys.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {apiKeys.length === 1 && (
                        <input
                            type="hidden"
                            name="api_key_id"
                            value={apiKeys[0].value}
                        />
                    )}

                    <Button type="submit" disabled={processing || isAnalyzing}>
                        {isAnalyzing ? (
                            <LoaderCircle
                                aria-hidden
                                className="animate-spin"
                            />
                        ) : (
                            <RefreshCw aria-hidden />
                        )}
                        {isAnalyzing
                            ? 'Analysing…'
                            : hasRun
                              ? 'Run again'
                              : 'Run analysis'}
                    </Button>
                </>
            )}
        </Form>
    );
}

function Selected({ analysis }: { analysis: BusinessAnalysis }) {
    if (analysis.status === 'failed') {
        return (
            <Alert variant="destructive">
                <AlertTitle>This analysis failed</AlertTitle>
                <AlertDescription>
                    <p>
                        {analysis.error ?? 'It failed for an unknown reason.'}
                    </p>
                </AlertDescription>
            </Alert>
        );
    }

    if (analysis.report === null) {
        return (
            <EmptyState icon={LoaderCircle} title="Analysing">
                Reading what there is to read about this business. This usually
                takes a minute or two, and the page will update by itself.
            </EmptyState>
        );
    }

    return <Report analysis={analysis} report={analysis.report} />;
}

function Report({
    analysis,
    report,
}: {
    analysis: BusinessAnalysis;
    report: BusinessAnalysisReport;
}) {
    return (
        <div className="space-y-6">
            <Panel>
                <PanelBar
                    title="Summary"
                    meta={formatRelative(analysis.finished_at)}
                />
                <div className="space-y-4 p-5">
                    <p className="max-w-prose text-base leading-relaxed">
                        {report.summary}
                    </p>
                    <div className="flex flex-wrap items-center gap-2">
                        <Chip
                            on={report.confidence === 'high'}
                            className={cn(
                                report.confidence === 'low' &&
                                    'border-ribbon-red/35 text-ribbon-red',
                            )}
                        >
                            {report.confidence} confidence
                        </Chip>
                        <Chip>
                            {analysis.source_url
                                ? `read ${hostOf(analysis.source_url)}`
                                : 'description only'}
                        </Chip>
                    </div>
                    {report.notes && (
                        <p className="text-sm text-muted-foreground">
                            <span className="mr-2 label-micro">Note</span>
                            {report.notes}
                        </p>
                    )}
                </div>
            </Panel>

            <div className="grid gap-4 md:grid-cols-2">
                <Section title="What it sells">{report.offering}</Section>
                <Section title="Who buys it">{report.audience}</Section>
                <Section title="How it stands apart">
                    {report.positioning}
                </Section>
                <Section title="Pricing">
                    {report.pricing ?? (
                        <span className="text-muted-foreground">
                            Not stated anywhere Creeper looked.
                        </span>
                    )}
                </Section>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <Points title="Strengths" points={report.strengths} />
                <Points title="Weaknesses" points={report.weaknesses} />
                <Points title="Opportunities" points={report.opportunities} />
                <Points title="Threats" points={report.threats} />
            </div>
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm leading-relaxed">
                {children}
            </CardContent>
        </Card>
    );
}

function Points({ title, points }: { title: string; points: string[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription className="font-mono text-xs">
                    {points.length}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {points.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Nothing the material supports.
                    </p>
                ) : (
                    <ul className="space-y-2 text-sm leading-relaxed">
                        {points.map((point) => (
                            <li key={point} className="flex gap-2.5">
                                <span
                                    aria-hidden
                                    className="mt-2 size-1.5 shrink-0 bg-ribbon"
                                />
                                {point}
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function History({
    subject,
    history,
    selectedId,
}: {
    subject: AnalysisSubject;
    history: BusinessAnalysis[];
    selectedId: number | null;
}) {
    return (
        <section className="space-y-3">
            <SectionHeading as="h2" size="sm" title="Runs" />

            <Table>
                <TableCaption className="sr-only">
                    Every time this business was analysed
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead scope="col">Run</TableHead>
                        <TableHead scope="col">Status</TableHead>
                        <TableHead scope="col">Read</TableHead>
                        <TableHead scope="col">Tokens</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {history.map((run) => (
                        <TableRow
                            key={run.id}
                            data-state={
                                run.id === selectedId ? 'selected' : undefined
                            }
                        >
                            <TableCell>
                                <Link
                                    href={routesFor[subject.type].index(
                                        subject.id,
                                        {
                                            query: { analysis: run.id },
                                        },
                                    )}
                                    preserveScroll
                                    className="font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                    aria-current={
                                        run.id === selectedId
                                            ? 'true'
                                            : undefined
                                    }
                                >
                                    {formatRelative(run.created_at)}
                                </Link>
                                {run.error && (
                                    <p className="line-clamp-1 max-w-md text-xs whitespace-normal text-muted-foreground">
                                        {run.error}
                                    </p>
                                )}
                            </TableCell>
                            <TableCell>
                                <RunStatusBadge
                                    status={run.status}
                                    label={run.status_label}
                                />
                            </TableCell>
                            <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                {run.source_url
                                    ? hostOf(run.source_url)
                                    : run.report
                                      ? 'description'
                                      : '—'}
                            </TableCell>
                            <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                {run.prompt_tokens === null
                                    ? '—'
                                    : (
                                          run.prompt_tokens +
                                          (run.completion_tokens ?? 0)
                                      ).toLocaleString()}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}

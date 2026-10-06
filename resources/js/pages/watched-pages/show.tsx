import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { ExternalLink, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import CreepRunController from '@/actions/App/Http/Controllers/CreepRunController';
import WatchedPageController from '@/actions/App/Http/Controllers/WatchedPageController';
import { CategoryField } from '@/components/creep/category-field';
import { ChangeList } from '@/components/creep/change-list';
import { PauseButton } from '@/components/creep/pause-button';
import {
    PageStatusBadge,
    RunStatusBadge,
} from '@/components/creep/status-badges';
import { WatchForField } from '@/components/creep/watch-for-field';
import {
    CheckField,
    EmptyLine,
    Field,
    FormActions,
    Page,
    Panel,
    PanelBar,
    ReceiptRow,
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    formatDateTime,
    formatDuration,
    formatRelative,
    hostOf,
} from '@/lib/format';
import { index as competitorsIndex } from '@/routes/businesses/competitors';
import { show as showCompetitor } from '@/routes/competitors';
import { show } from '@/routes/watched-pages';
import type {
    CreepChange,
    CreepRun,
    PageCategory,
    PageSnapshot,
    ResourceCollection,
    SelectOption,
    WatchedPage,
} from '@/types';

type Props = {
    watchedPage: { data: WatchedPage };
    runs: ResourceCollection<CreepRun>;
    changes: ResourceCollection<CreepChange>;
    frequencies: SelectOption[];
    categories: SelectOption[];
    apiKeys: SelectOption[];
    isCreeping: boolean;
};

export default function ShowWatchedPage({
    watchedPage: { data: watchedPage },
    runs,
    changes,
    frequencies,
    categories,
    apiKeys,
    isCreeping,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            ...(watchedPage.competitor
                ? [
                      {
                          title: 'Competitors',
                          href: competitorsIndex(
                              watchedPage.competitor.business_id,
                          ),
                      },
                      {
                          title: watchedPage.competitor.name,
                          href: showCompetitor(watchedPage.competitor.id),
                      },
                  ]
                : []),
            { title: watchedPage.display_name, href: show(watchedPage.id) },
        ],
    });

    const snapshot = watchedPage.latest_snapshot ?? null;
    const isPaused = watchedPage.status === 'paused';

    return (
        <>
            <Head title={watchedPage.display_name} />

            <Page>
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-2">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="display-dot text-2xl sm:text-3xl">
                                {watchedPage.display_name}
                            </h1>
                            <PageStatusBadge
                                status={watchedPage.status}
                                label={watchedPage.status_label}
                            />
                        </div>
                        <a
                            href={watchedPage.url}
                            target="_blank"
                            rel="noreferrer noopener"
                            className="inline-flex items-center gap-1.5 font-mono text-xs tracking-[0.04em] text-muted-foreground underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                        >
                            {hostOf(watchedPage.url)}
                            <ExternalLink aria-hidden className="size-3" />
                        </a>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <PauseButton watchedPage={watchedPage} />

                        <Form
                            {...CreepRunController.store.form(watchedPage.id)}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={
                                        processing || isCreeping || isPaused
                                    }
                                    title={
                                        isPaused
                                            ? 'Resume creeping first'
                                            : undefined
                                    }
                                >
                                    <RefreshCw
                                        aria-hidden
                                        className={
                                            isCreeping
                                                ? 'animate-spin'
                                                : undefined
                                        }
                                    />
                                    {isCreeping ? 'Creeping…' : 'Creep now'}
                                </Button>
                            )}
                        </Form>
                    </div>
                </header>

                {isPaused && (
                    <Alert variant="warning">
                        <AlertTitle>Creeping is paused</AlertTitle>
                        <AlertDescription>
                            <p>
                                Creeper is leaving this page alone. Everything
                                it has found so far — the readings, the run log
                                and the changes below — is all still here.
                                Resume whenever you want and it picks up where
                                it left off.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {watchedPage.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTitle>Parked</AlertTitle>
                        <AlertDescription>
                            <p>
                                Creeper failed{' '}
                                {watchedPage.consecutive_failures} times in a
                                row on this watched page, so it stopped trying.
                                Fix the URL, then resume it.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <Reading watchedPage={watchedPage} snapshot={snapshot} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>What has changed</CardTitle>
                            <CardDescription>
                                Facts that appeared, went or changed value.
                                Newest first.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ChangeList changes={changes.data} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Run log</CardTitle>
                            <CardDescription>
                                Every time Creeper went and looked.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <RunLog runs={runs.data} />
                        </CardContent>
                    </Card>
                </div>

                <SettingsCard
                    watchedPage={watchedPage}
                    frequencies={frequencies}
                    categories={categories}
                    apiKeys={apiKeys}
                />
            </Page>
        </>
    );
}

/**
 * What Creeper last read off the page: what it was asked to watch, its summary,
 * and every fact on a dotted leader, the way a receipt prints them.
 */
function Reading({
    watchedPage,
    snapshot,
}: {
    watchedPage: WatchedPage;
    snapshot: PageSnapshot | null;
}) {
    return (
        <Panel>
            <PanelBar
                title="Latest reading"
                meta={
                    snapshot
                        ? `as of ${formatRelative(snapshot.captured_at)}`
                        : undefined
                }
            />
            <div className="space-y-5 p-5">
                <div className="space-y-1">
                    <p className="label-micro text-muted-foreground">
                        Watching for
                    </p>
                    <p className="max-w-prose text-sm whitespace-pre-line">
                        {watchedPage.watch_for}
                    </p>
                </div>

                {snapshot === null ? (
                    <EmptyLine>
                        Not read yet. The first reading lands here.
                    </EmptyLine>
                ) : (
                    <>
                        <p className="max-w-prose text-base leading-relaxed">
                            {snapshot.summary}
                        </p>

                        {snapshot.facts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing on the page matched what you asked to
                                watch.
                            </p>
                        ) : (
                            <dl className="max-w-2xl space-y-2 text-sm">
                                {snapshot.facts.map((fact) => (
                                    <ReceiptRow
                                        key={fact.label}
                                        label={fact.label}
                                        value={fact.value}
                                        labelAs="dt"
                                        valueAs="dd"
                                    />
                                ))}
                            </dl>
                        )}
                    </>
                )}
            </div>
        </Panel>
    );
}

function RunLog({ runs }: { runs: CreepRun[] }) {
    if (runs.length === 0) {
        return <EmptyLine>No runs yet</EmptyLine>;
    }

    return (
        <ul className="divide-y divide-rule">
            {runs.map((run) => (
                <li key={run.id} className="py-2.5 first:pt-0 last:pb-0">
                    <div className="flex items-center justify-between gap-3">
                        <RunStatusBadge
                            status={run.status}
                            label={run.status_label}
                        />
                        <span className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground tabular-nums">
                            {formatRelative(run.started_at)}
                            <span aria-hidden> · </span>
                            {formatDuration(run.duration_ms)}
                        </span>
                    </div>
                    {run.error && (
                        <p className="mt-1.5 line-clamp-2 font-mono text-[0.6875rem] text-ribbon-red">
                            {run.error}
                        </p>
                    )}
                </li>
            ))}
        </ul>
    );
}

function SettingsCard({
    watchedPage,
    frequencies,
    categories,
    apiKeys,
}: {
    watchedPage: WatchedPage;
    frequencies: SelectOption[];
    categories: SelectOption[];
    apiKeys: SelectOption[];
}) {
    const [category, setCategory] = useState<PageCategory>(
        watchedPage.category,
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle>Settings</CardTitle>
                <CardDescription>
                    {watchedPage.next_creep_at
                        ? `Next creep ${formatDateTime(watchedPage.next_creep_at)}.`
                        : 'Nothing scheduled.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    {...WatchedPageController.update.form(watchedPage.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Page URL"
                                htmlFor="url"
                                error={errors.url}
                            >
                                <Input
                                    id="url"
                                    name="url"
                                    type="url"
                                    required
                                    className="font-mono text-sm"
                                    defaultValue={watchedPage.url}
                                />
                            </Field>

                            <WatchForField
                                defaultValue={watchedPage.watch_for}
                                error={errors.watch_for}
                                onPreset={(preset) =>
                                    setCategory(preset.category)
                                }
                            />

                            <CategoryField
                                categories={categories}
                                value={category}
                                onChange={setCategory}
                                error={errors.category}
                            />

                            <Field
                                label="Name"
                                htmlFor="name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={watchedPage.name ?? ''}
                                />
                            </Field>

                            <Field
                                label="Schedule"
                                htmlFor="frequency"
                                error={errors.frequency}
                            >
                                <Select
                                    name="frequency"
                                    defaultValue={watchedPage.frequency}
                                >
                                    <SelectTrigger
                                        id="frequency"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {frequencies.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>

                            <Field
                                label="API key"
                                htmlFor="api_key_id"
                                error={errors.api_key_id}
                                hint={
                                    watchedPage.api_key_id === null
                                        ? 'The key this watched page used has been removed. Choose another before it can be crept again.'
                                        : 'Creeper spends this key every time it reads the page.'
                                }
                            >
                                <Select
                                    name="api_key_id"
                                    defaultValue={
                                        watchedPage.api_key_id ?? undefined
                                    }
                                >
                                    <SelectTrigger
                                        id="api_key_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pick a key" />
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
                            </Field>

                            <CheckField
                                htmlFor="notify_on_change"
                                label="Email me when something changes"
                                control={
                                    <Checkbox
                                        id="notify_on_change"
                                        name="notify_on_change"
                                        value="1"
                                        defaultChecked={
                                            watchedPage.notify_on_change
                                        }
                                    />
                                }
                            />

                            <FormActions
                                aside={<DeletePage watchedPage={watchedPage} />}
                            >
                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function DeletePage({ watchedPage }: { watchedPage: WatchedPage }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" className="hover:text-ribbon-red">
                    Delete watched page
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {watchedPage.display_name}?</DialogTitle>
                <DialogDescription>
                    Everything Creeper found — every reading, the run log, the
                    changes — goes with it. This cannot be undone. To stop
                    creeping without losing any of it, pause the watched page
                    instead.
                </DialogDescription>

                <Form {...WatchedPageController.destroy.form(watchedPage.id)}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete for good
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

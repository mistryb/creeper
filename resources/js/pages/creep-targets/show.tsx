import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { ExternalLink, RefreshCw, Star } from 'lucide-react';
import CreepRunController from '@/actions/App/Http/Controllers/CreepRunController';
import CreepTargetController from '@/actions/App/Http/Controllers/CreepTargetController';
import { ChangeList } from '@/components/creep/change-list';
import { PauseButton } from '@/components/creep/pause-button';
import { PriceHistoryChart } from '@/components/creep/price-history-chart';
import { ReleaseList } from '@/components/creep/release-list';
import {
    AvailabilityBadge,
    RunStatusBadge,
    TargetStatusBadge,
} from '@/components/creep/status-badges';
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
import { creepTypeCopy } from '@/lib/creep-types';
import type { CreepTypeCopy } from '@/lib/creep-types';
import {
    formatDateTime,
    formatDuration,
    formatPrice,
    formatRelative,
    hostOf,
} from '@/lib/format';
import { index, show } from '@/routes/creep-targets';
import type {
    ChangelogSnapshot,
    CreepChange,
    CreepRun,
    CreepTarget,
    ProductSnapshot,
    ResourceCollection,
    SelectOption,
} from '@/types';

type Props = {
    target: { data: CreepTarget };
    history: ResourceCollection<ProductSnapshot>;
    runs: ResourceCollection<CreepRun>;
    changes: ResourceCollection<CreepChange>;
    frequencies: SelectOption[];
    isCreeping: boolean;
};

export default function ShowCreepTarget({
    target: { data: target },
    history,
    runs,
    changes,
    frequencies,
    isCreeping,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Creep targets', href: index() },
            { title: target.display_name, href: show(target.id) },
        ],
    });

    // A target reads as whatever it was pointed at: one of these is the
    // reading, and which one never changes for the life of the target.
    const snapshot = target.latest_snapshot ?? null;
    const changelog = target.latest_changelog_snapshot ?? null;
    const copy = creepTypeCopy(target.type);
    const isPaused = target.status === 'paused';

    return (
        <>
            <Head title={target.display_name} />

            <Page>
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-2">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="display-dot text-2xl sm:text-3xl">
                                {snapshot?.title ??
                                    changelog?.product ??
                                    target.display_name}
                            </h1>
                            <TargetStatusBadge
                                status={target.status}
                                label={target.status_label}
                            />
                        </div>
                        <a
                            href={target.url}
                            target="_blank"
                            rel="noreferrer noopener"
                            className="inline-flex items-center gap-1.5 font-mono text-xs tracking-[0.04em] text-muted-foreground underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                        >
                            {hostOf(target.url)}
                            <ExternalLink aria-hidden className="size-3" />
                        </a>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <PauseButton target={target} />

                        <Form {...CreepRunController.store.form(target.id)}>
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
                                it has found so far — the history, the run log
                                and the changes below — is all still here.
                                Resume whenever you want and it picks up where
                                it left off.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {target.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTitle>Parked</AlertTitle>
                        <AlertDescription>
                            <p>
                                Creeper failed {target.consecutive_failures}{' '}
                                times in a row on this target, so it stopped
                                trying. Fix the URL, then resume it.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {target.type === 'changelog' ? (
                        <>
                            <ChangelogPanel snapshot={changelog} copy={copy} />

                            <Panel className="lg:col-span-2" lifted={false}>
                                <PanelBar
                                    title="Releases"
                                    meta={
                                        changelog
                                            ? `as of ${formatRelative(changelog.captured_at)}`
                                            : undefined
                                    }
                                />
                                <div className="p-5">
                                    <ReleaseList
                                        releases={changelog?.releases ?? []}
                                    />
                                </div>
                            </Panel>
                        </>
                    ) : (
                        <>
                            <ProductPanel snapshot={snapshot} copy={copy} />

                            <Panel className="lg:col-span-2" lifted={false}>
                                <PanelBar
                                    title="Price history"
                                    meta={
                                        snapshot
                                            ? `as of ${formatRelative(snapshot.captured_at)}`
                                            : undefined
                                    }
                                />
                                <div className="p-5">
                                    <PriceHistoryChart
                                        snapshots={history.data}
                                        currency={snapshot?.currency ?? null}
                                    />
                                </div>
                            </Panel>
                        </>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>What has changed</CardTitle>
                            <CardDescription>
                                {copy.changeHint} Newest first.
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
                    target={target}
                    frequencies={frequencies}
                    copy={copy}
                />
            </Page>
        </>
    );
}

/**
 * What Creeper last read off the page, laid out as a receipt: the price large
 * in dot-matrix, the details on dotted leaders under it.
 */
function ProductPanel({
    snapshot,
    copy,
}: {
    snapshot: ProductSnapshot | null;
    copy: CreepTypeCopy;
}) {
    if (!snapshot) {
        return <NotReadYet copy={copy} />;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{copy.readingTitle}</CardTitle>
                <CardDescription>
                    As of {formatRelative(snapshot.captured_at)}.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {snapshot.image_url && (
                    <img
                        src={snapshot.image_url}
                        alt=""
                        className="aspect-video w-full border border-rule object-cover"
                        loading="lazy"
                    />
                )}

                <p className="numeral-dot text-5xl">
                    {formatPrice(snapshot.price_amount, snapshot.currency)}
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    <AvailabilityBadge
                        availability={snapshot.availability}
                        label={snapshot.availability_label}
                    />
                    {snapshot.rating !== null && (
                        <span className="inline-flex items-center gap-1 font-mono text-xs text-muted-foreground">
                            <Star aria-hidden className="size-3.5" />
                            {snapshot.rating.toFixed(1)}
                            {snapshot.review_count !== null && (
                                <> ({snapshot.review_count.toLocaleString()})</>
                            )}
                        </span>
                    )}
                </div>

                {(snapshot.brand || snapshot.sku) && (
                    <dl className="space-y-2 border-t border-dashed border-rule pt-4 font-mono text-xs">
                        {snapshot.brand && (
                            <ReceiptRow
                                labelAs="dt"
                                valueAs="dd"
                                label="Brand"
                                value={snapshot.brand}
                            />
                        )}
                        {snapshot.sku && (
                            <ReceiptRow
                                labelAs="dt"
                                valueAs="dd"
                                label="SKU"
                                value={snapshot.sku}
                            />
                        )}
                    </dl>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * What the changelog last said, laid out as the same receipt: the version
 * large in dot-matrix where a price would be, the counts on dotted leaders.
 */
function ChangelogPanel({
    snapshot,
    copy,
}: {
    snapshot: ChangelogSnapshot | null;
    copy: CreepTypeCopy;
}) {
    if (!snapshot) {
        return <NotReadYet copy={copy} />;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{copy.readingTitle}</CardTitle>
                <CardDescription>
                    As of {formatRelative(snapshot.captured_at)}.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <p className="numeral-dot text-4xl break-all">
                    {snapshot.latest_version ?? '—'}
                </p>

                <dl className="space-y-2 border-t border-dashed border-rule pt-4 font-mono text-xs">
                    {snapshot.product && (
                        <ReceiptRow
                            labelAs="dt"
                            valueAs="dd"
                            label="Product"
                            value={snapshot.product}
                        />
                    )}
                    {snapshot.latest_released_on && (
                        <ReceiptRow
                            labelAs="dt"
                            valueAs="dd"
                            label="Released"
                            value={snapshot.latest_released_on}
                        />
                    )}
                    <ReceiptRow
                        labelAs="dt"
                        valueAs="dd"
                        label="Releases on page"
                        value={snapshot.release_count.toLocaleString()}
                    />
                    <ReceiptRow
                        labelAs="dt"
                        valueAs="dd"
                        label="Features listed"
                        value={snapshot.feature_count.toLocaleString()}
                    />
                </dl>
            </CardContent>
        </Card>
    );
}

/** The first creep hasn't landed yet. */
function NotReadYet({ copy }: { copy: CreepTypeCopy }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{copy.readingTitle}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">{copy.waiting}</p>
            </CardContent>
        </Card>
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
    target,
    frequencies,
    copy,
}: {
    target: CreepTarget;
    frequencies: SelectOption[];
    copy: CreepTypeCopy;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Settings</CardTitle>
                <CardDescription>
                    {target.next_creep_at
                        ? `Next creep ${formatDateTime(target.next_creep_at)}.`
                        : 'Nothing scheduled.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    {...CreepTargetController.update.form(target.id)}
                    options={{ preserveScroll: true }}
                    className="max-w-xl space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={copy.urlLabel}
                                htmlFor="url"
                                error={errors.url}
                            >
                                <Input
                                    id="url"
                                    name="url"
                                    type="url"
                                    required
                                    className="font-mono text-sm"
                                    defaultValue={target.url}
                                />
                            </Field>

                            <Field
                                label="Name"
                                htmlFor="name"
                                error={errors.name}
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={target.name ?? ''}
                                />
                            </Field>

                            <Field
                                label="Schedule"
                                htmlFor="frequency"
                                error={errors.frequency}
                            >
                                <Select
                                    name="frequency"
                                    defaultValue={target.frequency}
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

                            <CheckField
                                htmlFor="notify_on_change"
                                label="Email me when something changes"
                                control={
                                    <Checkbox
                                        id="notify_on_change"
                                        name="notify_on_change"
                                        value="1"
                                        defaultChecked={target.notify_on_change}
                                    />
                                }
                            />

                            <FormActions
                                aside={<DeleteTarget target={target} />}
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

function DeleteTarget({ target }: { target: CreepTarget }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" className="hover:text-ribbon-red">
                    Delete target
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {target.display_name}?</DialogTitle>
                <DialogDescription>
                    Everything Creeper found — the price history, the run log,
                    the changes — goes with it. This cannot be undone. To stop
                    creeping without losing any of it, pause the target instead.
                </DialogDescription>

                <Form {...CreepTargetController.destroy.form(target.id)}>
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

import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { ExternalLink, RefreshCw, Star } from 'lucide-react';
import CreepRunController from '@/actions/App/Http/Controllers/CreepRunController';
import CreepTargetController from '@/actions/App/Http/Controllers/CreepTargetController';
import { ChangeList } from '@/components/creep/change-list';
import { PriceHistoryChart } from '@/components/creep/price-history-chart';
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
import {
    formatDateTime,
    formatDuration,
    formatPrice,
    formatRelative,
    hostOf,
} from '@/lib/format';
import { index, show } from '@/routes/creep-targets';
import type {
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
    statuses: SelectOption[];
    isCreeping: boolean;
};

export default function ShowCreepTarget({
    target: { data: target },
    history,
    runs,
    changes,
    frequencies,
    statuses,
    isCreeping,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Creep targets', href: index() },
            { title: target.display_name, href: show(target.id) },
        ],
    });

    const snapshot = target.latest_snapshot ?? null;

    return (
        <>
            <Head title={target.display_name} />

            <Page>
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-2">
                        <div className="flex flex-wrap items-center gap-2.5">
                            <h1 className="display-dot text-2xl sm:text-3xl">
                                {snapshot?.title ?? target.display_name}
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

                    <Form {...CreepRunController.store.form(target.id)}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={processing || isCreeping}
                            >
                                <RefreshCw
                                    aria-hidden
                                    className={
                                        isCreeping ? 'animate-spin' : undefined
                                    }
                                />
                                {isCreeping ? 'Creeping…' : 'Creep now'}
                            </Button>
                        )}
                    </Form>
                </header>

                {target.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTitle>Parked</AlertTitle>
                        <AlertDescription>
                            <p>
                                Creeper failed {target.consecutive_failures}{' '}
                                times in a row on this target. Fix the URL, or
                                set it back to active below to try again.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <ProductPanel snapshot={snapshot} />

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
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>What has changed</CardTitle>
                            <CardDescription>
                                Price moves and stock flips, newest first.
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
                    statuses={statuses}
                />
            </Page>
        </>
    );
}

/**
 * What Creeper last read off the page, laid out as a receipt: the price large
 * in dot-matrix, the details on dotted leaders under it.
 */
function ProductPanel({ snapshot }: { snapshot: ProductSnapshot | null }) {
    if (!snapshot) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>The product</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        Creeper hasn't managed to read this page yet. The first
                        result usually lands within a minute.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>The product</CardTitle>
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
    statuses,
}: {
    target: CreepTarget;
    frequencies: SelectOption[];
    statuses: SelectOption[];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Settings</CardTitle>
                <CardDescription>
                    Next creep {formatDateTime(target.next_creep_at)}.
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
                                label="Product URL"
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

                            <div className="grid gap-5 sm:grid-cols-2">
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

                                <Field
                                    label="Status"
                                    htmlFor="status"
                                    error={errors.status}
                                >
                                    <Select
                                        name="status"
                                        defaultValue={
                                            target.status === 'failed'
                                                ? 'active'
                                                : target.status
                                        }
                                    >
                                        <SelectTrigger
                                            id="status"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((option) => (
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
                            </div>

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
                    Stop creeping
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Stop creeping {target.display_name}?</DialogTitle>
                <DialogDescription>
                    Everything Creeper found — the price history, the run log,
                    the changes — goes with it. This cannot be undone.
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
                                Delete target
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

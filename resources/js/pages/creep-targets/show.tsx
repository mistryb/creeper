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
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
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

            <div className="space-y-6 px-4 py-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold tracking-tight">
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
                            className="inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >
                            {hostOf(target.url)}
                            <ExternalLink aria-hidden className="size-3" />
                        </a>
                    </div>

                    <Form {...CreepRunController.store.form(target.id)}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
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
                    <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm">
                        <p className="font-medium text-destructive">
                            Creeper has parked this target.
                        </p>
                        <p className="mt-0.5 text-muted-foreground">
                            It failed {target.consecutive_failures} times in a
                            row. Fix the URL or set it back to active below to
                            try again.
                        </p>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <ProductCard snapshot={snapshot} />

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Price history</CardTitle>
                            <CardDescription>
                                Every price Creeper has seen in the last 90
                                days.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <PriceHistoryChart
                                snapshots={history.data}
                                currency={snapshot?.currency ?? null}
                            />
                        </CardContent>
                    </Card>
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
            </div>
        </>
    );
}

function ProductCard({ snapshot }: { snapshot: ProductSnapshot | null }) {
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
                        className="aspect-video w-full rounded-lg border border-border object-cover"
                        loading="lazy"
                    />
                )}

                <p className="text-3xl font-semibold tabular-nums">
                    {formatPrice(snapshot.price_amount, snapshot.currency)}
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    <AvailabilityBadge
                        availability={snapshot.availability}
                        label={snapshot.availability_label}
                    />
                    {snapshot.rating !== null && (
                        <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                            <Star aria-hidden className="size-3.5" />
                            {snapshot.rating.toFixed(1)}
                            {snapshot.review_count !== null && (
                                <> ({snapshot.review_count.toLocaleString()})</>
                            )}
                        </span>
                    )}
                </div>

                <dl className="space-y-1 text-sm">
                    {snapshot.brand && (
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">Brand</dt>
                            <dd className="truncate">{snapshot.brand}</dd>
                        </div>
                    )}
                    {snapshot.sku && (
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">SKU</dt>
                            <dd className="truncate font-mono text-xs">
                                {snapshot.sku}
                            </dd>
                        </div>
                    )}
                </dl>
            </CardContent>
        </Card>
    );
}

function RunLog({ runs }: { runs: CreepRun[] }) {
    if (runs.length === 0) {
        return (
            <p className="py-6 text-center text-sm text-muted-foreground">
                No runs yet.
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border">
            {runs.map((run) => (
                <li key={run.id} className="py-3 first:pt-0 last:pb-0">
                    <div className="flex items-center justify-between gap-3">
                        <RunStatusBadge
                            status={run.status}
                            label={run.status_label}
                        />
                        <span className="text-xs text-muted-foreground">
                            {formatRelative(run.started_at)}
                            <span aria-hidden> · </span>
                            {formatDuration(run.duration_ms)}
                        </span>
                    </div>
                    {run.error && (
                        <p className="mt-1.5 line-clamp-2 text-xs text-muted-foreground">
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
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="url">Product URL</Label>
                                <Input
                                    id="url"
                                    name="url"
                                    type="url"
                                    required
                                    defaultValue={target.url}
                                />
                                <InputError message={errors.url} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={target.name ?? ''}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="frequency">Schedule</Label>
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
                                    <InputError message={errors.frequency} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
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
                                    <InputError message={errors.status} />
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <Checkbox
                                    id="notify_on_change"
                                    name="notify_on_change"
                                    value="1"
                                    defaultChecked={target.notify_on_change}
                                />
                                <Label
                                    htmlFor="notify_on_change"
                                    className="font-normal"
                                >
                                    Email me when something changes
                                </Label>
                            </div>

                            <div className="flex items-center justify-between gap-4 border-t border-border pt-6">
                                <Button type="submit" disabled={processing}>
                                    Save changes
                                </Button>

                                <DeleteTarget target={target} />
                            </div>
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
                <Button variant="ghost" className="text-destructive">
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

import { Head } from '@inertiajs/react';
import { Bug, Plus, RefreshCw } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ChangeList } from '@/components/creep/change-list';
import {
    AvailabilityBadge,
    RunStatusBadge,
    TargetStatusBadge,
} from '@/components/creep/status-badges';
import {
    Changed,
    CheckField,
    Chip,
    ChipRow,
    Display,
    Emitted,
    EmptyLine,
    EmptyState,
    Eyebrow,
    Field,
    FormActions,
    Meter,
    Ok,
    Panel,
    PanelBar,
    Prompt,
    ReceiptRow,
    SectionHeading,
    StatTile,
    Terminal,
} from '@/components/ds';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { CreepChange } from '@/types';

/**
 * The living style guide.
 *
 * Everything the design system offers, rendered once, with a note on when to
 * use it. If a new screen needs something that is not on this page, the thing
 * to do is add it to the system and then add it here — not to invent it inside
 * the screen.
 */

const PALETTE: { token: string; hex: string; note: string; ink?: boolean }[] = [
    { token: 'paper', hex: '#f5f4ec', note: 'The page ground' },
    { token: 'paper-lit', hex: '#fbfaf4', note: 'Cards, panels, popovers' },
    { token: 'greenbar', hex: '#e2ebdc', note: 'Zebra rows, title bars' },
    { token: 'rule', hex: '#c9cdc0', note: 'Hairlines and dividers' },
    {
        token: 'ink-soft',
        hex: '#5d6b62',
        note: 'Secondary text · 4.9:1',
        ink: true,
    },
    { token: 'ink', hex: '#14201a', note: 'Text, outlines, slabs', ink: true },
    {
        token: 'ribbon',
        hex: '#1c6547',
        note: 'Primary, positive · 6.2:1',
        ink: true,
    },
    {
        token: 'ribbon-amber',
        hex: '#8f4e05',
        note: 'Attention, change · 5.7:1',
        ink: true,
    },
    {
        token: 'ribbon-red',
        hex: '#a1332a',
        note: 'Destructive, failed · 6.1:1',
        ink: true,
    },
    {
        token: 'ribbon-pale',
        hex: '#8fc4a8',
        note: 'Ribbon on ink · 8.3:1',
    },
];

const CHANGES: CreepChange[] = [
    {
        id: 1,
        field: 'price',
        old_value: '28900',
        new_value: '21900',
        direction: 'down',
        description: 'Price fell from $289.00 to $219.00',
        detected_at: '2026-08-20T09:14:00Z',
    },
    {
        id: 2,
        field: 'availability',
        old_value: 'out_of_stock',
        new_value: 'in_stock',
        direction: 'changed',
        description: 'Back in stock — 3 left',
        detected_at: '2026-08-19T17:02:00Z',
    },
];

function Section({
    id,
    title,
    note,
    children,
}: {
    id: string;
    title: string;
    note: string;
    children: ReactNode;
}) {
    return (
        <section id={id} className="scroll-mt-6 space-y-5">
            <SectionHeading as="h2" title={title} note={note} />
            {children}
        </section>
    );
}

/** A labelled specimen, so each example says what it is for. */
function Spec({
    label,
    use,
    children,
    className = '',
}: {
    label: string;
    use?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className="space-y-2">
            <p className="label-micro text-muted-foreground">{label}</p>
            <div
                className={`flex flex-wrap items-center gap-3 border border-dashed border-rule bg-card p-4 ${className}`}
            >
                {children}
            </div>
            {use && (
                <p className="max-w-prose text-xs text-muted-foreground">
                    {use}
                </p>
            )}
        </div>
    );
}

const SECTIONS = [
    ['palette', 'Palette'],
    ['type', 'Type'],
    ['surfaces', 'Surfaces'],
    ['buttons', 'Buttons'],
    ['badges', 'Badges & chips'],
    ['data', 'Data'],
    ['forms', 'Forms'],
    ['feedback', 'Feedback'],
    ['machine', 'Machine voice'],
] as const;

export default function DesignSystem() {
    return (
        <>
            <Head title="Design system" />

            <div className="min-h-screen bg-paper font-sans text-ink">
                <header className="sticky top-0 z-10 bg-ink text-paper-lit">
                    <div className="mx-auto flex min-h-10 w-full max-w-5xl flex-wrap items-center gap-x-5 gap-y-1 px-4 py-2 sm:px-10">
                        <span className="inline-flex items-center gap-2 label-micro text-[0.8125rem] font-semibold tracking-[0.22em]">
                            <AppLogoIcon className="size-4 text-ribbon-pale" />
                            Creeper
                        </span>
                        <nav className="flex flex-wrap items-center gap-x-4 gap-y-1 label-micro">
                            {SECTIONS.map(([id, label]) => (
                                <a
                                    key={id}
                                    href={`#${id}`}
                                    className="text-paper-lit/60 transition-colors hover:text-paper-lit"
                                >
                                    {label}
                                </a>
                            ))}
                        </nav>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-5xl space-y-16 px-4 py-10 sm:px-10 sm:py-16">
                    <div>
                        <Eyebrow>Design system · light only</Eyebrow>
                        <Display as="h1" size="lg" className="mt-4 mb-4">
                            Green-bar paper,
                            <br />
                            ribbon ink.
                        </Display>
                        <p className="max-w-[42rem] text-[1.0625rem] text-ink-soft">
                            Three rules hold this together.{' '}
                            <strong className="font-medium text-ink">
                                Nothing is round
                            </strong>{' '}
                            — the radius scale is zeroed, and only{' '}
                            <code className="font-mono text-[0.9em]">
                                rounded-full
                            </code>{' '}
                            survives.{' '}
                            <strong className="font-medium text-ink">
                                Shadows are hard offsets
                            </strong>
                            , never blurs: paper stacks, it does not float.{' '}
                            <strong className="font-medium text-ink">
                                Anything a machine printed is mono
                            </strong>{' '}
                            — labels, counts, prices, statuses — and anything a
                            person wrote is sans.
                        </p>
                    </div>

                    <Section
                        id="palette"
                        title="Palette"
                        note="Fixed light values — there is no dark theme"
                    >
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {PALETTE.map((swatch) => (
                                <div
                                    key={swatch.token}
                                    className="border border-rule bg-card"
                                >
                                    <div
                                        className="h-16 border-b border-rule"
                                        style={{ background: swatch.hex }}
                                    />
                                    <div className="px-3 py-2.5">
                                        <p className="label-mono">
                                            {swatch.token}
                                        </p>
                                        <p className="mt-1 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                            {swatch.hex}
                                            <span aria-hidden> · </span>
                                            {swatch.note}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <p className="max-w-prose text-sm text-ink-soft">
                            Contrast figures are against{' '}
                            <code className="font-mono">paper</code>, except{' '}
                            <code className="font-mono">ribbon-pale</code>,
                            which is measured on{' '}
                            <code className="font-mono">ink</code>. Every value
                            that can carry a word clears 4.5:1 on every surface
                            it is allowed on, so none of them is
                            decoration-only.
                        </p>
                    </Section>

                    <Section
                        id="type"
                        title="Type"
                        note="Four recipes, as utilities"
                    >
                        <div className="space-y-6 border border-rule bg-card p-5">
                            <div>
                                <p className="label-micro text-muted-foreground">
                                    .display-dot · Doto 800
                                </p>
                                <Display size="md" className="mt-2">
                                    Put a page on watch
                                </Display>
                                <p className="mt-2 max-w-prose text-xs text-muted-foreground">
                                    Headlines only. Doto is a display face and
                                    goes mushy below about 18px, so the scale
                                    stops at <code>size="sm"</code> and prose
                                    never uses it.
                                </p>
                            </div>

                            <div>
                                <p className="label-micro text-muted-foreground">
                                    .numeral-dot · tabular
                                </p>
                                <p className="mt-2 numeral-dot text-5xl">
                                    $219.00
                                </p>
                                <p className="mt-2 max-w-prose text-xs text-muted-foreground">
                                    A figure worth reading across a room: a
                                    total, a count, a price.
                                </p>
                            </div>

                            <div>
                                <p className="label-micro text-muted-foreground">
                                    .label-micro · 11px / 0.16em
                                </p>
                                <p className="mt-2 label-micro text-ribbon">
                                    Page watcher · bring your own api key
                                </p>
                                <p className="mt-2 max-w-prose text-xs text-muted-foreground">
                                    The overline, field captions, column heads,
                                    breadcrumbs. Never in running prose, and
                                    never the only place a fact appears.
                                </p>
                            </div>

                            <div>
                                <p className="label-micro text-muted-foreground">
                                    .label-mono · 13px / 0.08em
                                </p>
                                <p className="mt-2 label-mono uppercase">
                                    Point it at a page
                                </p>
                                <p className="mt-2 max-w-prose text-xs text-muted-foreground">
                                    A heavier stamp: step numbers, card titles,
                                    alert titles.
                                </p>
                            </div>

                            <div>
                                <p className="label-micro text-muted-foreground">
                                    Body · Instrument Sans
                                </p>
                                <p className="mt-2 max-w-prose text-ink-soft">
                                    You have tabs you keep reopening — a price
                                    you are waiting on, a listing that might
                                    come back, a page that changes when nobody
                                    is looking. Prose is sans, always, and this
                                    is where the product speaks in sentences.{' '}
                                    <TextLink href="#type">
                                        A link looks like this.
                                    </TextLink>
                                </p>
                            </div>
                        </div>
                    </Section>

                    <Section
                        id="surfaces"
                        title="Surfaces"
                        note="Panel for the loudest thing, Card for everything else"
                    >
                        <div className="grid gap-6 lg:grid-cols-2">
                            <div className="space-y-2">
                                <p className="label-micro text-muted-foreground">
                                    Panel · framed in ink, hard offset
                                </p>
                                <Panel>
                                    <PanelBar
                                        title="creeper"
                                        meta="watching 1 page"
                                    />
                                    <div className="p-5 text-sm text-ink-soft">
                                        For the one surface on a page that
                                        should be read first — a hero, a chart,
                                        a receipt. The title bar names what the
                                        panel is showing.
                                    </div>
                                </Panel>
                            </div>

                            <div className="space-y-2">
                                <p className="label-micro text-muted-foreground">
                                    Card · ruled off on paper
                                </p>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Recent changes</CardTitle>
                                        <CardDescription>
                                            What moved across everything you
                                            watch.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="text-sm text-ink-soft">
                                        The everyday container. A printed
                                        hairline separates the header, and the
                                        title is a stamped field name, so a page
                                        of cards reads like a filled-in form.
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <Spec
                            label="Empty states"
                            use="EmptyState for a whole surface; EmptyLine for one row inside a card, where the dashed frame would be too much."
                            className="block"
                        >
                            <div className="w-full space-y-4">
                                <EmptyState
                                    icon={Bug}
                                    title="Nothing to creep"
                                    actions={
                                        <Button>
                                            <Plus aria-hidden />
                                            Add your first target
                                        </Button>
                                    }
                                >
                                    Point Creeper at a product page and it will
                                    track the price and the stock.
                                </EmptyState>
                                <EmptyLine>No runs yet</EmptyLine>
                            </div>
                        </Spec>
                    </Section>

                    <Section
                        id="buttons"
                        title="Buttons"
                        note="A key on a machine — it moves when you press it"
                    >
                        <Spec
                            label="Variants"
                            use="default commits; secondary is the neutral action; outline is quieter still; ghost is for a nav row or a dialog escape; destructive deletes; link is a low-emphasis inline action — for a link inside a sentence use TextLink instead."
                        >
                            <Button>Creep it</Button>
                            <Button variant="secondary">
                                <RefreshCw aria-hidden />
                                Creep now
                            </Button>
                            <Button variant="outline">Previous</Button>
                            <Button variant="ghost">Stop creeping</Button>
                            <Button variant="destructive">Delete target</Button>
                            <Button variant="link">Manage subscription</Button>
                        </Spec>

                        <Spec label="Sizes and states">
                            <Button size="sm">Small</Button>
                            <Button>Default</Button>
                            <Button size="lg">Large</Button>
                            <Button size="icon" aria-label="Add">
                                <Plus aria-hidden />
                            </Button>
                            <Button disabled>Disabled</Button>
                        </Spec>
                    </Section>

                    <Section
                        id="badges"
                        title="Badges & chips"
                        note="A badge is state; a chip is configuration"
                    >
                        <Spec
                            label="Badge tones"
                            use="Always pair a tone with an icon and a word — the meaning has to survive greyscale."
                        >
                            <Badge variant="ok">ok</Badge>
                            <Badge variant="warn">attention</Badge>
                            <Badge variant="bad">failed</Badge>
                            <Badge variant="muted">unknown</Badge>
                            <Badge>default</Badge>
                            <Badge variant="secondary">secondary</Badge>
                            <Badge variant="outline">outline</Badge>
                        </Spec>

                        <Spec label="The app's status badges">
                            <TargetStatusBadge status="active" label="Active" />
                            <TargetStatusBadge status="paused" label="Paused" />
                            <TargetStatusBadge status="failed" label="Parked" />
                            <AvailabilityBadge
                                availability="in_stock"
                                label="In stock"
                            />
                            <AvailabilityBadge
                                availability="out_of_stock"
                                label="Out of stock"
                            />
                            <AvailabilityBadge
                                availability="preorder"
                                label="Preorder"
                            />
                            <RunStatusBadge status="running" label="Running" />
                            <RunStatusBadge
                                status="succeeded"
                                label="Succeeded"
                            />
                        </Spec>

                        <Spec
                            label="Chips"
                            use="What is being watched, what schedules are on offer. The lit state is an outline, not a fill, so a row stays readable as a set."
                        >
                            <ChipRow>
                                <Chip on>price</Chip>
                                <Chip on>availability</Chip>
                                <Chip>title</Chip>
                                <Chip>+ anything you name</Chip>
                            </ChipRow>
                        </Spec>
                    </Section>

                    <Section
                        id="data"
                        title="Data"
                        note="Tabular data is a table — that is where green-bar earns its keep"
                    >
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatTile label="Targets" value="12" />
                            <StatTile label="Active" value="11" tone="ribbon" />
                            <StatTile
                                label="Changes this week"
                                value="34"
                                note="up from 21"
                            />
                            <StatTile
                                label="Parked"
                                value="1"
                                tone="warn"
                                note="needs a look"
                            />
                        </div>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead scope="col">Target</TableHead>
                                    <TableHead scope="col">Price</TableHead>
                                    <TableHead scope="col">Stock</TableHead>
                                    <TableHead scope="col">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {[
                                    ['Mesa 2P tent', '$219.00', 'in_stock'],
                                    ['Kettle, 1.7L', '$41.50', 'out_of_stock'],
                                    ['Trail runners', '$128.00', 'preorder'],
                                ].map(([name, price, stock]) => (
                                    <TableRow key={name}>
                                        <TableCell className="font-medium">
                                            {name}
                                        </TableCell>
                                        <TableCell className="font-mono font-medium tabular-nums">
                                            {price}
                                        </TableCell>
                                        <TableCell>
                                            <AvailabilityBadge
                                                availability={
                                                    stock as 'in_stock'
                                                }
                                                label={stock.replace(/_/g, ' ')}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <TargetStatusBadge
                                                status="active"
                                                label="Active"
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="grid gap-6 lg:grid-cols-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Meter</CardTitle>
                                    <CardDescription>
                                        Turns amber at 85% and red once past —
                                        the figures say it too.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-5">
                                    <Meter
                                        label="Checks used"
                                        value={410}
                                        max={1500}
                                    />
                                    <Meter
                                        label="Checks used"
                                        value={1380}
                                        max={1500}
                                    />
                                    <Meter
                                        label="Checks used"
                                        value={1620}
                                        max={1500}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Receipt rows</CardTitle>
                                    <CardDescription>
                                        Key and value on a dotted leader.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3 font-mono text-[0.8125rem] tabular-nums">
                                    <ReceiptRow
                                        label="Pages watched"
                                        value="unlimited"
                                    />
                                    <ReceiptRow
                                        label="Checks included"
                                        value="1,500 / mo"
                                    />
                                    <ReceiptRow
                                        label="Model API key"
                                        value="yours"
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Change list</CardTitle>
                                <CardDescription>
                                    Direction gets an arrow and a label as well
                                    as a colour.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ChangeList changes={CHANGES} />
                            </CardContent>
                        </Card>

                        <Spec label="Skeleton" className="block">
                            <div className="w-full space-y-2">
                                <Skeleton className="h-4 w-1/3" />
                                <Skeleton className="h-4 w-2/3" />
                                <Skeleton className="h-4 w-1/2" />
                            </div>
                        </Spec>
                    </Section>

                    <Section
                        id="forms"
                        title="Forms"
                        note="Caption, control, hint, objection — always that order"
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>New target</CardTitle>
                                <CardDescription>
                                    Built from Field, CheckField and
                                    FormActions.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="max-w-xl space-y-5">
                                <Field
                                    label="Product URL"
                                    htmlFor="ds-url"
                                    hint="The page for a single product, not a search or category listing."
                                >
                                    <Input
                                        id="ds-url"
                                        className="font-mono text-sm"
                                        placeholder="https://example.com/products/kettle"
                                    />
                                </Field>

                                <Field label="Name" htmlFor="ds-name" optional>
                                    <Input
                                        id="ds-name"
                                        placeholder="What you want to call it"
                                    />
                                </Field>

                                <Field label="Schedule" htmlFor="ds-frequency">
                                    <Select defaultValue="daily">
                                        <SelectTrigger
                                            id="ds-frequency"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="hourly">
                                                Every hour
                                            </SelectItem>
                                            <SelectItem value="daily">
                                                Every day
                                            </SelectItem>
                                            <SelectItem value="weekly">
                                                Every week
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </Field>

                                <Field
                                    label="Rejected field"
                                    htmlFor="ds-bad"
                                    error="This does not look like a public URL."
                                >
                                    <Input
                                        id="ds-bad"
                                        aria-invalid
                                        defaultValue="localhost:8000"
                                        className="font-mono text-sm"
                                    />
                                </Field>

                                <div className="space-y-2">
                                    <p className="label-micro text-ink-soft">
                                        Six digit code
                                    </p>
                                    <InputOTP
                                        maxLength={6}
                                        value="4071"
                                        onChange={() => {}}
                                    >
                                        <InputOTPGroup>
                                            {[0, 1, 2, 3, 4, 5].map((slot) => (
                                                <InputOTPSlot
                                                    key={slot}
                                                    index={slot}
                                                />
                                            ))}
                                        </InputOTPGroup>
                                    </InputOTP>
                                    <p className="max-w-prose text-xs text-muted-foreground">
                                        Sign-in codes and address confirmations.
                                        Set in the dot-matrix face, because a
                                        code is a figure a machine printed and a
                                        person is copying back.
                                    </p>
                                </div>

                                <CheckField
                                    htmlFor="ds-notify"
                                    label="Email me when something changes"
                                    hint="Price moves and stock flips only — not review counts."
                                    control={
                                        <Checkbox
                                            id="ds-notify"
                                            defaultChecked
                                        />
                                    }
                                />

                                <FormActions
                                    aside={
                                        <Button variant="ghost">
                                            Stop creeping
                                        </Button>
                                    }
                                >
                                    <Button>Save changes</Button>
                                </FormActions>
                            </CardContent>
                        </Card>
                    </Section>

                    <Section
                        id="feedback"
                        title="Feedback"
                        note="Say it in words, tint it in ribbon"
                    >
                        <div className="space-y-3">
                            <Alert>
                                <AlertTitle>Heads up</AlertTitle>
                                <AlertDescription>
                                    <p>
                                        4 targets left on your plan. Nothing to
                                        do yet.
                                    </p>
                                </AlertDescription>
                            </Alert>

                            <Alert variant="warning">
                                <AlertTitle>Creeping is paused</AlertTitle>
                                <AlertDescription>
                                    <p>
                                        Your targets stay exactly as they are
                                        and pick back up as soon as a key is on
                                        file.
                                    </p>
                                </AlertDescription>
                            </Alert>

                            <Alert variant="destructive">
                                <AlertTitle>Parked</AlertTitle>
                                <AlertDescription>
                                    <p>
                                        Creeper failed 5 times in a row on this
                                        target. Fix the URL, or set it back to
                                        active to try again.
                                    </p>
                                </AlertDescription>
                            </Alert>
                        </div>

                        <Spec
                            label="Inline error"
                            use="Set in mono like the caption above the field, so a rejected field reads as the machine answering back."
                        >
                            <InputError message="This does not look like a public URL." />
                        </Spec>
                    </Section>

                    <Section
                        id="machine"
                        title="Machine voice"
                        note="Where Creeper speaks for itself"
                    >
                        <Panel lifted={false}>
                            <PanelBar title="run log" meta="run #4181" />
                            <div className="p-5">
                                <Terminal caret aria-label="Example run">
                                    <Prompt />
                                    creep shop.example.com/tents/mesa-2p --daily
                                    {'\n  reading page '}
                                    <Ok>................ ok</Ok>
                                    {'\n  asking claude '}
                                    <Ok>............... ok</Ok>
                                    {'\n'}
                                    <Changed>~ price</Changed>
                                    {'        '}
                                    <Emitted>$289.00</Emitted>
                                    {' → '}
                                    <Emitted>$219.00</Emitted>
                                    {'   '}
                                    <Changed>down 24%</Changed>
                                    {'\n'}
                                    <Changed>~ availability</Changed>{' '}
                                    <Emitted>out of stock</Emitted>
                                    {' → '}
                                    <Emitted>3 left</Emitted>
                                    {'\n'}
                                    <Ok>2 changes logged.</Ok>
                                    {' next visit in 24h.'}
                                </Terminal>
                            </div>
                        </Panel>
                        <p className="max-w-prose text-sm text-ink-soft">
                            <code className="font-mono">Terminal</code> is a{' '}
                            <code className="font-mono">&lt;pre&gt;</code>, so
                            the caller controls the line breaks and leading
                            spaces line up.{' '}
                            <code className="font-mono">Ok</code>,{' '}
                            <code className="font-mono">Changed</code> and{' '}
                            <code className="font-mono">Emitted</code> mark what
                            the machine printed, so the colours stay consistent
                            between the landing page and a real run log.
                        </p>
                    </Section>
                </main>

                <footer className="bg-ink text-paper-lit/55">
                    <div className="mx-auto flex min-h-12 w-full max-w-5xl items-center justify-between gap-4 px-4 label-micro sm:px-10">
                        <span>Creeper design system · local only</span>
                        <span>resources/js/components/ds</span>
                    </div>
                </footer>
            </div>
        </>
    );
}

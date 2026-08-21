import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    Changed,
    Chip,
    ChipRow,
    Display,
    Emitted,
    Eyebrow,
    Ok,
    Panel,
    PanelBar,
    ReceiptRow,
    SectionHeading,
    Terminal,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { create } from '@/routes/creep-targets';

/**
 * The example run that types itself out in the hero. Only the command line is
 * typed character by character; the results land whole, the way a real run
 * reports back.
 */
const COMMAND = '> creep shop.example.com/tents/mesa-2p --daily';

type OutputLine = {
    key: string;
    /** Milliseconds to wait after this line before printing the next one. */
    delay: number;
    node: ReactNode;
};

const OUTPUT_LINES: OutputLine[] = [
    {
        key: 'read',
        delay: 500,
        node: (
            <>
                {'  reading page '}
                <Ok>................ ok</Ok>
            </>
        ),
    },
    {
        key: 'ask',
        delay: 660,
        node: (
            <>
                {'  asking claude '}
                <Ok>............... ok</Ok>
            </>
        ),
    },
    {
        key: 'price',
        delay: 440,
        node: (
            <>
                <Changed>~ price</Changed>
                {'        '}
                <Emitted>$289.00</Emitted>
                {' → '}
                <Emitted>$219.00</Emitted>
                {'   '}
                <Changed>down 24%</Changed>
            </>
        ),
    },
    {
        key: 'stock',
        delay: 440,
        node: (
            <>
                <Changed>~ availability</Changed>{' '}
                <Emitted>out of stock</Emitted>
                {' → '}
                <Emitted>3 left</Emitted>
            </>
        ),
    },
    {
        key: 'done',
        delay: 0,
        node: (
            <>
                <Ok>2 changes logged.</Ok>
                {' next visit in 24h.'}
            </>
        ),
    },
];

/**
 * Types the command, then reveals each result line on a timer. Everything is
 * skipped straight to the finished state when the visitor asked for less
 * motion.
 */
function useExampleRun(): { typed: string; revealed: number } {
    const [typed, setTyped] = useState('');
    const [revealed, setRevealed] = useState(0);

    useEffect(() => {
        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        // Someone who asked for less motion gets the finished run instead of
        // the performance, settled on the next tick so hydration still matches
        // the empty output the server rendered.
        if (reducedMotion) {
            const settle = setTimeout(() => {
                setTyped(COMMAND);
                setRevealed(OUTPUT_LINES.length);
            });

            return () => clearTimeout(settle);
        }

        let timer: ReturnType<typeof setTimeout>;
        let characters = 0;
        let lines = 0;

        const revealNextLine = (): void => {
            if (lines >= OUTPUT_LINES.length) {
                return;
            }

            lines += 1;
            setRevealed(lines);
            timer = setTimeout(revealNextLine, OUTPUT_LINES[lines - 1].delay);
        };

        const typeNextCharacter = (): void => {
            characters += 1;
            setTyped(COMMAND.slice(0, characters));

            timer =
                characters < COMMAND.length
                    ? setTimeout(typeNextCharacter, 28)
                    : setTimeout(revealNextLine, 420);
        };

        timer = setTimeout(typeNextCharacter, 600);

        return () => clearTimeout(timer);
    }, []);

    return { typed, revealed };
}

function SetupStep({
    step,
    heading,
    children,
    chips,
}: {
    step: string;
    heading: string;
    children: ReactNode;
    chips: ReactNode;
}) {
    return (
        <li className="grid grid-cols-[3.25rem_1fr] gap-3 border-t border-rule px-3.5 py-5 first:border-t-0 odd:bg-greenbar sm:gap-7 sm:px-6 sm:py-6">
            <span className="pt-0.5 label-mono text-ribbon">{step}</span>
            <div>
                <h3 className="mb-1.5 font-mono text-[0.9375rem] font-semibold tracking-[0.14em] uppercase">
                    {heading}
                </h3>
                <p className="max-w-[40rem] text-[0.9375rem] text-ink-soft">
                    {children}
                </p>
                <ChipRow className="mt-3.5">{chips}</ChipRow>
            </div>
        </li>
    );
}

type Pricing = {
    amount: string;
    period: string;
    included_runs: number;
    overage: string;
};

export default function Welcome({ pricing }: { pricing: Pricing }) {
    const { auth } = usePage().props;
    const { typed, revealed } = useExampleRun();
    const includedRuns = pricing.included_runs.toLocaleString();

    const signUpHref = auth.user ? create() : register();

    const startWatching = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        router.visit(signUpHref);
    };

    return (
        <>
            <Head title="Put a page on watch" />

            <div className="min-h-screen bg-paper font-sans text-ink">
                <header className="bg-ink text-paper-lit">
                    <div className="mx-auto flex min-h-10 w-full max-w-5xl items-center justify-between gap-4 px-4 sm:px-10">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 label-micro text-[0.8125rem] font-semibold tracking-[0.22em]"
                        >
                            <AppLogoIcon className="size-4" />
                            Creeper
                        </Link>

                        <nav className="flex items-center gap-3 label-micro sm:gap-7">
                            <a
                                href="#setup"
                                className="hidden text-paper-lit/70 transition-colors hover:text-paper-lit sm:inline"
                            >
                                How it works
                            </a>
                            <a
                                href="#price"
                                className="hidden text-paper-lit/70 transition-colors hover:text-paper-lit sm:inline"
                            >
                                Price
                            </a>
                            <Link
                                href={auth.user ? dashboard() : login()}
                                className="bg-paper-lit px-3 py-1.5 text-ink transition-colors hover:bg-white"
                            >
                                {auth.user ? 'Dashboard' : 'Log in'}
                            </Link>
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto w-full max-w-5xl px-4 pt-8 pb-10 sm:px-10 sm:pt-16 sm:pb-20">
                        <Panel>
                            <PanelBar title="creeper" meta="watching 1 page" />

                            <div className="p-6 sm:p-12">
                                <Eyebrow className="mb-5 flex flex-wrap items-center gap-2.5">
                                    <span>Page watcher</span>
                                    <span className="text-rule">/</span>
                                    <span>Bring your own API key</span>
                                </Eyebrow>

                                <Display
                                    as="h1"
                                    size="hero"
                                    className="mb-5 text-balance"
                                >
                                    Put a page
                                    <br />
                                    <span className="text-ribbon">
                                        on watch.
                                    </span>
                                </Display>

                                <p className="mb-8 max-w-[40rem] text-[1.0625rem] text-ink-soft">
                                    You have tabs you keep reopening — a price
                                    you are waiting on, a listing that might
                                    come back, a page that changes when nobody
                                    is looking.{' '}
                                    <strong className="font-medium text-ink">
                                        Creeper reads them for you
                                    </strong>{' '}
                                    and speaks up the moment something moves.
                                </p>

                                <form
                                    onSubmit={startWatching}
                                    className="flex max-w-[34rem] flex-wrap gap-2"
                                >
                                    <label className="flex flex-1 basis-60 items-center gap-2 border border-ink bg-white px-3 font-mono focus-within:shadow-[inset_0_0_0_1px_var(--color-ink)]">
                                        <span
                                            aria-hidden="true"
                                            className="text-ribbon"
                                        >
                                            &gt;
                                        </span>
                                        <input
                                            type="url"
                                            aria-label="Page to watch"
                                            placeholder="paste a link to watch"
                                            className="min-w-0 flex-1 border-0 bg-transparent py-2.5 text-sm text-ink placeholder:text-ink-soft/65 focus:outline-none"
                                        />
                                    </label>
                                    <Button type="submit" size="lg">
                                        Creep it
                                    </Button>
                                </form>

                                <Terminal
                                    aria-label="Example run"
                                    caret
                                    className="mt-7 min-h-46"
                                >
                                    {typed}
                                    {OUTPUT_LINES.slice(0, revealed).map(
                                        (line) => (
                                            <span key={line.key}>
                                                {'\n'}
                                                {line.node}
                                            </span>
                                        ),
                                    )}
                                </Terminal>
                            </div>
                        </Panel>
                    </section>

                    <section
                        id="setup"
                        className="mx-auto w-full max-w-5xl px-4 pb-12 sm:px-10 sm:pb-22"
                    >
                        <SectionHeading
                            as="h2"
                            className="mb-5"
                            title="Set it up once"
                            note="Three steps, then it is out of your hands"
                        />

                        <ol className="border border-ink">
                            <SetupStep
                                step="01"
                                heading="Point it at a page"
                                chips={
                                    <Chip>shop.example.com/tents/mesa-2p</Chip>
                                }
                            >
                                Any URL that renders in a browser. A product
                                page, a marketplace listing, a release page —
                                Creeper does not need an API on the other end.
                            </SetupStep>

                            <SetupStep
                                step="02"
                                heading="Say what you care about"
                                chips={
                                    <>
                                        <Chip on>price</Chip>
                                        <Chip on>availability</Chip>
                                        <Chip on>title</Chip>
                                        <Chip>+ anything you name</Chip>
                                    </>
                                }
                            >
                                Describe it in plain words. Creeper turns that
                                into fields it can pull on every visit, so you
                                get a tidy number back instead of a wall of
                                page.
                            </SetupStep>

                            <SetupStep
                                step="03"
                                heading="Then quit checking"
                                chips={
                                    <>
                                        <Chip>every hour</Chip>
                                        <Chip on>every day</Chip>
                                        <Chip>every week</Chip>
                                        <Chip>only when I ask</Chip>
                                    </>
                                }
                            >
                                Pick a rhythm and walk away. Creeper keeps the
                                history, draws the line, and only interrupts you
                                when a value actually changed. Each visit counts
                                as one check.
                            </SetupStep>
                        </ol>
                    </section>

                    <section id="price" className="bg-ink text-paper-lit">
                        <div className="mx-auto grid w-full max-w-5xl items-start gap-8 px-4 py-12 sm:px-10 sm:py-22 lg:grid-cols-[1fr_22rem] lg:gap-14">
                            <div>
                                <Eyebrow tone="pale">
                                    Pricing, all of it
                                </Eyebrow>

                                <Display
                                    size="lg"
                                    className="my-4 max-w-[16ch] text-balance"
                                >
                                    One plan. No tiers, no seats.
                                </Display>

                                <p className="max-w-[32rem] text-paper-lit/70">
                                    {pricing.amount} a month covers as many
                                    pages as you like, and {includedRuns} checks
                                    to spend across them. Past that, checks are{' '}
                                    {pricing.overage} each — so a quiet month
                                    costs {pricing.amount} and a busy one costs
                                    a little more.
                                </p>
                                <p className="mt-3.5 max-w-[32rem] text-paper-lit/70">
                                    The thinking runs on{' '}
                                    <strong className="font-medium text-paper-lit">
                                        your own API key
                                    </strong>{' '}
                                    — Claude, or whichever model you like. You
                                    paste it in once, you pay the model
                                    directly, and you can see exactly what each
                                    run cost you.
                                </p>
                            </div>

                            <div className="drop-shadow-[0_10px_22px_rgba(0,0,0,0.45)]">
                                <div className="receipt-paper space-y-3 bg-white px-6 py-9 font-mono text-[0.8125rem] text-ink tabular-nums">
                                    <div className="border-b border-dashed border-rule pb-3.5 text-center">
                                        <strong className="block font-semibold tracking-[0.22em]">
                                            CREEPER
                                        </strong>
                                        <span className="text-[0.6875rem] tracking-[0.1em] text-ink-soft">
                                            ONE LINE ITEM, EVERY MONTH
                                        </span>
                                    </div>

                                    <ReceiptRow
                                        label="Pages watched"
                                        value="unlimited"
                                    />
                                    <ReceiptRow
                                        label="Checks included"
                                        value={`${includedRuns} / mo`}
                                    />
                                    <ReceiptRow
                                        label="Extra checks"
                                        value={`${pricing.overage} each`}
                                    />
                                    <ReceiptRow
                                        label="Model API key"
                                        value="yours"
                                    />

                                    <div className="flex items-end justify-between gap-3 border-t border-dashed border-rule pt-4">
                                        <span className="label-micro text-ink-soft">
                                            Total due
                                        </span>
                                        <span>
                                            <span className="numeral-dot text-[2.9rem]">
                                                {pricing.amount}
                                            </span>{' '}
                                            <span className="text-[0.6875rem] tracking-[0.12em] text-ink-soft uppercase">
                                                / {pricing.period}
                                            </span>
                                        </span>
                                    </div>

                                    <Button
                                        asChild
                                        size="lg"
                                        className="w-full"
                                    >
                                        <Link href={signUpHref}>
                                            Start creeping
                                        </Link>
                                    </Button>

                                    <p className="text-center text-[0.625rem] tracking-[0.1em] text-ink-soft uppercase">
                                        Cancel any time · Usage billed monthly
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="bg-ink text-paper-lit/55">
                    <div className="mx-auto flex min-h-12 w-full max-w-5xl items-center justify-between gap-4 border-t border-paper-lit/20 px-4 label-micro sm:px-10">
                        <span>Creeper · a small tool for keeping tabs</span>
                        <Link
                            href={login()}
                            className="transition-colors hover:text-paper-lit"
                        >
                            Log in
                        </Link>
                    </div>
                </footer>
            </div>
        </>
    );
}

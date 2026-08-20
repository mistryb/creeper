import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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
                <span className="text-ribbon">................ ok</span>
            </>
        ),
    },
    {
        key: 'ask',
        delay: 660,
        node: (
            <>
                {'  asking claude '}
                <span className="text-ribbon">............... ok</span>
            </>
        ),
    },
    {
        key: 'price',
        delay: 440,
        node: (
            <>
                <span className="font-medium text-ribbon-amber">~ price</span>
                {'        '}
                <span className="font-medium text-ink">$289.00</span>
                {' → '}
                <span className="font-medium text-ink">$219.00</span>
                {'   '}
                <span className="font-medium text-ribbon-amber">down 24%</span>
            </>
        ),
    },
    {
        key: 'stock',
        delay: 440,
        node: (
            <>
                <span className="font-medium text-ribbon-amber">
                    ~ availability
                </span>{' '}
                <span className="font-medium text-ink">out of stock</span>
                {' → '}
                <span className="font-medium text-ink">3 left</span>
            </>
        ),
    },
    {
        key: 'done',
        delay: 0,
        node: (
            <>
                <span className="text-ribbon">2 changes logged.</span>
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
            <span className="pt-0.5 font-mono text-[0.8125rem] font-semibold tracking-[0.08em] text-ribbon">
                {step}
            </span>
            <div>
                <h3 className="mb-1.5 font-mono text-[0.9375rem] font-semibold tracking-[0.14em] uppercase">
                    {heading}
                </h3>
                <p className="max-w-[40rem] text-[0.9375rem] text-ink-soft">
                    {children}
                </p>
                <div className="mt-3.5 flex flex-wrap gap-1.5">{chips}</div>
            </div>
        </li>
    );
}

function Chip({ children, on = false }: { children: ReactNode; on?: boolean }) {
    return (
        <span
            className={
                on
                    ? 'border border-ribbon bg-white px-2 py-1 font-mono text-[0.6875rem] tracking-[0.08em] whitespace-nowrap text-ribbon'
                    : 'border border-rule bg-paper-lit px-2 py-1 font-mono text-[0.6875rem] tracking-[0.08em] whitespace-nowrap text-ink-soft'
            }
        >
            {children}
        </span>
    );
}

function ReceiptRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline gap-1.5 pt-3">
            <span>{label}</span>
            <span className="flex-1 -translate-y-1 border-b border-dotted border-rule" />
            <span className="text-ink-soft">{value}</span>
        </div>
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
                            className="inline-flex items-center gap-2 font-mono text-[0.8125rem] font-semibold tracking-[0.22em] uppercase"
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 16 16"
                                aria-hidden="true"
                                fill="currentColor"
                            >
                                <rect x="2" y="2" width="3" height="3" />
                                <rect x="7" y="2" width="3" height="3" />
                                <rect x="11" y="6" width="3" height="3" />
                                <rect x="6" y="6" width="3" height="3" />
                                <rect x="2" y="11" width="3" height="3" />
                                <rect x="7" y="11" width="3" height="3" />
                            </svg>
                            Creeper
                        </Link>

                        <nav className="flex items-center gap-3 font-mono text-[0.6875rem] font-medium tracking-[0.16em] uppercase sm:gap-7">
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
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="bg-paper-lit px-3 py-1.5 text-ink transition-colors hover:bg-white"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="bg-paper-lit px-3 py-1.5 text-ink transition-colors hover:bg-white"
                                >
                                    Log in
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="mx-auto w-full max-w-5xl px-4 pt-8 pb-10 sm:px-10 sm:pt-16 sm:pb-20">
                        <div className="border border-ink bg-paper-lit shadow-[6px_6px_0_rgba(20,32,26,0.12)]">
                            <div className="flex items-center gap-3 border-b border-ink bg-greenbar px-3.5 py-2 font-mono text-[0.6875rem] font-medium tracking-[0.16em] text-ribbon uppercase">
                                <span
                                    className="flex gap-1.5"
                                    aria-hidden="true"
                                >
                                    <i className="size-2 rounded-full border border-ribbon bg-ribbon" />
                                    <i className="size-2 rounded-full border border-ribbon" />
                                    <i className="size-2 rounded-full border border-ribbon" />
                                </span>
                                <span>creeper</span>
                                <span className="flex-1" />
                                <span>watching 1 page</span>
                            </div>

                            <div className="p-6 sm:p-12">
                                <p className="mb-5 flex flex-wrap items-center gap-2.5 font-mono text-[0.6875rem] font-medium tracking-[0.16em] text-ribbon uppercase">
                                    <span>Page watcher</span>
                                    <span className="text-rule">/</span>
                                    <span>Bring your own API key</span>
                                </p>

                                <h1 className="mb-5 font-dot text-[clamp(2.6rem,9.5vw,5.5rem)] leading-[0.92] font-extrabold tracking-[0.01em] text-balance uppercase">
                                    Put a page
                                    <br />
                                    <span className="text-ribbon">
                                        on watch.
                                    </span>
                                </h1>

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
                                    <button
                                        type="submit"
                                        className="cursor-pointer border border-ink bg-ribbon px-5 py-3 font-mono text-xs font-semibold tracking-[0.14em] text-white uppercase transition-colors hover:bg-ribbon-lit active:translate-y-px"
                                    >
                                        Creep it
                                    </button>
                                </form>

                                <pre
                                    aria-label="Example run"
                                    className="mt-7 min-h-46 overflow-x-auto border-t border-dashed border-rule px-4 pt-4 font-mono text-[0.8125rem] leading-[1.9] text-ink-soft tabular-nums"
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
                                    <span
                                        aria-hidden="true"
                                        className="caret ml-0.5 inline-block h-[1.05em] w-[0.55em] bg-ribbon [vertical-align:-0.2em]"
                                    />
                                </pre>
                            </div>
                        </div>
                    </section>

                    <section
                        id="setup"
                        className="mx-auto w-full max-w-5xl px-4 pb-12 sm:px-10 sm:pb-22"
                    >
                        <div className="mb-5 flex flex-wrap items-baseline justify-between gap-3">
                            <h2 className="font-dot text-[clamp(1.4rem,4vw,2.1rem)] leading-tight font-bold tracking-[0.02em] uppercase">
                                Set it up once
                            </h2>
                            <p className="font-mono text-[0.6875rem] font-medium tracking-[0.16em] text-ribbon uppercase">
                                Three steps, then it is out of your hands
                            </p>
                        </div>

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
                                <p className="font-mono text-[0.6875rem] font-medium tracking-[0.16em] text-[#8fc4a8] uppercase">
                                    Pricing, all of it
                                </p>

                                <h2 className="my-4 max-w-[16ch] font-dot text-[clamp(1.8rem,5vw,2.8rem)] leading-tight font-bold tracking-[0.02em] text-balance uppercase">
                                    One plan. No tiers, no seats.
                                </h2>

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
                                <div className="receipt-paper bg-white px-6 py-9 font-mono text-[0.8125rem] text-ink tabular-nums">
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

                                    <div className="mt-4 flex items-end justify-between gap-3 border-t border-dashed border-rule pt-4">
                                        <span className="font-mono text-[0.6875rem] font-medium tracking-[0.16em] text-ink-soft uppercase">
                                            Total due
                                        </span>
                                        <span>
                                            <span className="font-dot text-[2.9rem] leading-[0.85] font-extrabold tracking-[0.01em]">
                                                {pricing.amount}
                                            </span>{' '}
                                            <span className="text-[0.6875rem] tracking-[0.12em] text-ink-soft uppercase">
                                                / {pricing.period}
                                            </span>
                                        </span>
                                    </div>

                                    <Link
                                        href={signUpHref}
                                        className="mt-5 block cursor-pointer border border-ink bg-ribbon px-5 py-3 text-center font-mono text-xs font-semibold tracking-[0.14em] text-white uppercase transition-colors hover:bg-ribbon-lit"
                                    >
                                        Start creeping
                                    </Link>

                                    <p className="mt-3.5 text-center text-[0.625rem] tracking-[0.1em] text-ink-soft uppercase">
                                        Cancel any time · Usage billed monthly
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <footer className="bg-ink text-paper-lit/55">
                    <div className="mx-auto flex min-h-12 w-full max-w-5xl items-center justify-between gap-4 border-t border-paper-lit/20 px-4 font-mono text-[0.6875rem] font-medium tracking-[0.16em] uppercase sm:px-10">
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

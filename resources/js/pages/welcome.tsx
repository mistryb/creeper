import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
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
    SectionHeading,
    Terminal,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import MarketingLayout, { MarketingNavLink } from '@/layouts/marketing-layout';
import { REPOSITORY_URL } from '@/lib/links';
import { deploy, login } from '@/routes';
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

/**
 * One of the ways to run Creeper yourself: a numbered card on the ink slab,
 * carrying its own button. The children are the sentence and the button, in
 * that order, so an option reads as prose with a key at the bottom.
 */
function RunOption({
    step,
    heading,
    children,
}: {
    step: string;
    heading: string;
    children: ReactNode;
}) {
    return (
        <li className="border border-paper-lit/20 px-6 py-7">
            <p className="label-micro text-paper-lit/55">Option {step}</p>
            <h3 className="mt-2.5 mb-2 font-mono text-[0.9375rem] font-semibold tracking-[0.14em] uppercase">
                {heading}
            </h3>
            <div className="text-paper-lit/70">{children}</div>
        </li>
    );
}

export default function Welcome() {
    const { auth } = usePage().props;
    const { typed, revealed } = useExampleRun();

    const signUpHref = auth.user ? create() : login();

    const startWatching = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        router.visit(signUpHref);
    };

    return (
        <>
            <Head title="Put a page on watch" />

            <MarketingLayout
                nav={
                    <>
                        <MarketingNavLink href="#setup">
                            How it works
                        </MarketingNavLink>
                        <MarketingNavLink href="#start">
                            Ways to run
                        </MarketingNavLink>
                    </>
                }
            >
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
                                <span className="text-ribbon">on watch.</span>
                            </Display>

                            <p className="mb-8 max-w-[40rem] text-[1.0625rem] text-ink-soft">
                                You have tabs you keep reopening — a price you
                                are waiting on, a listing that might come back,
                                a page that changes when nobody is looking.{' '}
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
                                {OUTPUT_LINES.slice(0, revealed).map((line) => (
                                    <span key={line.key}>
                                        {'\n'}
                                        {line.node}
                                    </span>
                                ))}
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
                            chips={<Chip>shop.example.com/tents/mesa-2p</Chip>}
                        >
                            Any URL that renders in a browser. A product page, a
                            marketplace listing, a release page — Creeper does
                            not need an API on the other end.
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
                            Describe it in plain words. Creeper turns that into
                            fields it can pull on every visit, so you get a tidy
                            number back instead of a wall of page.
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
                            when a value actually changed. Each visit counts as
                            one check.
                        </SetupStep>
                    </ol>
                </section>

                <section id="start" className="bg-ink text-paper-lit">
                    <div className="mx-auto grid w-full max-w-5xl items-start gap-8 px-4 py-12 sm:px-10 sm:py-22 lg:grid-cols-[1fr_22rem] lg:gap-14">
                        <div>
                            <Eyebrow tone="pale">Two ways to run it</Eyebrow>

                            <Display
                                size="lg"
                                className="my-4 max-w-[16ch] text-balance"
                            >
                                One install. No tiers, no seats.
                            </Display>

                            <p className="max-w-[32rem] text-paper-lit/70">
                                Creeper is an install of your own — a box you
                                keep, or a Laravel Cloud account — watching as
                                many pages as you like, as often as you like.
                                Nothing is metered and nothing is capped.
                            </p>
                            <p className="mt-3.5 max-w-[32rem] text-paper-lit/70">
                                The thinking runs on{' '}
                                <strong className="font-medium text-paper-lit">
                                    your own API key
                                </strong>{' '}
                                — Claude, or whichever model you like. You paste
                                it in once, you pay the model directly, and you
                                can see exactly what each run cost you.
                            </p>
                        </div>

                        <ol className="grid gap-4">
                            <RunOption step="01" heading="Read the source">
                                Every part of Creeper is in one repository — the
                                drivers, the schedule, the schema. Clone it,
                                look it over, run it locally.
                                <Button
                                    asChild
                                    size="lg"
                                    variant="secondary"
                                    className="mt-5 w-full"
                                >
                                    <a
                                        href={REPOSITORY_URL}
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        View the repository
                                    </a>
                                </Button>
                            </RunOption>

                            <RunOption
                                step="02"
                                heading="Put it on Laravel Cloud"
                            >
                                Would rather not keep a box alive? Copy the
                                prompt, hand it to your agent, and it clones the
                                repository and deploys your own copy.
                                <Button
                                    asChild
                                    size="lg"
                                    className="mt-5 w-full"
                                >
                                    <Link href={deploy()}>
                                        Deploy on Laravel Cloud
                                    </Link>
                                </Button>
                            </RunOption>
                        </ol>
                    </div>
                </section>
            </MarketingLayout>
        </>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Cloud, Github } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
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
import { cn } from '@/lib/utils';
import { deploy } from '@/routes';

/**
 * The example run that types itself out in the hero. Only the command line is
 * typed character by character; the results land whole, the way a real run
 * reports back.
 */
const COMMAND = '> creeper digest acme.example.com --every-morning';

type OutputLine = {
    key: string;
    /** Milliseconds to wait after this line before printing the next one. */
    delay: number;
    node: ReactNode;
};

const OUTPUT_LINES: OutputLine[] = [
    {
        key: 'find',
        delay: 500,
        node: (
            <>
                {'  finding competitors '}
                <Ok>......... 6 found</Ok>
            </>
        ),
    },
    {
        key: 'search',
        delay: 660,
        node: (
            <>
                {'  searching the web '}
                <Ok>........... 12 searches</Ok>
            </>
        ),
    },
    {
        key: 'rival',
        delay: 440,
        node: (
            <>
                <Changed>~ rival.example.com</Changed>
                {'  cut '}
                <Emitted>pro</Emitted>
                {' to '}
                <Emitted>$39/mo</Emitted>
                {', 2 sources'}
            </>
        ),
    },
    {
        key: 'other',
        delay: 440,
        node: (
            <>
                <Changed>+ other.example.com</Changed>
                {'  raised a '}
                <Emitted>series B</Emitted>
                {', hiring in sales'}
            </>
        ),
    },
    {
        key: 'done',
        delay: 0,
        node: (
            <>
                <Ok>digest sent.</Ok>
                {' next one tomorrow morning.'}
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
 * The two ways to get Creeper running, offered in the hero and again at the
 * foot of the page: read the source, or deploy a copy on Laravel Cloud.
 */
function RunButtons({ className }: { className?: string }) {
    return (
        <div className={cn('flex flex-wrap gap-3', className)}>
            <Button asChild size="lg" variant="secondary">
                <a href={REPOSITORY_URL} target="_blank" rel="noreferrer">
                    <Github aria-hidden="true" />
                    View on GitHub
                </a>
            </Button>
            <Button asChild size="lg" variant="secondary">
                <Link href={deploy()}>
                    <Cloud aria-hidden="true" />
                    Deploy on Laravel Cloud
                </Link>
            </Button>
        </div>
    );
}

export default function Welcome() {
    const { typed, revealed } = useExampleRun();

    return (
        <>
            <Head title="A competitor digest in your inbox" />

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
                        <PanelBar
                            title="creeper"
                            meta="6 competitors · every morning"
                        />

                        <div className="p-6 sm:p-12">
                            <Eyebrow className="mb-5 flex flex-wrap items-center gap-2.5">
                                <span>Competitor digest</span>
                                <span className="text-rule">/</span>
                                <span>Bring your own API key</span>
                            </Eyebrow>

                            <Display
                                as="h1"
                                size="hero"
                                className="mb-5 text-balance"
                            >
                                Know what your rivals
                                <br />
                                <span className="text-ribbon">
                                    did this week.
                                </span>
                            </Display>

                            <p className="mb-8 max-w-[40rem] text-[1.0625rem] text-ink-soft">
                                Your competitors launch, reprice, raise money
                                and hire without telling you. You find out from
                                a customer, weeks later.{' '}
                                <strong className="font-medium text-ink">
                                    Creeper researches them for you
                                </strong>{' '}
                                and emails you one digest, with sources.
                            </p>

                            <RunButtons className="mb-7" />

                            <Terminal
                                aria-label="Example run"
                                caret
                                className="min-h-46"
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
                        note="Three steps, then it shows up in your inbox"
                    />

                    <ol className="border border-ink">
                        <SetupStep
                            step="01"
                            heading="Add your company"
                            chips={
                                <>
                                    <Chip>acme.example.com</Chip>
                                    <Chip on>every morning</Chip>
                                    <Chip>every Monday morning</Chip>
                                </>
                            }
                        >
                            A name, a website and a sentence on what you sell.
                            Pick how often you want to hear about it and which
                            of your API keys to spend. That is the whole form.
                        </SetupStep>

                        <SetupStep
                            step="02"
                            heading="Meet your competitors"
                            chips={
                                <>
                                    <Chip on>rival.example.com</Chip>
                                    <Chip on>other.example.com</Chip>
                                    <Chip>+ add your own</Chip>
                                </>
                            }
                        >
                            Creeper reads your homepage, searches the web and
                            comes back with a list of who you are up against.
                            Delete the ones that do not count, add the ones it
                            missed.
                        </SetupStep>

                        <SetupStep
                            step="03"
                            heading="Read the digest"
                            chips={
                                <>
                                    <Chip on>launches</Chip>
                                    <Chip on>pricing</Chip>
                                    <Chip on>funding</Chip>
                                    <Chip on>hires</Chip>
                                    <Chip on>campaigns</Chip>
                                </>
                            }
                        >
                            On schedule, Creeper researches what each competitor
                            has been doing and emails you one digest: a
                            headline, a section per competitor, and a link for
                            every claim. Nothing to check, nothing to log into.
                        </SetupStep>
                    </ol>
                </section>

                <section id="start" className="bg-ink text-paper-lit">
                    <div className="mx-auto w-full max-w-5xl px-4 py-12 sm:px-10 sm:py-22">
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
                                keep, or a Laravel Cloud account — covering as
                                many companies and competitors as you like.
                                Nothing is metered and nothing is capped.
                            </p>
                            <p className="mt-3.5 max-w-[32rem] text-paper-lit/70">
                                The thinking runs on{' '}
                                <strong className="font-medium text-paper-lit">
                                    your own API key
                                </strong>{' '}
                                — Anthropic, OpenAI, Gemini, Groq or OpenRouter.
                                You paste it in once, the research and the web
                                searches run on it, and you pay the provider
                                directly.
                            </p>

                            <RunButtons className="mt-7" />
                        </div>
                    </div>
                </section>
            </MarketingLayout>
        </>
    );
}

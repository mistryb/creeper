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
const COMMAND = '> creep rival.example.com/pricing --daily';

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
                <Changed>~ pro plan</Changed>
                {'     '}
                <Emitted>$49/mo</Emitted>
                {' → '}
                <Emitted>$39/mo</Emitted>
                {'   '}
                <Changed>down 20%</Changed>
            </>
        ),
    },
    {
        key: 'release',
        delay: 440,
        node: (
            <>
                <Changed>+ changelog</Changed>
                {'    '}
                <Emitted>v4.2</Emitted>
                {' shipped '}
                <Emitted>SSO for every plan</Emitted>
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
            <Head title="Keep tabs on your competitors" />

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
                            meta="watching 1 competitor"
                        />

                        <div className="p-6 sm:p-12">
                            <Eyebrow className="mb-5 flex flex-wrap items-center gap-2.5">
                                <span>Competitor watcher</span>
                                <span className="text-rule">/</span>
                                <span>Bring your own API key</span>
                            </Eyebrow>

                            <Display
                                as="h1"
                                size="hero"
                                className="mb-5 text-balance"
                            >
                                Keep tabs on
                                <br />
                                <span className="text-ribbon">
                                    the competition.
                                </span>
                            </Display>

                            <p className="mb-8 max-w-[40rem] text-[1.0625rem] text-ink-soft">
                                Your rivals change their prices, ship features
                                and rewrite their plans without telling you. You
                                find out from a customer, weeks later.{' '}
                                <strong className="font-medium text-ink">
                                    Creeper reads their pages for you
                                </strong>{' '}
                                and speaks up the moment something moves.
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
                        note="Three steps, then it is out of your hands"
                    />

                    <ol className="border border-ink">
                        <SetupStep
                            step="01"
                            heading="Point it at a rival"
                            chips={
                                <>
                                    <Chip>rival.example.com/pricing</Chip>
                                    <Chip>rival.example.com/changelog</Chip>
                                </>
                            }
                        >
                            Their pricing page, their changelog, a product they
                            sell next to yours — any URL that renders in a
                            browser. Creeper does not need their API, or their
                            permission.
                        </SetupStep>

                        <SetupStep
                            step="02"
                            heading="Say what you care about"
                            chips={
                                <>
                                    <Chip on>price</Chip>
                                    <Chip on>new releases</Chip>
                                    <Chip on>breaking changes</Chip>
                                    <Chip>+ anything you name</Chip>
                                </>
                            }
                        >
                            Describe it in plain words. Creeper turns that into
                            fields it can pull on every visit, so you get a
                            price or a release note back instead of a wall of
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
                            history of every move they make and only interrupts
                            you when something actually changed. Each visit
                            counts as one check.
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
                                keep, or a Laravel Cloud account — watching as
                                many competitors as you like, as often as you
                                like. Nothing is metered and nothing is capped.
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

                            <RunButtons className="mt-7" />
                        </div>
                    </div>
                </section>
            </MarketingLayout>
        </>
    );
}

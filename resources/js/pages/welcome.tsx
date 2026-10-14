import { Head, Link, usePage } from '@inertiajs/react';
import { Check, Cloud, Github } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Changed, Emitted, Eyebrow, Ok, Terminal } from '@/components/ds';
import { MarketingHeading, MarketingStep } from '@/components/marketing/type';
import { Button } from '@/components/ui/button';
import MarketingLayout, { MarketingNavLink } from '@/layouts/marketing-layout';
import { REPOSITORY_URL } from '@/lib/links';
import { navPill } from '@/lib/nav-pill';
import { cn } from '@/lib/utils';
import { deploy, login } from '@/routes';
import type { PlanOption } from '@/types';

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

/** The spans of time the hero headline cycles through, in order. */
const PERIODS = ['day', 'week', 'month', 'quarter', 'year'];

/**
 * Steps through PERIODS on a timer. Someone who asked for less motion keeps
 * the first one, so the headline still reads as a sentence.
 */
function useRotatingPeriod(): string {
    const [index, setIndex] = useState(0);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const timer = setInterval(
            () => setIndex((current) => (current + 1) % PERIODS.length),
            2200,
        );

        return () => clearInterval(timer);
    }, []);

    return PERIODS[index];
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

/** What every plan includes; the plans differ only in how many companies. */
const PLAN_INCLUDES = [
    'Competitors found for you',
    'Daily or weekly digests',
    'A source for every claim',
    'Your own API key, no markup',
];

/**
 * One plan on the landing page. The featured one is framed in ink with the
 * stamped offset shadow, and carries the filled button, so the eye lands on
 * it first.
 */
function PlanCard({
    plan,
    trialDays,
    featured,
}: {
    plan: PlanOption;
    trialDays: number;
    featured: boolean;
}) {
    return (
        <div
            className={cn(
                'relative flex flex-col border bg-card p-6 sm:p-7',
                featured ? 'border-ink shadow-stamp' : 'border-rule',
            )}
        >
            {featured && (
                <span className="absolute -top-3 left-6 bg-ink px-2 py-1 label-micro text-paper-lit">
                    Best value
                </span>
            )}

            <h3 className="font-medium">{plan.label}</h3>
            <p className="mt-1 text-[0.9375rem] text-ink-soft">
                {plan.businessLimit === null
                    ? 'Every company you run'
                    : plan.businessLimit === 1
                      ? 'One company'
                      : `Up to ${plan.businessLimit} companies`}
            </p>

            <p className="mt-6 flex items-baseline gap-1.5">
                <span className="text-5xl font-semibold tracking-[-0.04em] tabular-nums">
                    ${plan.price}
                </span>
                <span className="text-ink-soft">/ month</span>
            </p>

            <ul className="mt-6 flex-1 space-y-2.5 border-t border-rule pt-6 text-[0.9375rem]">
                {PLAN_INCLUDES.map((item) => (
                    <li key={item} className="flex gap-2.5">
                        <Check
                            aria-hidden="true"
                            className="mt-0.5 size-4 shrink-0 text-ribbon"
                        />
                        {item}
                    </li>
                ))}
            </ul>

            <Button
                asChild
                size="lg"
                variant={featured ? 'default' : 'secondary'}
                className="mt-7 w-full"
            >
                <Link href={login()}>Start {trialDays}-day free trial</Link>
            </Button>
        </div>
    );
}

type Props = {
    /** Null on a self-hosted install, which has nothing to sell. */
    plans: PlanOption[] | null;
    trialDays: number;
};

export default function Welcome({ plans, trialDays }: Props) {
    const { auth } = usePage().props;
    const { typed, revealed } = useExampleRun();
    const period = useRotatingPeriod();

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
                            {plans ? 'Pricing' : 'Ways to run'}
                        </MarketingNavLink>
                        {/*
                         * The homepage, unlike the other public pages, offers
                         * a way back in. Signed-in visitors get the layout's
                         * Dashboard pill in this spot instead.
                         */}
                        {!auth.user && (
                            <Link
                                href={login()}
                                className={cn(
                                    navPill,
                                    'border-ink bg-ink text-paper-lit hover:bg-ink-lift',
                                )}
                            >
                                Log in
                            </Link>
                        )}
                    </>
                }
            >
                <section className="mx-auto w-full max-w-3xl px-4 pt-14 pb-16 sm:px-10 sm:pt-20 sm:pb-24">
                    <Eyebrow className="mb-6">Always be creeping</Eyebrow>

                    <MarketingHeading size="hero" className="mb-6">
                        Know what your rivals are doing
                        <br />
                        <span className="text-ribbon">
                            this{' '}
                            <span
                                key={period}
                                className="inline-block animate-in duration-500 fade-in slide-in-from-bottom-3 motion-reduce:animate-none"
                            >
                                {period}
                            </span>
                            .
                        </span>
                    </MarketingHeading>

                    <p className="mb-10 max-w-[34rem] text-[1.0625rem] text-ink-soft">
                        Creeper researches your competitors and emails you one
                        digest of what they launched, repriced, raised and hired
                        — with a source for every claim.
                    </p>

                    <RunButtons className="mb-14" />

                    <Terminal
                        aria-label="Example run"
                        caret
                        className="min-h-46 px-0"
                    >
                        {typed}
                        {OUTPUT_LINES.slice(0, revealed).map((line) => (
                            <span key={line.key}>
                                {'\n'}
                                {line.node}
                            </span>
                        ))}
                    </Terminal>
                </section>

                <section
                    id="setup"
                    className="mx-auto w-full max-w-3xl px-4 pb-16 sm:px-10 sm:pb-24"
                >
                    <MarketingHeading className="mb-6">
                        Set it up once
                    </MarketingHeading>

                    <ol className="border-b border-rule">
                        <MarketingStep step="01" heading="Add your company">
                            A name, a website and a sentence on what you sell.
                            Pick how often you want to hear about it.
                        </MarketingStep>

                        <MarketingStep
                            step="02"
                            heading="Meet your competitors"
                        >
                            Creeper reads your homepage, searches the web and
                            suggests who you are up against. Keep the ones that
                            count, add the ones it missed.
                        </MarketingStep>

                        <MarketingStep step="03" heading="Read the digest">
                            On schedule, one email: a headline, a section per
                            competitor, and a link for every claim.
                        </MarketingStep>
                    </ol>
                </section>

                <section
                    id="start"
                    className="mx-auto w-full max-w-3xl px-4 pb-20 sm:px-10 sm:pb-28"
                >
                    {plans ? (
                        <>
                            <MarketingHeading className="mb-4">
                                One plan, two sizes.
                            </MarketingHeading>

                            <p className="max-w-[34rem] text-ink-soft">
                                {trialDays} days free with one company, no card
                                needed. The research runs on{' '}
                                <strong className="font-medium text-ink">
                                    your own API key
                                </strong>
                                , so you pay your model provider directly and
                                Creeper never marks it up.
                            </p>

                            <div className="mt-10 grid gap-5 sm:grid-cols-2">
                                {plans.map((plan) => (
                                    <PlanCard
                                        key={plan.value}
                                        plan={plan}
                                        trialDays={trialDays}
                                        featured={plan.businessLimit === null}
                                    />
                                ))}
                            </div>

                            <p className="mt-14 max-w-[34rem] text-[0.9375rem] text-ink-soft">
                                Rather run it yourself? Creeper is open source,
                                and a copy of your own has no plans and no
                                limits.
                            </p>
                        </>
                    ) : (
                        <>
                            <MarketingHeading className="mb-4">
                                One install. No tiers, no seats.
                            </MarketingHeading>

                            <p className="max-w-[34rem] text-ink-soft">
                                Run it on a box you keep or on Laravel Cloud.
                                The research runs on{' '}
                                <strong className="font-medium text-ink">
                                    your own API key
                                </strong>{' '}
                                — Anthropic, OpenAI, Gemini, Groq or OpenRouter
                                — and you pay the provider directly.
                            </p>
                        </>
                    )}

                    <RunButtons className={plans ? 'mt-5' : 'mt-8'} />
                </section>
            </MarketingLayout>
        </>
    );
}

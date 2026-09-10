import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Chip,
    ChipRow,
    CopyBlock,
    CopyDock,
    Display,
    Eyebrow,
    Panel,
    PanelBar,
    SectionHeading,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import MarketingLayout, { MarketingNavLink } from '@/layouts/marketing-layout';
import { REPOSITORY_URL } from '@/lib/links';

/**
 * The prompt, written to be pasted into an agent rather than read as prose.
 * It names the commands the Laravel Cloud CLI actually has, and every piece of
 * Creeper that has to be running for a creep to fire — the queue worker and
 * the scheduler — because an agent that skips those deploys a site that looks
 * fine and never creeps anything.
 *
 * It also asks who is allowed in. An install left open is one anybody who
 * finds the URL can sign into, so the addresses are named at deploy time and
 * adding somebody later is another deploy.
 */
const PROMPT = `Deploy Creeper on Laravel Cloud for me.

1. Clone ${REPOSITORY_URL} and cd into it. It is a Laravel app:
   PHP 8.3+, Composer, Node 20+, Inertia and React on the front end.

2. Install and sign into the Laravel Cloud CLI:
     composer global require laravel/cloud-cli
     cloud auth -n

3. Read "cloud ship -h", then run "cloud ship -n" with every option passed
   explicitly so nothing prompts you. Provision a Postgres database, build the
   front end with "npm ci && npm run build", and run "php artisan migrate
   --force" on each deploy.

4. Creeper needs two things running besides the web app, or nothing is ever
   crept:
     - the scheduler, which dispatches due creeps every minute
     - a queue worker background process running "php artisan queue:work"

5. Set the environment with "cloud environment:variables -n --force":
     APP_NAME=Creeper
     MARKETING_MODE=false
     AUTHORIZED_EMAILS=...          the addresses allowed to sign in, comma
                                    separated — ask me who, and put mine
                                    first. Nobody else can get an account
     CREEP_DRIVER=llm
     DB_QUEUE_RETRY_AFTER=330       leave this above the job timeout, or a
                                    slow page is crept twice and billed twice
     MAIL_...                       signing in means receiving a six digit
                                    code, so mail has to work — ask me for
                                    the credentials
   There is deliberately no model API key here: keys are added inside the
   app, under Settings → API keys, and each target picks the one it spends.
   Use CREEP_DRIVER=fake instead if I tell you I want to look around before
   paying for a model.

6. Finish with "cloud deploy:monitor -n". If the deploy fails, show me what
   broke before you change anything. When it lands, give me the URL — I sign
   in at /login with my email address and the code it sends me.

7. Tell me that adding somebody later means adding their address to
   AUTHORIZED_EMAILS and deploying again, and how to do that. Tell me too
   that the first thing to do once I am signed in is add a model API key
   under Settings → API keys, because nothing can be crept without one.`;

/** One thing the agent does, numbered the way the landing page numbers setup. */
function AgentStep({
    step,
    heading,
    children,
}: {
    step: string;
    heading: string;
    children: ReactNode;
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
            </div>
        </li>
    );
}

export default function Deploy() {
    return (
        <>
            <Head title="Deploy on Laravel Cloud" />

            <MarketingLayout
                nav={
                    <>
                        <MarketingNavLink href="#prompt">
                            The prompt
                        </MarketingNavLink>
                        <MarketingNavLink href="#steps">
                            What it does
                        </MarketingNavLink>
                    </>
                }
            >
                <section className="mx-auto w-full max-w-5xl px-4 pt-8 pb-10 sm:px-10 sm:pt-16 sm:pb-16">
                    <Panel>
                        <PanelBar title="creeper · deploy" meta="one prompt" />

                        <div className="p-6 sm:p-12">
                            <Eyebrow className="mb-5 flex flex-wrap items-center gap-2.5">
                                <span>Your own copy</span>
                                <span className="text-rule">/</span>
                                <span>Laravel Cloud</span>
                            </Eyebrow>

                            <Display
                                as="h1"
                                size="lg"
                                className="mb-5 max-w-[22ch] text-balance"
                            >
                                Hand it to
                                <br />
                                <span className="text-ribbon">your agent.</span>
                            </Display>

                            <p className="mb-8 max-w-[40rem] text-[1.0625rem] text-ink-soft">
                                Creeper is an ordinary Laravel app, and{' '}
                                <strong className="font-medium text-ink">
                                    Laravel Cloud runs it as it is
                                </strong>{' '}
                                — no container to build, nothing to keep alive
                                yourself. Copy the prompt below into Claude
                                Code, or whichever agent you keep a terminal
                                open for, and it does the whole thing.
                            </p>

                            <ChipRow>
                                <Chip on>a Laravel Cloud account</Chip>
                                <Chip on>an agent with a shell</Chip>
                                <Chip>a model API key</Chip>
                            </ChipRow>
                        </div>
                    </Panel>
                </section>

                <section
                    id="prompt"
                    className="mx-auto w-full max-w-5xl px-4 pb-12 sm:px-10 sm:pb-20"
                >
                    <SectionHeading
                        as="h2"
                        className="mb-5"
                        title="Copy this, paste it in"
                        note="Then answer the three things it asks you for"
                    />

                    <CopyBlock
                        title="deploy prompt"
                        meta="reads it top to bottom"
                        action="Copy the prompt"
                        text={PROMPT}
                    />

                    <p className="mt-4 max-w-[40rem] text-sm text-ink-soft">
                        The prompt asks for three things: your model API key,
                        your mail credentials, and the addresses allowed to sign
                        in. It will not guess at any of them — Creeper signs
                        people in by emailing a six digit code, so an install
                        that cannot send mail cannot let anybody in, and an
                        install that admits every address is not yours.
                    </p>
                </section>

                <section
                    id="steps"
                    className="mx-auto w-full max-w-5xl px-4 pb-12 sm:px-10 sm:pb-20"
                >
                    <SectionHeading
                        as="h2"
                        className="mb-5"
                        title="What your agent will do"
                        note="So you can follow along, or stop it"
                    />

                    <ol className="border border-ink">
                        <AgentStep step="01" heading="Clone the repository">
                            The same source the repository link goes to. Nothing
                            is fetched from anywhere else, and nothing about
                            your copy is reported back here.
                        </AgentStep>

                        <AgentStep step="02" heading="Set up the Cloud CLI">
                            <code className="font-mono text-ink">
                                composer global require laravel/cloud-cli
                            </code>
                            , then{' '}
                            <code className="font-mono text-ink">
                                cloud auth
                            </code>{' '}
                            — which opens Laravel Cloud in your browser so you
                            sign in as yourself. Your agent never sees a
                            password.
                        </AgentStep>

                        <AgentStep step="03" heading="Ship it">
                            <code className="font-mono text-ink">
                                cloud ship
                            </code>{' '}
                            creates the application, provisions a Postgres
                            database, builds the front end and runs the
                            migrations.
                        </AgentStep>

                        <AgentStep step="04" heading="Start the two workers">
                            A queue worker does the creeping and the scheduler
                            decides when a target is due. Both have to be
                            running, or you get a site that looks perfectly fine
                            and never checks a page.
                        </AgentStep>

                        <AgentStep step="05" heading="Hand you the URL">
                            It watches the deploy land, then gives you the
                            address. Sign in with one of the addresses you
                            authorized, add your model key under Settings, and
                            put a page on watch.
                        </AgentStep>
                    </ol>
                </section>

                <section className="bg-ink text-paper-lit">
                    <div className="mx-auto grid w-full max-w-5xl items-start gap-8 px-4 py-12 sm:px-10 sm:py-20 lg:grid-cols-[1fr_22rem] lg:gap-14">
                        <div>
                            <Eyebrow tone="pale">Or keep it local</Eyebrow>

                            <Display
                                size="lg"
                                className="my-4 max-w-[18ch] text-balance"
                            >
                                It runs on your own box too.
                            </Display>

                            <p className="max-w-[32rem] text-paper-lit/70">
                                Cloud is the version with nothing to maintain.
                                If you would rather keep it on a machine you
                                own, the repository's README sets it up in four
                                commands — SQLite and a database queue, so there
                                is nothing else to stand up.
                            </p>
                        </div>

                        <div className="border border-paper-lit/20 px-6 py-9">
                            <p className="label-micro text-paper-lit/55">
                                The source
                            </p>

                            <p className="mt-3.5 mb-6 text-paper-lit/70">
                                Every driver, migration and schedule Creeper
                                has, in one repository.
                            </p>

                            <Button
                                asChild
                                size="lg"
                                variant="secondary"
                                className="w-full"
                            >
                                <a
                                    href={REPOSITORY_URL}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    View the repository
                                </a>
                            </Button>

                            <Button
                                asChild
                                size="lg"
                                variant="link"
                                className="mt-5 w-full text-paper-lit/70"
                            >
                                <Link href="/">Back to the landing page</Link>
                            </Button>
                        </div>
                    </div>
                </section>
            </MarketingLayout>

            <CopyDock
                title="deploy prompt"
                label="Copy the prompt"
                text={PROMPT}
            />
        </>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Github } from 'lucide-react';
import { CopyBlock, CopyDock, Eyebrow } from '@/components/ds';
import {
    LaravelLockup,
    LaravelMark,
} from '@/components/marketing/laravel-logo';
import { MarketingHeading, MarketingStep } from '@/components/marketing/type';
import { Button } from '@/components/ui/button';
import MarketingLayout, { MarketingNavLink } from '@/layouts/marketing-layout';
import { REPOSITORY_URL } from '@/lib/links';

/**
 * The prompt, written to be pasted into an agent rather than read as prose.
 * It names the commands the Laravel Cloud CLI actually has, and every piece of
 * Creeper that has to be running for a digest to go out — the queue worker and
 * the scheduler — because an agent that skips those deploys a site that looks
 * fine and never sends anything.
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

4. Creeper needs two things running besides the web app, or no digest is
   ever sent:
     - the scheduler, running "php artisan schedule:run" every minute; it
       runs "creeper:dispatch-due-digests" for every company whose digest
       is due
     - a queue worker background process running "php artisan queue:work",
       which does the research and sends the mail

5. Set the environment with "cloud environment:variables -n --force":
     APP_NAME=Creeper
     MARKETING_MODE=false
     AUTHORIZED_EMAILS=...          the addresses allowed to sign in, comma
                                    separated — ask me who, and put mine
                                    first. Nobody else can get an account
     DB_QUEUE_RETRY_AFTER=330       leave this above the 300 second job
                                    timeout, or a slow digest is researched
                                    twice and billed twice
     MAIL_MAILER=resend
     RESEND_API_KEY=...             signing in means receiving a six digit
                                    code, so mail has to work — ask me for
                                    the key
   Leave these at their defaults unless I say otherwise:
     DIGEST_MODEL=                  empty takes the provider's own default
     DIGEST_TIMEOUT=180             seconds per model call
     DIGEST_MAX_SEARCHES=12         web searches one digest may make
     DIGEST_DISCOVERY_MAX_SEARCHES=8
     DIGEST_DISCOVERY_MAX_COMPETITORS=8
     DIGEST_FETCH_*                 limits for reading my own homepage
   There is deliberately no model API key here: keys are added inside the
   app, under Settings → API keys, and each company picks the one it spends.
   Every search is billed to that key, which is what the DIGEST_*_SEARCHES
   caps are for.

6. Finish with "cloud deploy:monitor -n". If the deploy fails, show me what
   broke before you change anything. When it lands, give me the URL — I sign
   in at /login with my email address and the code it sends me.

7. Tell me that adding somebody later means adding their address to
   AUTHORIZED_EMAILS and deploying again, and how to do that. Tell me too
   that the first thing to do once I am signed in is add a model API key
   under Settings → API keys, because nothing can be researched without one.`;

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
                <section className="mx-auto w-full max-w-3xl px-4 pt-14 pb-16 sm:px-10 sm:pt-20 sm:pb-24">
                    <Eyebrow className="mb-6">Your own copy</Eyebrow>

                    <MarketingHeading size="hero" className="mb-6">
                        Hand it to
                        <br />
                        <span className="text-ribbon">your agent.</span>
                    </MarketingHeading>

                    <p className="max-w-[34rem] text-[1.0625rem] text-ink-soft">
                        Creeper is an ordinary Laravel app, and Laravel Cloud
                        runs it as it is — no container to build, nothing to
                        keep alive yourself. Paste one prompt into Claude Code,
                        or whichever agent you keep a terminal open for, and it
                        does the whole thing.
                    </p>

                    <LaravelLockup className="mt-8 h-7 w-auto text-ink" />

                    <p className="mt-6 max-w-[34rem] text-[0.9375rem] text-ink-soft">
                        You need{' '}
                        <strong className="font-medium text-ink">
                            a Laravel Cloud account
                        </strong>
                        ,{' '}
                        <strong className="font-medium text-ink">
                            an agent with a shell
                        </strong>{' '}
                        and, once it is running,{' '}
                        <strong className="font-medium text-ink">
                            a model API key
                        </strong>
                        .
                    </p>
                </section>

                <section
                    id="prompt"
                    className="mx-auto w-full max-w-3xl scroll-mt-6 px-4 pb-16 sm:px-10 sm:pb-24"
                >
                    <MarketingHeading className="mb-4">
                        Copy this, paste it in.
                    </MarketingHeading>

                    <p className="mb-8 max-w-[34rem] text-ink-soft">
                        The prompt asks for three things: your model API key,
                        your mail credentials, and the addresses allowed to sign
                        in. It will not guess at any of them — Creeper signs
                        people in by emailing a six digit code, so an install
                        that cannot send mail cannot let anybody in, and an
                        install that admits every address is not yours.
                    </p>

                    <CopyBlock
                        title="deploy prompt"
                        meta="reads it top to bottom"
                        action="Copy the prompt"
                        text={PROMPT}
                    />
                </section>

                <section
                    id="steps"
                    className="mx-auto w-full max-w-3xl scroll-mt-6 px-4 pb-16 sm:px-10 sm:pb-24"
                >
                    <MarketingHeading className="mb-6">
                        What your agent will do.
                    </MarketingHeading>

                    <ol className="border-b border-rule">
                        <MarketingStep step="01" heading="Clone the repository">
                            The same source the repository link goes to. Nothing
                            is fetched from anywhere else, and nothing about
                            your copy is reported back here.
                        </MarketingStep>

                        <MarketingStep step="02" heading="Set up the Cloud CLI">
                            <code className="font-mono text-[0.875rem] text-ink">
                                composer global require laravel/cloud-cli
                            </code>
                            , then{' '}
                            <code className="font-mono text-[0.875rem] text-ink">
                                cloud auth
                            </code>
                            , which opens Laravel Cloud in your browser so you
                            sign in as yourself. Your agent never sees a
                            password.
                        </MarketingStep>

                        <MarketingStep step="03" heading="Ship it">
                            <code className="font-mono text-[0.875rem] text-ink">
                                cloud ship
                            </code>{' '}
                            creates the application, provisions a Postgres
                            database, builds the front end and runs the
                            migrations.
                        </MarketingStep>

                        <MarketingStep
                            step="04"
                            heading="Start the two workers"
                        >
                            The scheduler decides when a digest is due and a
                            queue worker does the research and sends the mail.
                            Both have to run, or you get a site that looks fine
                            and never emails anyone.
                        </MarketingStep>

                        <MarketingStep step="05" heading="Hand you the URL">
                            It watches the deploy land, then gives you the
                            address. Sign in with one of the addresses you
                            authorized, add your model key under Settings, and
                            add your company.
                        </MarketingStep>
                    </ol>
                </section>

                <section className="mx-auto w-full max-w-3xl px-4 pb-20 sm:px-10 sm:pb-28">
                    <MarketingHeading className="mb-4">
                        It runs on your own box too.
                    </MarketingHeading>

                    <p className="max-w-[34rem] text-ink-soft">
                        Cloud is the version with nothing to maintain. If you
                        would rather keep it on a machine you own, the README
                        sets it up in four commands — SQLite and a database
                        queue, so there is nothing else to stand up.
                    </p>

                    <div className="mt-8 flex flex-wrap items-center gap-x-6 gap-y-4">
                        <Button asChild size="lg" variant="secondary">
                            <a
                                href={REPOSITORY_URL}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <Github aria-hidden="true" />
                                View the repository
                            </a>
                        </Button>

                        <Link
                            href="/"
                            className="inline-flex items-center gap-1.5 text-[0.9375rem] text-ink-soft transition-colors hover:text-ink"
                        >
                            <ArrowLeft aria-hidden="true" className="size-4" />
                            Back to the landing page
                        </Link>
                    </div>

                    <p className="mt-16 flex items-center gap-2.5 border-t border-rule pt-6 text-[0.9375rem] text-ink-soft">
                        <LaravelMark className="size-5 shrink-0 text-ink" />
                        Built with Laravel, made to run on Laravel Cloud.
                    </p>
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

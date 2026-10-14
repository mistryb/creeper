import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import DigestController from '@/actions/App/Http/Controllers/DigestController';
import { RunStatusBadge } from '@/components/digest/status-badge';
import { Page } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatRelative } from '@/lib/format';
import { index as apiKeysIndex } from '@/routes/api-keys';
import { show as showBusiness } from '@/routes/businesses';
import { index as competitorsIndex } from '@/routes/businesses/competitors';
import { show } from '@/routes/digests';
import type { Business, Competitor, Digest, ResourceCollection } from '@/types';

type Props = {
    business: { data: Business };
    digests: ResourceCollection<Digest>;
    competitors: ResourceCollection<Competitor>;
    isDigesting: boolean;
    hasApiKeys: boolean;
};

/**
 * Every digest about a company's competitors, newest first. This is the
 * first screen after signing in, so it reads like a reading list: when, the
 * headline, a line from each competitor, and the way into the whole thing.
 */
export default function DigestsIndex({
    business: { data: business },
    digests,
    competitors,
    isDigesting,
    hasApiKeys,
}: Props) {
    // A digest takes a minute or two to write; check back while one is.
    const { start, stop } = usePoll(
        3000,
        { only: ['digests', 'isDigesting'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isDigesting) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [isDigesting, start, stop]);

    const keyless = business.api_key_id === null;
    const nobody = competitors.data.length === 0;
    const named = new Map(competitors.data.map((c) => [c.subject, c.name]));

    return (
        <>
            <Head title="Digests" />

            <Page>
                <div className="space-y-3 text-center">
                    <h1 className="display-dot text-3xl">Digests</h1>
                    <p className="label-micro text-muted-foreground">
                        {business.digest_frequency_label}
                        {business.next_digest_at &&
                            ` · next ${formatDateTime(business.next_digest_at)}`}
                    </p>
                    <Form
                        {...DigestController.store.form(business.id)}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                disabled={
                                    processing ||
                                    isDigesting ||
                                    keyless ||
                                    nobody
                                }
                            >
                                {isDigesting ? 'Writing…' : 'Send one now'}
                            </Button>
                        )}
                    </Form>
                </div>

                {keyless && (
                    <p className="text-center text-sm text-ribbon-red">
                        No API key to write with.{' '}
                        <Link
                            href={
                                hasApiKeys
                                    ? showBusiness(business.id)
                                    : apiKeysIndex()
                            }
                            className="underline underline-offset-4"
                        >
                            {hasApiKeys ? 'Pick one' : 'Add one'}
                        </Link>
                        .
                    </p>
                )}

                {digests.data.length === 0 ? (
                    <p className="pt-6 text-center text-sm text-muted-foreground">
                        {nobody ? (
                            <>
                                Nothing to write about yet.{' '}
                                <Link
                                    href={competitorsIndex(business.id)}
                                    className="underline underline-offset-4"
                                >
                                    Add some competitors
                                </Link>
                                .
                            </>
                        ) : (
                            'No digests yet. The first one lands here.'
                        )}
                    </p>
                ) : (
                    <ol className="divide-y divide-rule border-t border-rule">
                        {digests.data.map((digest) => (
                            <DigestEntry
                                key={digest.id}
                                digest={digest}
                                named={named}
                            />
                        ))}
                    </ol>
                )}
            </Page>
        </>
    );
}

function DigestEntry({
    digest,
    named,
}: {
    digest: Digest;
    named: Map<string, string>;
}) {
    const when = formatDateTime(digest.period_end);

    if (digest.report === null) {
        return (
            <li className="space-y-1 py-6">
                <p className="flex items-center gap-3 label-micro text-muted-foreground">
                    {when}
                    <RunStatusBadge
                        status={digest.status}
                        label={digest.status_label}
                    />
                </p>
                {digest.error && (
                    <p className="text-sm text-muted-foreground">
                        {digest.error}
                    </p>
                )}
            </li>
        );
    }

    // A line from each competitor that had something to say.
    const lines = digest.report.competitors
        .filter((section) => section.items.length > 0)
        .slice(0, 3);

    return (
        <li className="py-6">
            <Link
                href={show(digest.id)}
                prefetch
                className="group block space-y-2"
            >
                <p className="label-micro text-muted-foreground">
                    {when}
                    {digest.sent_at &&
                        ` · sent ${formatRelative(digest.sent_at)}`}
                </p>
                <h2 className="text-lg leading-snug font-medium decoration-ribbon underline-offset-4 group-hover:underline">
                    {digest.report.headline}
                </h2>
                {lines.length > 0 && (
                    <ul className="space-y-1 text-sm text-muted-foreground">
                        {lines.map((section) => (
                            <li key={section.subject} className="line-clamp-1">
                                <span className="text-ink">
                                    {named.get(section.subject) ?? 'Removed'}
                                </span>{' '}
                                — {section.summary}
                            </li>
                        ))}
                    </ul>
                )}
            </Link>
        </li>
    );
}

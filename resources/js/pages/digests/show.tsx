import { Head, Link } from '@inertiajs/react';
import { DigestReportView } from '@/components/digest/digest-report';
import { RunStatusBadge } from '@/components/digest/status-badge';
import { Page } from '@/components/ds';
import { formatDateTime, formatRelative } from '@/lib/format';
import { index } from '@/routes/businesses/digests';
import type { Business, Competitor, Digest, ResourceCollection } from '@/types';

/**
 * One digest, to be read top to bottom: when it covers, what it says, and a
 * quiet line about how it was written.
 */
export default function ShowDigest({
    business: { data: business },
    digest: { data: digest },
    competitors,
}: {
    business: { data: Business };
    digest: { data: Digest };
    competitors: ResourceCollection<Competitor>;
}) {
    const period = `${formatDateTime(digest.period_start)} to ${formatDateTime(digest.period_end)}`;

    return (
        <>
            <Head title={`Digest · ${period}`} />

            <Page>
                <div className="space-y-2 text-center">
                    <p className="label-micro text-muted-foreground">
                        <Link
                            href={index(business.id)}
                            className="hover:text-ink"
                        >
                            ← Digests
                        </Link>
                    </p>
                    <p className="label-micro text-muted-foreground">
                        {period}
                    </p>
                    {digest.status !== 'succeeded' && (
                        <RunStatusBadge
                            status={digest.status}
                            label={digest.status_label}
                        />
                    )}
                </div>

                {digest.report ? (
                    <DigestReportView
                        report={digest.report}
                        competitors={competitors.data}
                    />
                ) : (
                    <p className="text-center text-sm text-muted-foreground">
                        {digest.error ?? 'Still being written.'}
                    </p>
                )}

                <p className="border-t border-rule pt-4 text-center font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                    {[
                        digest.sent_at &&
                            `Sent ${formatRelative(digest.sent_at)}`,
                        digest.provider &&
                            `written by ${digest.provider}${digest.model ? ` · ${digest.model}` : ''}`,
                        digest.prompt_tokens !== null &&
                            `${(digest.prompt_tokens + (digest.completion_tokens ?? 0)).toLocaleString()} tokens`,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </Page>
        </>
    );
}

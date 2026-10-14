import { ExternalLink } from 'lucide-react';
import { EmptyLine } from '@/components/ds';
import { hostOf } from '@/lib/format';
import type { Competitor, DigestReport, DigestSection } from '@/types';

/**
 * A digest as it reads on screen: the headline, then one section per
 * competitor. Sections are matched to competitors by subject id when they are
 * drawn, so a rename shows up and a competitor since deleted is shown under
 * the name the digest knew.
 */
export function DigestReportView({
    report,
    competitors,
}: {
    report: DigestReport;
    competitors: Competitor[];
}) {
    const named = new Map(
        competitors.map((competitor) => [competitor.subject, competitor]),
    );

    return (
        <div className="space-y-8">
            <p className="max-w-3xl text-lg leading-snug font-medium">
                {report.headline}
            </p>

            {report.competitors.map((section) => (
                <CompetitorSection
                    key={section.subject}
                    section={section}
                    competitor={named.get(section.subject) ?? null}
                />
            ))}

            {report.notes && (
                <p className="font-mono text-xs text-muted-foreground">
                    Note: {report.notes}
                </p>
            )}
        </div>
    );
}

function CompetitorSection({
    section,
    competitor,
}: {
    section: DigestSection;
    competitor: Competitor | null;
}) {
    return (
        <section className="space-y-3 border-t border-rule pt-5">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h3 className="text-base font-semibold">
                    {competitor?.name ?? 'A competitor since removed'}
                </h3>
                {competitor?.url && (
                    <a
                        href={competitor.url}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="inline-flex items-center gap-1.5 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                    >
                        {hostOf(competitor.url)}
                        <ExternalLink aria-hidden className="size-3" />
                    </a>
                )}
            </div>

            <p className="max-w-3xl text-sm">{section.summary}</p>

            {section.items.length === 0 ? (
                <EmptyLine>Quiet this period</EmptyLine>
            ) : (
                <ul className="divide-y divide-rule overflow-hidden rounded-card border border-rule bg-card shadow-xs">
                    {section.items.map((item, index) => (
                        <li key={index} className="space-y-1 px-4 py-3 text-sm">
                            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                <p className="font-medium">{item.title}</p>
                                {item.date && (
                                    <time
                                        dateTime={item.date}
                                        className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground tabular-nums"
                                    >
                                        {item.date}
                                    </time>
                                )}
                            </div>
                            {item.detail && (
                                <p className="text-muted-foreground">
                                    {item.detail}
                                </p>
                            )}
                            {item.url && (
                                <a
                                    href={item.url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="inline-flex items-center gap-1.5 font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                                >
                                    {hostOf(item.url)}
                                    <ExternalLink
                                        aria-hidden
                                        className="size-3"
                                    />
                                </a>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

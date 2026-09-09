import { EmptyLine } from '@/components/ds';
import { Badge } from '@/components/ui/badge';
import type { ChangelogRelease, FeatureKind } from '@/types';

/**
 * A changelog, as Creeper last read it: one block per release, the version
 * stamped in mono, and under it the things that release shipped.
 *
 * Newest first, the way the page itself reads.
 */
export function ReleaseList({ releases }: { releases: ChangelogRelease[] }) {
    if (releases.length === 0) {
        return <EmptyLine>No releases read yet</EmptyLine>;
    }

    return (
        <ol className="divide-y divide-rule">
            {releases.map((release, index) => (
                <li
                    key={`${release.version ?? release.title ?? 'release'}-${index}`}
                    className="py-4 first:pt-0 last:pb-0"
                >
                    <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <h3 className="font-mono text-sm font-medium tracking-[0.04em]">
                            {release.version ?? release.title ?? 'Untitled'}
                        </h3>
                        {release.version && release.title && (
                            <span className="text-sm text-muted-foreground">
                                {release.title}
                            </span>
                        )}
                        {release.released_on && (
                            <time
                                dateTime={release.released_on}
                                className="ml-auto font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground tabular-nums"
                            >
                                {release.released_on}
                            </time>
                        )}
                    </div>

                    {release.summary && (
                        <p className="mt-1.5 text-sm text-muted-foreground">
                            {release.summary}
                        </p>
                    )}

                    {release.features.length > 0 && (
                        <ul className="mt-3 space-y-2">
                            {release.features.map((feature, position) => (
                                <li
                                    key={`${feature.title}-${position}`}
                                    className="flex flex-wrap items-baseline gap-2"
                                >
                                    <KindBadge kind={feature.kind} />
                                    <span className="text-sm">
                                        {feature.title}
                                    </span>
                                    {feature.description && (
                                        <span className="basis-full text-sm text-muted-foreground">
                                            {feature.description}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </li>
            ))}
        </ol>
    );
}

/**
 * What sort of change this is. Breaking changes and security fixes are the two
 * a reader must not miss, so they take the loud ribbons; everything else is
 * quiet enough to skim.
 */
function KindBadge({ kind }: { kind: FeatureKind }) {
    const config = {
        feature: { variant: 'ok' as const, label: 'New' },
        improvement: { variant: 'outline' as const, label: 'Improved' },
        fix: { variant: 'outline' as const, label: 'Fixed' },
        breaking: { variant: 'bad' as const, label: 'Breaking' },
        deprecation: { variant: 'warn' as const, label: 'Deprecated' },
        security: { variant: 'warn' as const, label: 'Security' },
        other: { variant: 'muted' as const, label: 'Other' },
    }[kind];

    return <Badge variant={config.variant}>{config.label}</Badge>;
}

import { Form, Head, Link, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import CompetitorDiscoveryController from '@/actions/App/Http/Controllers/CompetitorDiscoveryController';
import { Page } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatRelative, hostOf } from '@/lib/format';
import { create } from '@/routes/businesses/competitors';
import { show } from '@/routes/competitors';
import type {
    Business,
    Competitor,
    CompetitorDiscovery,
    ResourceCollection,
} from '@/types';

type Props = {
    business: { data: Business };
    competitors: ResourceCollection<Competitor>;
    latestDiscovery: { data: CompetitorDiscovery } | null;
    isDiscovering: boolean;
};

/**
 * Everyone a company is up against: found by Creeper, or added by hand.
 */
export default function CompetitorsIndex({
    business: { data: business },
    competitors,
    latestDiscovery,
    isDiscovering,
}: Props) {
    // Looking takes a minute or two; check back while it is.
    const { start, stop } = usePoll(
        3000,
        { only: ['competitors', 'latestDiscovery', 'isDiscovering'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isDiscovering) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [isDiscovering, start, stop]);

    const count = competitors.data.length;
    const failed =
        latestDiscovery?.data.status === 'failed' ? latestDiscovery.data : null;

    return (
        <>
            <Head title="Competitors" />

            <Page>
                <div className="space-y-3 text-center">
                    <h1 className="display-dot text-3xl">Competitors</h1>
                    <p className="label-micro text-muted-foreground">
                        {count === 1 ? '1 competitor' : `${count} competitors`}{' '}
                        of {business.name}
                    </p>
                    <div className="flex justify-center gap-2">
                        <Form
                            {...CompetitorDiscoveryController.store.form(
                                business.id,
                            )}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        processing ||
                                        isDiscovering ||
                                        business.api_key_id === null
                                    }
                                >
                                    {isDiscovering
                                        ? 'Looking…'
                                        : count === 0
                                          ? 'Find competitors'
                                          : 'Find more'}
                                </Button>
                            )}
                        </Form>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={create(business.id)}>Add one</Link>
                        </Button>
                    </div>
                </div>

                {failed && (
                    <p className="text-center text-sm text-ribbon-red">
                        Creeper could not look: {failed.error}
                    </p>
                )}

                {count === 0 ? (
                    isDiscovering ? (
                        <div className="space-y-3 border-t border-rule pt-6">
                            <Skeleton className="h-4 w-1/2" />
                            <Skeleton className="h-4 w-2/3" />
                            <Skeleton className="h-4 w-2/5" />
                        </div>
                    ) : (
                        <p className="pt-6 text-center text-sm text-muted-foreground">
                            Nobody yet. Let Creeper look, or add one yourself.
                        </p>
                    )
                ) : (
                    <ul className="divide-y divide-rule border-t border-rule">
                        {competitors.data.map((competitor) => (
                            <li key={competitor.id} className="space-y-1 py-5">
                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                                    <Link
                                        href={show(competitor.id)}
                                        prefetch
                                        className="text-base font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                    >
                                        {competitor.name}
                                    </Link>
                                    <span className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                        {competitor.url &&
                                            hostOf(competitor.url)}
                                        {competitor.url && ' · '}
                                        {competitor.discovered_at
                                            ? `found ${formatRelative(competitor.discovered_at)}`
                                            : 'added by you'}
                                    </span>
                                </div>
                                {(competitor.reason ??
                                    competitor.description) && (
                                    <p className="text-sm text-muted-foreground">
                                        {competitor.reason ??
                                            competitor.description}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Page>
        </>
    );
}

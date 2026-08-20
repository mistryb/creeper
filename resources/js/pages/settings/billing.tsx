import { Form, Head, Link } from '@inertiajs/react';
import BillingController from '@/actions/App/Http/Controllers/BillingController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { edit as editApiKey } from '@/routes/api-key';
import { portal } from '@/routes/billing';
import type { BillingPlan } from '@/types';

type Usage = {
    targets: number;
    limit: number | null;
    runs: number;
    included_runs: number | null;
    overage_runs: number;
    overage_amount: number;
};

type Props = {
    plan: BillingPlan;
    offer: BillingPlan | null;
    usage: Usage;
    overage_unit_amount: number;
    has_api_key: boolean;
    subscribed: boolean;
};

function money(minorUnits: number): string {
    return `$${(minorUnits / 100).toFixed(2)}`;
}

export default function Billing({
    plan,
    offer,
    usage,
    overage_unit_amount: overageUnitAmount,
    has_api_key: hasApiKey,
    subscribed,
}: Props) {
    const included = usage.included_runs;
    const usedPercent =
        included && included > 0
            ? Math.min(100, (usage.runs / included) * 100)
            : 0;

    return (
        <>
            <Head title="Billing" />

            <h1 className="sr-only">Billing settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Billing"
                    description="Your plan and what you have used this month"
                />

                <div className="rounded-lg border border-border p-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Current plan
                            </p>
                            <p className="text-lg font-medium">
                                {plan.name}
                                {plan.price ? ` · ${plan.price}` : ''}
                            </p>
                        </div>

                        {subscribed && (
                            <Button variant="outline" asChild>
                                <Link href={portal()}>Manage subscription</Link>
                            </Button>
                        )}
                    </div>

                    {included !== null && (
                        <div className="mt-5 space-y-1">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Checks this month
                                </span>
                                <span className="tabular-nums">
                                    {usage.runs.toLocaleString()} of{' '}
                                    {included.toLocaleString()} included
                                </span>
                            </div>

                            <div
                                className="h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuenow={usage.runs}
                                aria-valuemin={0}
                                aria-valuemax={included}
                                aria-label="Checks used this month"
                            >
                                <div
                                    className="h-full rounded-full bg-primary"
                                    style={{ width: `${usedPercent}%` }}
                                />
                            </div>

                            <p className="pt-1 text-sm text-muted-foreground">
                                {usage.overage_runs > 0 ? (
                                    <>
                                        {usage.overage_runs.toLocaleString()}{' '}
                                        extra{' '}
                                        {usage.overage_runs === 1
                                            ? 'check'
                                            : 'checks'}{' '}
                                        so far, adding{' '}
                                        <span className="font-medium text-foreground tabular-nums">
                                            {money(usage.overage_amount)}
                                        </span>{' '}
                                        to your next invoice.
                                    </>
                                ) : (
                                    <>
                                        Extra checks are{' '}
                                        {money(overageUnitAmount)} each once the
                                        allowance runs out.
                                    </>
                                )}
                            </p>
                        </div>
                    )}

                    <div className="mt-4 flex justify-between border-t border-border pt-4 text-sm">
                        <span className="text-muted-foreground">
                            Creep targets
                        </span>
                        <span className="tabular-nums">
                            {usage.targets}
                            {usage.limit !== null && ` of ${usage.limit}`}
                        </span>
                    </div>
                </div>

                {!hasApiKey && (
                    <div className="flex items-center justify-between gap-4 rounded-lg border border-border bg-muted/40 p-4">
                        <p className="text-sm text-muted-foreground">
                            Creeper runs on your own model API key, and you do
                            not have one on file yet.
                        </p>
                        <Button variant="outline" asChild>
                            <Link href={editApiKey()}>Add a key</Link>
                        </Button>
                    </div>
                )}

                {!subscribed && offer && (
                    <div className="flex flex-col gap-3 rounded-lg border border-border p-4">
                        <div>
                            <p className="font-medium">{offer.name}</p>
                            <p className="text-2xl font-semibold">
                                {offer.price ?? '—'}
                            </p>
                        </div>

                        <ul className="flex-1 space-y-1 text-sm text-muted-foreground">
                            <li>
                                {offer.targets === null
                                    ? 'Unlimited creep targets'
                                    : `${offer.targets} creep targets`}
                            </li>
                            <li>
                                Creep as often as{' '}
                                {offer.min_frequency ?? 'daily'}
                            </li>
                            <li>
                                {offer.included_runs?.toLocaleString() ?? 0}{' '}
                                checks included, then {money(overageUnitAmount)}{' '}
                                each
                            </li>
                            <li>Your own model API key, billed to you</li>
                        </ul>

                        <Form
                            {...BillingController.checkout.form({
                                plan: offer.key,
                            })}
                        >
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    Subscribe
                                </Button>
                            )}
                        </Form>
                    </div>
                )}
            </div>
        </>
    );
}

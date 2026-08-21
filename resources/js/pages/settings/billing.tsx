import { Form, Head, Link } from '@inertiajs/react';
import BillingController from '@/actions/App/Http/Controllers/BillingController';
import { Meter, ReceiptRow, SectionHeading, StatTile } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

    return (
        <>
            <Head title="Billing" />

            <h1 className="sr-only">Billing settings</h1>

            <div className="space-y-6">
                <SectionHeading
                    as="h2"
                    size="sm"
                    title="Billing"
                    note="Your plan, and what you have used this month"
                    actions={
                        subscribed && (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={portal()}>Manage subscription</Link>
                            </Button>
                        )
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatTile
                        label="Checks this month"
                        value={usage.runs.toLocaleString()}
                        tone={usage.overage_runs > 0 ? 'warn' : 'default'}
                        note={
                            included === null
                                ? plan.name
                                : `of ${included.toLocaleString()} included`
                        }
                    />
                    <StatTile
                        label="Creep targets"
                        value={usage.targets.toLocaleString()}
                        note={
                            usage.limit === null
                                ? 'no limit on your plan'
                                : `of ${usage.limit.toLocaleString()} allowed`
                        }
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {plan.name}
                            {plan.price ? ` · ${plan.price}` : ''}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {included !== null && (
                            <>
                                <Meter
                                    label="Checks used"
                                    value={usage.runs}
                                    max={included}
                                    valueLabel={`${usage.runs.toLocaleString()} of ${included.toLocaleString()}`}
                                />

                                <p className="text-sm text-muted-foreground">
                                    {usage.overage_runs > 0 ? (
                                        <>
                                            {usage.overage_runs.toLocaleString()}{' '}
                                            extra{' '}
                                            {usage.overage_runs === 1
                                                ? 'check'
                                                : 'checks'}{' '}
                                            so far, adding{' '}
                                            <span className="font-mono font-medium text-foreground tabular-nums">
                                                {money(usage.overage_amount)}
                                            </span>{' '}
                                            to your next invoice.
                                        </>
                                    ) : (
                                        <>
                                            Extra checks are{' '}
                                            {money(overageUnitAmount)} each once
                                            the allowance runs out.
                                        </>
                                    )}
                                </p>
                            </>
                        )}

                        {!hasApiKey && (
                            <div className="flex flex-wrap items-center justify-between gap-4 border border-ribbon-amber/35 bg-ribbon-amber/8 px-4 py-3">
                                <p className="max-w-sm text-sm text-ribbon-amber">
                                    Creeper runs on your own model API key, and
                                    you do not have one on file yet.
                                </p>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={editApiKey()}>Add a key</Link>
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {!subscribed && offer && (
                    <Offer
                        offer={offer}
                        overageUnitAmount={overageUnitAmount}
                    />
                )}
            </div>
        </>
    );
}

/**
 * The upgrade, printed as the landing page's receipt: one line item per thing
 * you get, then the total.
 */
function Offer({
    offer,
    overageUnitAmount,
}: {
    offer: BillingPlan;
    overageUnitAmount: number;
}) {
    return (
        <div className="border border-ink bg-white px-5 py-6 font-mono text-[0.8125rem] tabular-nums shadow-stamp-sm">
            <div className="border-b border-dashed border-rule pb-3.5 text-center">
                <strong className="block font-semibold tracking-[0.22em] uppercase">
                    {offer.name}
                </strong>
                <span className="text-[0.6875rem] tracking-[0.1em] text-ink-soft uppercase">
                    One line item, every month
                </span>
            </div>

            <div className="space-y-3 py-4">
                <ReceiptRow
                    label="Creep targets"
                    value={
                        offer.targets === null
                            ? 'unlimited'
                            : offer.targets.toLocaleString()
                    }
                />
                <ReceiptRow
                    label="Creep as often as"
                    value={offer.min_frequency ?? 'daily'}
                />
                <ReceiptRow
                    label="Checks included"
                    value={`${offer.included_runs?.toLocaleString() ?? 0} / mo`}
                />
                <ReceiptRow
                    label="Extra checks"
                    value={`${money(overageUnitAmount)} each`}
                />
                <ReceiptRow label="Model API key" value="yours" />
            </div>

            <div className="flex items-end justify-between gap-3 border-t border-dashed border-rule pt-4">
                <span className="label-micro text-ink-soft">Total due</span>
                <span className="numeral-dot text-3xl">
                    {offer.price ?? '—'}
                </span>
            </div>

            <Form
                {...BillingController.checkout.form({ plan: offer.key })}
                className="mt-5"
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                    >
                        Subscribe
                    </Button>
                )}
            </Form>
        </div>
    );
}

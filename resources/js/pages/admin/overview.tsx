import { Head } from '@inertiajs/react';
import { ReceiptRow, SectionHeading, StatTile } from '@/components/ds';

type Revenue = {
    plans: {
        label: string;
        price: number;
        paying: number;
        trialing: number;
    }[];
    /** Dollars a month from paying subscribers. */
    mrr: number;
    /** Accounts on the card-free trial. */
    trials: number;
    comped: number;
};

type Props = {
    accounts: number;
    businesses: number;
    digestsThisWeek: number;
    /** Null on a self-hosted install. */
    revenue: Revenue | null;
};

export default function AdminOverview({
    accounts,
    businesses,
    digestsThisWeek,
    revenue,
}: Props) {
    return (
        <>
            <Head title="Admin" />

            <div className="grid gap-3 sm:grid-cols-3">
                <StatTile label="Accounts" value={accounts.toLocaleString()} />
                <StatTile
                    label="Companies"
                    value={businesses.toLocaleString()}
                />
                <StatTile
                    label="Digests"
                    value={digestsThisWeek.toLocaleString()}
                    note="sent in the last 7 days"
                />
            </div>

            <div className="space-y-5 border-t border-rule pt-8">
                <SectionHeading
                    as="h2"
                    size="sm"
                    title="Revenue"
                    description={
                        revenue
                            ? 'Read from the subscriptions Stripe’s webhooks keep in sync.'
                            : 'Billing is off: Stripe is not configured, so this install runs self-hosted and charges nobody.'
                    }
                />

                {revenue && (
                    <>
                        <StatTile
                            label="Monthly recurring revenue"
                            value={`$${revenue.mrr.toLocaleString()}`}
                            tone="ribbon"
                            note="paying subscribers only"
                        />

                        <dl className="space-y-2 font-mono text-sm tabular-nums">
                            {revenue.plans.map((plan) => (
                                <ReceiptRow
                                    key={plan.label}
                                    labelAs="dt"
                                    valueAs="dd"
                                    label={`${plan.label} · $${plan.price}`}
                                    value={`${plan.paying} paying · ${plan.trialing} trialing`}
                                />
                            ))}
                            <ReceiptRow
                                labelAs="dt"
                                valueAs="dd"
                                label="Free trial"
                                value={`${revenue.trials} accounts`}
                            />
                            <ReceiptRow
                                labelAs="dt"
                                valueAs="dd"
                                label="Comped"
                                value={`${revenue.comped} accounts`}
                            />
                        </dl>
                    </>
                )}
            </div>
        </>
    );
}

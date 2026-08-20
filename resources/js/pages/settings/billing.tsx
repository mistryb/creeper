import { Form, Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import BillingController from '@/actions/App/Http/Controllers/BillingController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/billing';
import { portal } from '@/routes/billing';
import type { BillingPlan } from '@/types';

type Props = {
    plan: BillingPlan;
    plans: BillingPlan[];
    usage: { targets: number; limit: number | null };
    subscribed: boolean;
};

export default function Billing({ plan, plans, usage, subscribed }: Props) {
    return (
        <>
            <Head title="Billing" />

            <h1 className="sr-only">Billing settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Billing"
                    description="Your plan and what it covers"
                />

                <div className="rounded-lg border border-border p-4">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Current plan
                            </p>
                            <p className="text-lg font-medium">{plan.name}</p>
                        </div>

                        {subscribed && (
                            <Button variant="outline" asChild>
                                <Link href={portal()}>Manage subscription</Link>
                            </Button>
                        )}
                    </div>

                    <div className="mt-4 space-y-1">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">
                                Creep targets
                            </span>
                            <span className="tabular-nums">
                                {usage.targets}
                                {usage.limit !== null && ` of ${usage.limit}`}
                            </span>
                        </div>

                        {usage.limit !== null && (
                            <div
                                className="h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuenow={usage.targets}
                                aria-valuemin={0}
                                aria-valuemax={usage.limit}
                                aria-label="Creep targets used"
                            >
                                <div
                                    className="h-full rounded-full bg-primary"
                                    style={{
                                        width: `${Math.min(100, (usage.targets / usage.limit) * 100)}%`,
                                    }}
                                />
                            </div>
                        )}
                    </div>
                </div>

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Plans"
                        description="Change what Creeper is allowed to do for you"
                    />

                    <div className="grid gap-4 sm:grid-cols-2">
                        {plans.map((option) => (
                            <div
                                key={option.key}
                                className="flex flex-col gap-3 rounded-lg border border-border p-4"
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <p className="font-medium">{option.name}</p>
                                    {option.key === plan.key && (
                                        <Badge variant="secondary">
                                            <Check aria-hidden />
                                            Current
                                        </Badge>
                                    )}
                                </div>

                                <p className="text-2xl font-semibold">
                                    {option.price ?? '—'}
                                </p>

                                <ul className="flex-1 space-y-1 text-sm text-muted-foreground">
                                    <li>
                                        {option.targets === null
                                            ? 'Unlimited creep targets'
                                            : `${option.targets} creep targets`}
                                    </li>
                                    <li>
                                        Creep as often as{' '}
                                        {option.min_frequency ?? 'daily'}
                                    </li>
                                </ul>

                                {option.purchasable &&
                                    option.key !== plan.key && (
                                        <Form
                                            {...BillingController.checkout.form(
                                                option.key,
                                            )}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    className="w-full"
                                                    disabled={processing}
                                                >
                                                    {processing
                                                        ? 'Opening Stripe…'
                                                        : `Upgrade to ${option.name}`}
                                                </Button>
                                            )}
                                        </Form>
                                    )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

Billing.layout = {
    breadcrumbs: [{ title: 'Billing settings', href: show() }],
};

import { Form, Head, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';
import BillingController from '@/actions/App/Http/Controllers/Settings/BillingController';
import { Chip, SectionHeading } from '@/components/ds';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import type { PlanName, PlanOption } from '@/types';

type Props = {
    plans: PlanOption[];
    plan: PlanName | null;
    comped: boolean;
    trialEndsAt: string | null;
    /** Set while a cancelled subscription runs out the period it paid for. */
    endsAt: string | null;
    businessCount: number;
    /** Null means no limit. */
    businessLimit: number | null;
    hasBillingAccount: boolean;
    justCheckedOut: boolean;
};

function companies(count: number): string {
    return count === 1 ? '1 company' : `${count} companies`;
}

/**
 * The plan this account pays for. Subscribing and everything to do with the
 * card happen on Stripe; moving between the two plans happens here.
 */
export default function Billing({
    plans,
    plan,
    comped,
    trialEndsAt,
    endsAt,
    businessCount,
    businessLimit,
    hasBillingAccount,
    justCheckedOut,
}: Props) {
    const current = plans.find((option) => option.value === plan);

    // Stripe confirms a new subscription by webhook, which can land a moment
    // after Checkout sends the visitor back. Ask again until it has.
    const waitingForStripe = justCheckedOut && plan === null && !comped;
    const { stop } = usePoll(
        3000,
        { only: ['plan', 'billing', 'businessLimit'] },
        { autoStart: waitingForStripe },
    );

    useEffect(() => {
        if (!waitingForStripe) {
            stop();
        }
    }, [waitingForStripe, stop]);

    return (
        <>
            <Head title="Billing" />

            <h1 className="sr-only">Billing settings</h1>

            <div className="space-y-5">
                <SectionHeading
                    as="h2"
                    size="sm"
                    title="Billing"
                    description={
                        comped
                            ? 'You have Unlimited, on the house.'
                            : current
                              ? `You are on ${current.label}, $${current.price} a month.`
                              : trialEndsAt
                                ? `Your free trial covers one company until ${formatDate(trialEndsAt)}. Pick a plan to keep your digests coming.`
                                : 'Pick a plan to start your digests again. Your companies and competitors are all still here.'
                    }
                />

                {waitingForStripe && (
                    <Alert>
                        <AlertTitle>Thanks — confirming with Stripe</AlertTitle>
                        <AlertDescription>
                            <p>Your plan will show up here in a moment.</p>
                        </AlertDescription>
                    </Alert>
                )}

                {endsAt && (
                    <Alert>
                        <AlertTitle>Cancelled</AlertTitle>
                        <AlertDescription>
                            <p>
                                Your plan runs until {formatDate(endsAt)}, then
                                digests stop. Resume it from the billing portal
                                below.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                <ul className="overflow-hidden rounded-card border border-rule bg-card shadow-xs">
                    {plans.map((option) => (
                        <li
                            key={option.value}
                            className="flex flex-wrap items-center justify-between gap-4 border-b border-rule px-4 py-3 last:border-b-0"
                        >
                            <div>
                                <p className="text-sm">{option.label}</p>
                                <p className="mt-1 font-mono text-xs text-muted-foreground tabular-nums">
                                    ${option.price} / month ·{' '}
                                    {option.businessLimit === null
                                        ? 'unlimited companies'
                                        : companies(option.businessLimit)}
                                </p>
                            </div>

                            {option.value === plan ? (
                                <Chip on>Current</Chip>
                            ) : comped ? null : plan ? (
                                <Form
                                    {...BillingController.update.form(
                                        option.value,
                                    )}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Switch
                                        </Button>
                                    )}
                                </Form>
                            ) : (
                                <Form
                                    {...BillingController.checkout.form(
                                        option.value,
                                    )}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Subscribe
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </li>
                    ))}
                </ul>

                {businessLimit !== null &&
                    businessLimit > 0 &&
                    businessCount > businessLimit && (
                        <p className="text-xs text-muted-foreground">
                            You have {companies(businessCount)} and your plan
                            covers {companies(businessLimit)}. Digests go out
                            only for the first you set up; the rest wait until
                            you move up.
                        </p>
                    )}

                {hasBillingAccount && (
                    <Form {...BillingController.portal.form()}>
                        {({ processing }) => (
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-rule pt-5">
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={processing}
                                >
                                    Manage billing
                                </Button>
                                <p className="max-w-sm text-xs text-muted-foreground">
                                    Your card, invoices and cancelling, on
                                    Stripe.
                                </p>
                            </div>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

import { Form, Head } from '@inertiajs/react';
import { MonitorSmartphone } from 'lucide-react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { EmptyState, FormActions, SectionHeading } from '@/components/ds';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/security';

type Props = {
    otherSessions: number;
};

/**
 * There is no password to change and no second factor to enrol. The one thing
 * worth having here is a way to cut off other browsers, because signing in
 * leaves a cookie that lasts about a year.
 */
export default function Security({ otherSessions }: Props) {
    return (
        <>
            <Head title="Security settings" />

            <h1 className="sr-only">Security settings</h1>

            <div className="space-y-5">
                <SectionHeading
                    as="h2"
                    size="sm"
                    title="Security"
                    description="Creeper has no passwords. You sign in with a code emailed to your address, and this browser stays signed in afterwards."
                />

                <Alert>
                    <AlertTitle>How signing in works</AlertTitle>
                    <AlertDescription>
                        <p>
                            Whoever can read email at your address can sign in,
                            so there is nothing else to configure — and nothing
                            else standing in the way. Keep that mailbox secure.
                        </p>
                    </AlertDescription>
                </Alert>

                {otherSessions === 0 ? (
                    <EmptyState
                        icon={MonitorSmartphone}
                        title="No other browsers"
                    >
                        This is the only browser with a live session. Others may
                        still return using a stored cookie — sign out everywhere
                        below if you have lost a device.
                    </EmptyState>
                ) : (
                    <div className="border border-rule bg-card px-4 py-3 shadow-xs">
                        <p className="label-micro text-muted-foreground">
                            Other live sessions
                        </p>
                        <p className="mt-2 numeral-dot text-4xl">
                            {otherSessions.toLocaleString()}
                        </p>
                    </div>
                )}

                <Form
                    {...SecurityController.destroy.form()}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <FormActions>
                            <Button
                                type="submit"
                                variant="secondary"
                                disabled={processing}
                                data-test="sign-out-others-button"
                            >
                                Sign out everywhere else
                            </Button>
                            <p className="max-w-sm text-xs text-muted-foreground">
                                Revokes the stored cookie on every other
                                browser. This one stays signed in.
                            </p>
                        </FormActions>
                    )}
                </Form>
            </div>
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Security settings',
            href: edit(),
        },
    ],
};

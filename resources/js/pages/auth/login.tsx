import { Form, Head } from '@inertiajs/react';
import { Field } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

type Props = {
    /** Prefilled when this browser has asked for a code before. */
    email?: string | null;
    status?: string;
};

/**
 * The only way in. One field, and no distinction between signing in and
 * signing up — an address we have not seen before gets an account when its
 * code comes back.
 */
export default function Login({ email, status }: Props) {
    return (
        <>
            <Head title="Sign in" />

            {status && (
                <p className="text-center font-mono text-xs tracking-[0.04em] text-ribbon">
                    {status}
                </p>
            )}

            <Form
                {...store.form()}
                className="space-y-5"
                disableWhileProcessing
            >
                {({ processing, errors }) => (
                    <>
                        <Field
                            label="Email address"
                            htmlFor="email"
                            error={errors.email}
                            hint="We'll email you a six digit code. No password to remember."
                        >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                required
                                autoFocus
                                autoComplete="email"
                                defaultValue={email ?? ''}
                                placeholder="email@example.com"
                            />
                        </Field>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="request-code-button"
                        >
                            {processing && <Spinner />}
                            {processing ? 'Sending…' : 'Email me a code'}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Sign in to Creeper',
    description: 'New here? The same form signs you up.',
};

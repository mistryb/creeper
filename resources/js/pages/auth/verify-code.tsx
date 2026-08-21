import { Form, Head } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import { Field } from '@/components/ds';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store as verify } from '@/routes/login/verify';

const CODE_LENGTH = 6;

type Props = {
    /** Known when the code was asked for in this browser; typed otherwise. */
    email?: string | null;
    status?: string;
    expiresInMinutes: number;
};

/**
 * Where the code is typed back. The address travels with the code, so a code
 * requested on a laptop can be finished on a phone — which is why the address
 * is an editable field rather than a hidden one when we do not already know it.
 */
export default function VerifyCode({ email, status, expiresInMinutes }: Props) {
    const [code, setCode] = useState('');

    return (
        <>
            <Head title="Enter your code" />

            {status && (
                <p className="text-center font-mono text-xs tracking-[0.04em] text-ribbon">
                    {status}
                </p>
            )}

            <Form
                {...verify.form()}
                className="space-y-5"
                resetOnError={['code']}
                onError={() => setCode('')}
                disableWhileProcessing
            >
                {({ processing, errors }) => (
                    <>
                        {email ? (
                            <input type="hidden" name="email" value={email} />
                        ) : (
                            <Field
                                label="Email address"
                                htmlFor="email"
                                error={errors.email}
                                hint="The address you asked for a code with."
                            >
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                            </Field>
                        )}

                        <div className="space-y-2">
                            <p className="text-center label-micro text-ink-soft">
                                Six digit code
                            </p>

                            <div className="flex justify-center">
                                <InputOTP
                                    name="code"
                                    maxLength={CODE_LENGTH}
                                    value={code}
                                    onChange={setCode}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    autoFocus
                                    autoComplete="one-time-code"
                                    disabled={processing}
                                >
                                    <InputOTPGroup>
                                        {Array.from({
                                            length: CODE_LENGTH,
                                        }).map((_, slot) => (
                                            <InputOTPSlot
                                                key={slot}
                                                index={slot}
                                            />
                                        ))}
                                    </InputOTPGroup>
                                </InputOTP>
                            </div>

                            <InputError
                                message={errors.code}
                                className="text-center"
                            />

                            <p className="text-center text-xs text-muted-foreground">
                                {email ? <>Sent to {email}. </> : null}
                                Expires in {expiresInMinutes} minutes.
                            </p>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing || code.length < CODE_LENGTH}
                            data-test="verify-code-button"
                        >
                            {processing && <Spinner />}
                            {processing ? 'Checking…' : 'Sign in'}
                        </Button>
                    </>
                )}
            </Form>

            <p className="text-center text-xs text-muted-foreground">
                Didn't get it?{' '}
                <TextLink href={login()}>Send another code</TextLink>
                {' — '}a new one replaces the old.
            </p>
        </>
    );
}

VerifyCode.layout = {
    title: 'Enter your code',
    description: 'Check your inbox for a six digit code.',
};

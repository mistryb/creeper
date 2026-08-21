import { Form, Head, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { Field, FormActions, SectionHeading } from '@/components/ds';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';

const CODE_LENGTH = 6;

type PageProps = {
    auth: Auth;
};

type Props = {
    /** An address waiting on its code, if a change is in flight. */
    pendingEmail: string | null;
    expiresInMinutes: number;
};

export default function Profile({ pendingEmail, expiresInMinutes }: Props) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-5">
                <SectionHeading
                    as="h2"
                    size="sm"
                    title="Profile"
                    note="Your name and email address"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Name"
                                htmlFor="name"
                                error={errors.name}
                                hint="We guessed this from your email address when the account was created."
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={auth.user.name}
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />
                            </Field>

                            <Field
                                label="Email address"
                                htmlFor="email"
                                error={errors.email}
                                hint="This is how you sign in, so a new address has to be confirmed with a code before it takes effect."
                            >
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    defaultValue={auth.user.email}
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />
                            </Field>

                            <FormActions>
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>

                {pendingEmail && (
                    <ConfirmEmailChange
                        email={pendingEmail}
                        expiresInMinutes={expiresInMinutes}
                    />
                )}
            </div>

            <DeleteUser email={auth.user.email} />
        </>
    );
}

/**
 * The half-finished address change. It sits here until the code sent to the
 * new address comes back, so a typo never becomes the only way in.
 */
function ConfirmEmailChange({
    email,
    expiresInMinutes,
}: {
    email: string;
    expiresInMinutes: number;
}) {
    const [code, setCode] = useState('');

    return (
        <div className="space-y-4 border border-ribbon-amber/35 bg-ribbon-amber/8 p-4">
            <div className="space-y-0.5 text-ribbon-amber">
                <p className="label-mono uppercase">Confirm your new address</p>
                <p className="text-sm">
                    We sent a six digit code to {email}. Until you enter it, you
                    still sign in with your current address.
                </p>
            </div>

            <Form
                {...ProfileController.confirmEmail.form()}
                options={{ preserveScroll: true }}
                resetOnError={['code']}
                onError={() => setCode('')}
                className="space-y-3"
            >
                {({ processing, errors }) => (
                    <>
                        <InputOTP
                            name="code"
                            maxLength={CODE_LENGTH}
                            value={code}
                            onChange={setCode}
                            pattern={REGEXP_ONLY_DIGITS}
                            autoComplete="one-time-code"
                            disabled={processing}
                        >
                            <InputOTPGroup>
                                {Array.from({ length: CODE_LENGTH }).map(
                                    (_, slot) => (
                                        <InputOTPSlot key={slot} index={slot} />
                                    ),
                                )}
                            </InputOTPGroup>
                        </InputOTP>

                        <InputError message={errors.code} />

                        <p className="text-xs text-ribbon-amber/90">
                            Expires in {expiresInMinutes} minutes.
                        </p>

                        <Button
                            type="submit"
                            disabled={processing || code.length < CODE_LENGTH}
                            data-test="confirm-email-button"
                        >
                            Confirm address
                        </Button>
                    </>
                )}
            </Form>

            <Form
                {...ProfileController.cancelEmail.form()}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant="ghost"
                        disabled={processing}
                        data-test="cancel-email-button"
                    >
                        Cancel the change
                    </Button>
                )}
            </Form>
        </div>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};

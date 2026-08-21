import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { Field, FormActions, SectionHeading } from '@/components/ds';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
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

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div className="space-y-2">
                                        <p className="text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <TextLink href={send()} as="button">
                                                Re-send the verification email.
                                            </TextLink>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <p className="font-mono text-xs text-ribbon">
                                                A new verification link has been
                                                sent to your email address.
                                            </p>
                                        )}
                                    </div>
                                )}

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
            </div>

            <DeleteUser />
        </>
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

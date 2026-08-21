import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Field, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

/**
 * There is no password to ask for, so the confirmation is typing the account's
 * own address. It is not a secret — it is a speed bump, so that destroying
 * everything cannot be a misplaced click.
 */
export default function DeleteUser({ email }: { email: string }) {
    const emailInput = useRef<HTMLInputElement>(null);
    const [typed, setTyped] = useState('');

    return (
        <div className="space-y-5">
            <SectionHeading
                as="h2"
                size="sm"
                title="Delete account"
                description="Your account and everything Creeper collected for it."
            />

            <div className="space-y-4 border border-ribbon-red/35 bg-ribbon-red/8 p-4">
                <div className="space-y-0.5 text-ribbon-red">
                    <p className="label-mono uppercase">Warning</p>
                    <p className="text-sm">
                        Please proceed with caution — this cannot be undone.
                    </p>
                </div>

                <Dialog onOpenChange={() => setTyped('')}>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Delete your account?</DialogTitle>
                        <DialogDescription>
                            Every target, price history and run log goes with
                            it, permanently. Type your email address to confirm.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{ preserveScroll: true }}
                            onError={() => emailInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-5"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <Field
                                        label="Email address"
                                        htmlFor="delete_email"
                                        error={errors.email}
                                    >
                                        <Input
                                            id="delete_email"
                                            name="email"
                                            type="email"
                                            ref={emailInput}
                                            value={typed}
                                            onChange={(event) =>
                                                setTyped(event.target.value)
                                            }
                                            autoComplete="off"
                                            placeholder={email}
                                        />
                                    </Field>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                type="button"
                                                onClick={() => {
                                                    resetAndClearErrors();
                                                    setTyped('');
                                                }}
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            type="submit"
                                            disabled={
                                                processing ||
                                                typed.trim().toLowerCase() !==
                                                    email.toLowerCase()
                                            }
                                            data-test="confirm-delete-user-button"
                                        >
                                            Delete account
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}

import { Form, Head } from '@inertiajs/react';
import ApiKeyController from '@/actions/App/Http/Controllers/Settings/ApiKeyController';
import { Field, FormActions, SectionHeading } from '@/components/ds';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    hasKey: boolean;
    hint: string | null;
    required: boolean;
};

export default function ApiKey({ hasKey, hint, required }: Props) {
    return (
        <>
            <Head title="API key" />

            <h1 className="sr-only">API key settings</h1>

            <div className="space-y-5">
                <SectionHeading
                    as="h2"
                    size="sm"
                    title="API key"
                    description="Creeper reads pages with your key, so you pay your model provider directly."
                />

                {required && !hasKey && (
                    <Alert variant="warning">
                        <AlertTitle>Creeping is paused</AlertTitle>
                        <AlertDescription>
                            <p>
                                Your targets stay exactly as they are and pick
                                back up as soon as a key is on file.
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {hasKey && (
                    <div className="flex flex-wrap items-center justify-between gap-4 border border-rule bg-card px-4 py-3 shadow-xs">
                        <div>
                            <p className="label-micro text-muted-foreground">
                                Key on file
                            </p>
                            <p className="mt-1 font-mono text-sm tabular-nums">
                                ••••••••{hint}
                            </p>
                        </div>

                        <Form
                            {...ApiKeyController.destroy.form()}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Remove
                                </Button>
                            )}
                        </Form>
                    </div>
                )}

                <Form
                    {...ApiKeyController.update.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label={hasKey ? 'Replace key' : 'Add a key'}
                                htmlFor="api_key"
                                error={errors.api_key}
                                hint="Stored encrypted and only ever sent to the creeping agent. We never show it again after you save it."
                            >
                                <Input
                                    id="api_key"
                                    name="api_key"
                                    type="password"
                                    required
                                    autoComplete="off"
                                    spellCheck={false}
                                    className="font-mono text-sm"
                                    placeholder="sk-ant-..."
                                />
                            </Field>

                            <FormActions>
                                <Button type="submit" disabled={processing}>
                                    Save key
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

import { Form, Head } from '@inertiajs/react';
import ApiKeyController from '@/actions/App/Http/Controllers/Settings/ApiKeyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="API key"
                    description="Creeper reads pages with your key, so you pay your model provider directly"
                />

                {required && !hasKey && (
                    <p className="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                        Creeping is paused until you add a key. Your targets
                        stay exactly as they are and pick back up as soon as one
                        is on file.
                    </p>
                )}

                {hasKey && (
                    <div className="flex items-center justify-between gap-4 rounded-lg border border-border p-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Key on file
                            </p>
                            <p className="font-mono text-sm tabular-nums">
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
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="api_key">
                                    {hasKey ? 'Replace key' : 'Add a key'}
                                </Label>

                                <Input
                                    id="api_key"
                                    name="api_key"
                                    type="password"
                                    required
                                    autoComplete="off"
                                    spellCheck={false}
                                    className="mt-1 block w-full font-mono"
                                    placeholder="sk-ant-..."
                                />

                                <p className="text-xs text-muted-foreground">
                                    Stored encrypted and only ever sent to the
                                    creeping agent. We never show it again after
                                    you save it.
                                </p>

                                <InputError
                                    className="mt-2"
                                    message={errors.api_key}
                                />
                            </div>

                            <Button type="submit" disabled={processing}>
                                Save key
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

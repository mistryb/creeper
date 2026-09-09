import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ApiKeyController from '@/actions/App/Http/Controllers/Settings/ApiKeyController';
import { Field, FormActions, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ProviderOption = {
    value: string;
    label: string;
    placeholder: string;
};

type Props = {
    hasKey: boolean;
    hint: string | null;
    provider: string | null;
    providerLabel: string | null;
    providers: ProviderOption[];
};

export default function ApiKey({
    hasKey,
    hint,
    provider,
    providerLabel,
    providers,
}: Props) {
    const [selected, setSelected] = useState<string>(
        provider ?? providers[0]?.value ?? '',
    );

    const placeholder =
        providers.find((option) => option.value === selected)?.placeholder ??
        'sk-...';

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

                {hasKey && (
                    <div className="flex flex-wrap items-center justify-between gap-4 border border-rule bg-card px-4 py-3 shadow-xs">
                        <div>
                            <p className="label-micro text-muted-foreground">
                                Key on file
                            </p>
                            <p className="mt-1 font-mono text-sm tabular-nums">
                                {providerLabel ? `${providerLabel} · ` : ''}
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
                                label="Provider"
                                htmlFor="provider"
                                error={errors.provider}
                                hint="Where Creeper sends your key. Change it and the hint below follows."
                            >
                                <Select
                                    name="provider"
                                    value={selected}
                                    onValueChange={setSelected}
                                >
                                    <SelectTrigger
                                        id="provider"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {providers.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>

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
                                    placeholder={placeholder}
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

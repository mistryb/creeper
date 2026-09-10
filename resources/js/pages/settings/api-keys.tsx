import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import ApiKeyController from '@/actions/App/Http/Controllers/Settings/ApiKeyController';
import { EmptyLine, Field, FormActions, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ApiKeySummary } from '@/types';

type ProviderOption = {
    value: string;
    label: string;
    placeholder: string;
};

type Props = {
    keys: ApiKeySummary[];
    providers: ProviderOption[];
};

export default function ApiKeys({ keys, providers }: Props) {
    // The provider decides what a key is meant to look like, so the form holds
    // it in order to hint at the right shape.
    const [provider, setProvider] = useState<string>(providers[0]?.value ?? '');

    const placeholder =
        providers.find((option) => option.value === provider)?.placeholder ??
        'sk-...';

    return (
        <>
            <Head title="API keys" />

            <h1 className="sr-only">API key settings</h1>

            <div className="space-y-5">
                <SectionHeading
                    as="h2"
                    size="sm"
                    title="API keys"
                    description="Creeper reads pages with your keys, so you pay your model provider directly. Add as many as you like — each target says which one it spends."
                />

                <div className="border border-rule bg-card shadow-xs">
                    {keys.length === 0 ? (
                        <EmptyLine>No keys yet</EmptyLine>
                    ) : (
                        <ul>
                            {keys.map((key) => (
                                <li
                                    key={key.id}
                                    className="flex flex-wrap items-center justify-between gap-4 border-b border-rule px-4 py-3 last:border-b-0"
                                >
                                    <div>
                                        <p className="text-sm">{key.name}</p>
                                        <p className="mt-1 font-mono text-xs text-muted-foreground tabular-nums">
                                            {key.providerLabel} · ••••••••
                                            {key.hint} ·{' '}
                                            {key.targets === 1
                                                ? '1 target'
                                                : `${key.targets} targets`}
                                        </p>
                                    </div>

                                    <Form
                                        {...ApiKeyController.destroy.form(
                                            key.id,
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
                                                Remove
                                            </Button>
                                        )}
                                    </Form>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {keys.length > 0 && (
                    <p className="text-xs text-muted-foreground">
                        Removing a key pauses every target that was being crept
                        with it, so nothing fails quietly while you find
                        another.
                    </p>
                )}

                <Form
                    {...ApiKeyController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Name"
                                htmlFor="name"
                                error={errors.name}
                                hint="What you'll see when picking a key for a target."
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    maxLength={60}
                                    placeholder="Personal Anthropic"
                                />
                            </Field>

                            <Field
                                label="Provider"
                                htmlFor="provider"
                                error={errors.provider}
                                hint="Where Creeper sends this key. Change it and the hint below follows."
                            >
                                <Select
                                    name="provider"
                                    value={provider}
                                    onValueChange={setProvider}
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
                                label="Key"
                                htmlFor="api_key"
                                error={errors.api_key}
                                hint="Stored encrypted and only ever sent to the model provider. We never show it again after you save it."
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
                                    Add key
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

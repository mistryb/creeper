import { Form, Head, Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import CreepTargetController from '@/actions/App/Http/Controllers/CreepTargetController';
import {
    CheckField,
    EmptyState,
    Field,
    FormActions,
    Page,
    SectionHeading,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { creepTypeCopy } from '@/lib/creep-types';
import { index as apiKeysSettings } from '@/routes/api-keys';
import { create, index } from '@/routes/creep-targets';
import type { CreepType, CreepTypeOption, SelectOption } from '@/types';

export default function CreateCreepTarget({
    types,
    frequencies,
    apiKeys,
}: {
    types: CreepTypeOption[];
    frequencies: SelectOption[];
    apiKeys: SelectOption[];
}) {
    // The type decides what the rest of the form is asking for, so the page
    // holds it even though everything else on the form is uncontrolled.
    const [type, setType] = useState<CreepType>(types[0]?.value ?? 'product');
    const copy = creepTypeCopy(type);

    return (
        <>
            <Head title="New creep target" />

            <Page>
                <SectionHeading
                    title="New target"
                    note="Paste a URL — Creeper takes it from there"
                />

                <div className="max-w-xl space-y-6">
                    {/*
                     * Creeping is paid for with the user's own key, and there
                     * is none in the environment to fall back on, so a keyring
                     * with nothing on it means there is no form to fill in yet.
                     */}
                    {apiKeys.length === 0 ? (
                        <EmptyState
                            icon={KeyRound}
                            title="No API key yet"
                            actions={
                                <Button asChild>
                                    <Link href={apiKeysSettings()}>
                                        Add an API key
                                    </Link>
                                </Button>
                            }
                        >
                            Creeper reads pages with your own model API key, so
                            you pay your provider directly. Add one and come
                            back — you can keep several and choose which one
                            each target spends.
                        </EmptyState>
                    ) : (
                        <Form
                            {...CreepTargetController.store.form()}
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        label="What should Creeper watch?"
                                        htmlFor="type"
                                        error={errors.type}
                                        hint={copy.pitch}
                                    >
                                        <Select
                                            name="type"
                                            value={type}
                                            onValueChange={(value) =>
                                                setType(value as CreepType)
                                            }
                                        >
                                            <SelectTrigger
                                                id="type"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {types.map((option) => (
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
                                        label={copy.urlLabel}
                                        htmlFor="url"
                                        error={errors.url}
                                        hint={copy.urlHint}
                                    >
                                        <Input
                                            id="url"
                                            name="url"
                                            type="url"
                                            required
                                            autoFocus
                                            className="font-mono text-sm"
                                            placeholder={copy.urlPlaceholder}
                                        />
                                    </Field>

                                    <Field
                                        label="Name"
                                        htmlFor="name"
                                        optional
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            placeholder="What you want to call it"
                                        />
                                    </Field>

                                    <Field
                                        label="How often should Creeper check?"
                                        htmlFor="frequency"
                                        error={errors.frequency}
                                    >
                                        <Select
                                            name="frequency"
                                            defaultValue={
                                                frequencies.find(
                                                    (option) =>
                                                        option.value ===
                                                        'daily',
                                                )?.value ??
                                                frequencies[0]?.value
                                            }
                                        >
                                            <SelectTrigger
                                                id="frequency"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {frequencies.map((option) => (
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
                                        label="Which API key should pay for it?"
                                        htmlFor="api_key_id"
                                        error={errors.api_key_id}
                                        hint="Creeper spends this key every time it reads the page. Manage your keys in settings."
                                    >
                                        <Select
                                            name="api_key_id"
                                            defaultValue={apiKeys[0]?.value}
                                        >
                                            <SelectTrigger
                                                id="api_key_id"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {apiKeys.map((option) => (
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

                                    <CheckField
                                        htmlFor="notify_on_change"
                                        label="Email me when something changes"
                                        hint={copy.changeHint}
                                        control={
                                            <Checkbox
                                                id="notify_on_change"
                                                name="notify_on_change"
                                                value="1"
                                                defaultChecked
                                            />
                                        }
                                    />

                                    <FormActions>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Starting…'
                                                : 'Start creeping'}
                                        </Button>
                                    </FormActions>
                                </>
                            )}
                        </Form>
                    )}
                </div>
            </Page>
        </>
    );
}

CreateCreepTarget.layout = {
    breadcrumbs: [
        { title: 'Creep targets', href: index() },
        { title: 'New target', href: create() },
    ],
};

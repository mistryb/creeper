import { Form, Head } from '@inertiajs/react';
import CreepTargetController from '@/actions/App/Http/Controllers/CreepTargetController';
import {
    CheckField,
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
import { create, index } from '@/routes/creep-targets';
import type { SelectOption } from '@/types';

export default function CreateCreepTarget({
    frequencies,
}: {
    frequencies: SelectOption[];
}) {
    return (
        <>
            <Head title="New creep target" />

            <Page>
                <SectionHeading
                    title="New target"
                    note="Paste a product URL — Creeper takes it from there"
                />

                <div className="max-w-xl space-y-6">
                    <Form
                        {...CreepTargetController.store.form()}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    label="Product URL"
                                    htmlFor="url"
                                    error={errors.url}
                                    hint="The page for a single product, not a search or category listing."
                                >
                                    <Input
                                        id="url"
                                        name="url"
                                        type="url"
                                        required
                                        autoFocus
                                        className="font-mono text-sm"
                                        placeholder="https://example.com/products/kettle"
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
                                                    option.value === 'daily',
                                            )?.value ?? frequencies[0]?.value
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

                                <CheckField
                                    htmlFor="notify_on_change"
                                    label="Email me when something changes"
                                    hint="Price moves and stock flips only — not review counts."
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
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Starting…'
                                            : 'Start creeping'}
                                    </Button>
                                </FormActions>
                            </>
                        )}
                    </Form>
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

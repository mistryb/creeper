import { Form, Head } from '@inertiajs/react';
import CreepTargetController from '@/actions/App/Http/Controllers/CreepTargetController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    targetsRemaining,
}: {
    frequencies: SelectOption[];
    targetsRemaining: number | null;
}) {
    return (
        <>
            <Head title="New creep target" />

            <div className="px-4 py-6">
                <Heading
                    title="New creep target"
                    description="Paste a product URL. Creeper takes it from there."
                />

                <div className="max-w-xl">
                    {targetsRemaining !== null && (
                        <p className="mb-6 rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                            {targetsRemaining === 0
                                ? 'You have used every target on your plan.'
                                : `${targetsRemaining} target${targetsRemaining === 1 ? '' : 's'} left on your plan.`}
                        </p>
                    )}

                    <Form
                        {...CreepTargetController.store.form()}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="url">Product URL</Label>
                                    <Input
                                        id="url"
                                        name="url"
                                        type="url"
                                        required
                                        autoFocus
                                        placeholder="https://example.com/products/kettle"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The page for a single product, not a
                                        search or category listing.
                                    </p>
                                    <InputError message={errors.url} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        Name{' '}
                                        <span className="font-normal text-muted-foreground">
                                            (optional)
                                        </span>
                                    </Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        placeholder="What you want to call it"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="frequency">
                                        How often should Creeper check?
                                    </Label>
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
                                    <InputError message={errors.frequency} />
                                </div>

                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="notify_on_change"
                                        name="notify_on_change"
                                        value="1"
                                        defaultChecked
                                    />
                                    <div className="grid gap-1">
                                        <Label
                                            htmlFor="notify_on_change"
                                            className="font-normal"
                                        >
                                            Email me when something changes
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Price moves and stock flips only —
                                            not review counts.
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Starting…'
                                            : 'Start creeping'}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}

CreateCreepTarget.layout = {
    breadcrumbs: [
        { title: 'Creep targets', href: index() },
        { title: 'New target', href: create() },
    ],
};

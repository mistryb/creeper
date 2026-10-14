import { Link } from '@inertiajs/react';
import { Field } from '@/components/ds';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { index as apiKeys } from '@/routes/api-keys';
import type { Business, SelectOption } from '@/types';

/**
 * Everything a company is: what it is called, the owner's own account of it,
 * how often to write about its competitors, and which key pays. Shared by
 * the set-up form and the settings form so they ask the same questions the
 * same way.
 */
export function BusinessFields({
    business,
    frequencies,
    apiKeys: keys,
    errors,
    autoFocus = false,
}: {
    business?: Business;
    frequencies: SelectOption[];
    apiKeys: SelectOption[];
    errors: Partial<
        Record<
            'name' | 'url' | 'description' | 'digest_frequency' | 'api_key_id',
            string
        >
    >;
    autoFocus?: boolean;
}) {
    return (
        <>
            <Field label="Company name" htmlFor="name" error={errors.name}>
                <Input
                    id="name"
                    name="name"
                    required
                    autoFocus={autoFocus}
                    defaultValue={business?.name}
                    aria-invalid={errors.name ? true : undefined}
                    placeholder="Northwind Coffee"
                />
            </Field>

            <Field
                label="Website"
                htmlFor="url"
                optional
                error={errors.url}
                hint="Your own site. Creeper reads it before it goes looking for competitors, so it knows what it is finding rivals for."
            >
                <Input
                    id="url"
                    name="url"
                    type="url"
                    className="font-mono text-sm"
                    defaultValue={business?.url ?? ''}
                    aria-invalid={errors.url ? true : undefined}
                    placeholder="https://northwindcoffee.com"
                />
            </Field>

            <Field
                label="Describe your company"
                htmlFor="description"
                error={errors.description}
                hint="What you sell, who buys it, and what sets you apart. Competitors are found and written up against this, so the more specific the better."
            >
                <Textarea
                    id="description"
                    name="description"
                    required
                    rows={8}
                    maxLength={5000}
                    defaultValue={business?.description}
                    aria-invalid={errors.description ? true : undefined}
                    placeholder="A small-batch roaster selling single-origin coffee by subscription to home brewers in the UK. We compete on freshness — roasted to order, shipped within 48 hours — rather than on price."
                />
            </Field>

            <Field
                label="How often do you want the digest?"
                htmlFor="digest_frequency"
                error={errors.digest_frequency}
                hint="Each digest covers everything since the last one."
            >
                <Select
                    name="digest_frequency"
                    defaultValue={
                        business?.digest_frequency ?? frequencies[0]?.value
                    }
                >
                    <SelectTrigger id="digest_frequency" className="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {frequencies.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
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
                hint={
                    keys.length === 0 ? (
                        <>
                            You have no keys yet.{' '}
                            <Link
                                href={apiKeys()}
                                className="underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                            >
                                Add one in settings
                            </Link>{' '}
                            first: finding competitors and writing digests both
                            spend it.
                        </>
                    ) : (
                        'Finding competitors and writing every digest spends this key. Manage your keys in settings.'
                    )
                }
            >
                <Select
                    name="api_key_id"
                    defaultValue={business?.api_key_id ?? keys[0]?.value}
                    disabled={keys.length === 0}
                >
                    <SelectTrigger id="api_key_id" className="w-full">
                        <SelectValue placeholder="No keys yet" />
                    </SelectTrigger>
                    <SelectContent>
                        {keys.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
        </>
    );
}

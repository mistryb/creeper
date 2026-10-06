import { Field } from '@/components/ds';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Business } from '@/types';

/**
 * The two things a business is: what it is called, and the owner's own account
 * of it. Shared by the set-up form and the settings form so they ask the same
 * question the same way.
 */
export function BusinessFields({
    business,
    errors,
    autoFocus = false,
}: {
    business?: Business;
    errors: Partial<Record<'name' | 'url' | 'description', string>>;
    autoFocus?: boolean;
}) {
    return (
        <>
            <Field label="Business name" htmlFor="name" error={errors.name}>
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
                hint="Your own site, so Creeper can compare competitors against what you actually offer."
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
                label="Describe your business"
                htmlFor="description"
                error={errors.description}
                hint="What you sell, who buys it, and what sets you apart. Creeper reads every competitor against this, so the more specific the better."
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
        </>
    );
}

import { Field } from '@/components/ds';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Competitor } from '@/types';

/**
 * Who a competitor is. Shared by the add form and the settings form so they
 * ask the same questions the same way.
 */
export function CompetitorFields({
    competitor,
    errors,
    autoFocus = false,
}: {
    competitor?: Competitor;
    errors: Partial<Record<'name' | 'url' | 'description', string>>;
    autoFocus?: boolean;
}) {
    return (
        <>
            <Field label="Name" htmlFor="name" error={errors.name}>
                <Input
                    id="name"
                    name="name"
                    required
                    autoFocus={autoFocus}
                    defaultValue={competitor?.name}
                    aria-invalid={errors.name ? true : undefined}
                    placeholder="Acme Roasters"
                />
            </Field>

            <Field
                label="Website"
                htmlFor="url"
                optional
                error={errors.url}
                hint="Read when you analyse them."
            >
                <Input
                    id="url"
                    name="url"
                    type="url"
                    className="font-mono text-sm"
                    defaultValue={competitor?.url ?? ''}
                    aria-invalid={errors.url ? true : undefined}
                    placeholder="https://acmeroasters.com"
                />
            </Field>

            <Field
                label="What do you know about them?"
                htmlFor="description"
                optional
                error={errors.description}
                hint="Who they sell to, how they compete with you, anything you have heard. It sharpens their analysis."
            >
                <Textarea
                    id="description"
                    name="description"
                    rows={6}
                    maxLength={5000}
                    defaultValue={competitor?.description ?? ''}
                    aria-invalid={errors.description ? true : undefined}
                    placeholder="The big national roaster. Cheaper than us, sold in supermarkets, and just launched a subscription."
                />
            </Field>
        </>
    );
}

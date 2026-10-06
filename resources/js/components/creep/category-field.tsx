import { Field } from '@/components/ds';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { PageCategory, SelectOption } from '@/types';

/**
 * What kind of page this is, so the dashboard can tell a price move from a
 * release from a change of message. Submitted as `category`.
 */
export function CategoryField({
    categories,
    value,
    onChange,
    error,
}: {
    categories: SelectOption[];
    value: PageCategory;
    onChange: (category: PageCategory) => void;
    error?: string;
}) {
    return (
        <Field
            label="What kind of page is this?"
            htmlFor="category"
            error={error}
            hint="Used to sort this competitor's changes on the dashboard."
        >
            <Select
                name="category"
                value={value}
                onValueChange={(next) => onChange(next as PageCategory)}
            >
                <SelectTrigger id="category" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {categories.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

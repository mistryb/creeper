import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * One line of a form: stamped caption, the control, the hint under it, then the
 * machine's objection if there is one. Everything a field needs in a fixed
 * order, so no two forms in the app stack these four pieces differently.
 *
 * Pass `htmlFor` matching the control's `id` — the label is not much use
 * otherwise.
 */
export function Field({
    label,
    htmlFor,
    hint,
    error,
    optional = false,
    className,
    children,
}: {
    label: ReactNode;
    htmlFor?: string;
    /** What a good answer looks like, or what the field is for. */
    hint?: ReactNode;
    error?: string;
    optional?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-1.5', className)}>
            <Label htmlFor={htmlFor}>
                {label}
                {optional && (
                    <span className="text-muted-foreground/70">
                        {' '}
                        (optional)
                    </span>
                )}
            </Label>

            {children}

            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}

            <InputError message={error} />
        </div>
    );
}

/**
 * A checkbox or radio and the sentence beside it. The sentence is sans, not a
 * stamped caption, because it has to read as something you can agree to.
 */
export function CheckField({
    control,
    label,
    htmlFor,
    hint,
    error,
}: {
    control: ReactNode;
    label: ReactNode;
    htmlFor?: string;
    hint?: ReactNode;
    error?: string;
}) {
    return (
        <div className="flex items-start gap-3">
            {control}
            <div className="grid gap-1">
                <Label variant="inline" htmlFor={htmlFor}>
                    {label}
                </Label>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
                <InputError message={error} />
            </div>
        </div>
    );
}

/**
 * The bar a form ends on: a rule, then the commit action on the left and any
 * destructive escape hatch pushed to the right.
 */
export function FormActions({
    children,
    aside,
    className,
}: {
    children: ReactNode;
    aside?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3 border-t border-rule pt-5',
                className,
            )}
        >
            <div className="flex flex-wrap items-center gap-3">{children}</div>
            {aside}
        </div>
    );
}

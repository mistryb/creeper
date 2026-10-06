import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * The multi-line sibling of `Input`: the same keyed box, pressed into the page
 * the same way, for answers written in sentences rather than a word or two.
 */
function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'flex min-h-32 w-full min-w-0 border border-input bg-white px-3 py-2 text-base transition-shadow outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground/70 disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-secondary disabled:opacity-60 md:text-sm',
                'focus-visible:shadow-[inset_0_0_0_1px_var(--color-ink)]',
                'aria-invalid:border-destructive aria-invalid:focus-visible:shadow-[inset_0_0_0_1px_var(--color-ribbon-red)]',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };

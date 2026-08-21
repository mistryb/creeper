import * as LabelPrimitive from '@radix-ui/react-label';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * Two jobs, two looks. A `field` label is the caption stamped above an input —
 * quiet, mono, small, because on a printed form the label is the caption and
 * the answer is the content. An `inline` label sits beside a checkbox or a
 * radio and has to read as a sentence, so it stays in sans.
 */
function Label({
    className,
    variant = 'field',
    ...props
}: React.ComponentProps<typeof LabelPrimitive.Root> & {
    variant?: 'field' | 'inline';
}) {
    return (
        <LabelPrimitive.Root
            data-slot="label"
            className={cn(
                'select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
                variant === 'field'
                    ? 'label-micro text-ink-soft'
                    : 'text-sm leading-snug font-normal',
                className,
            )}
            {...props}
        />
    );
}

export { Label };

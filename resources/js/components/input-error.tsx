import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * A validation message, set in mono like the field label above it, so a
 * rejected field reads as the machine answering back.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            className={cn(
                'font-mono text-xs tracking-[0.02em] text-ribbon-red',
                className,
            )}
        >
            {message}
        </p>
    ) : null;
}

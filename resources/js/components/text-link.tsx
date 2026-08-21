import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type Props = ComponentProps<typeof Link>;

/**
 * A link in running prose: underlined in rule grey, inked green on hover. The
 * underline is always present, so a link is never colour alone.
 */
export default function TextLink({
    className = '',
    children,
    ...props
}: Props) {
    return (
        <Link
            className={cn(
                'text-foreground underline decoration-rule decoration-1 underline-offset-4 transition-colors hover:text-ribbon hover:decoration-ribbon',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}

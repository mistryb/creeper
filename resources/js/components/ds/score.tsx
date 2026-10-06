import { cn } from '@/lib/utils';

/**
 * A judgement out of five, punched as a row of dots: filled for the score,
 * hollow for the rest. Always paired with the figure, so the dots are a quick
 * read rather than the only one.
 */
export function Score({
    value,
    out = 5,
    className,
}: {
    value: number;
    out?: number;
    className?: string;
}) {
    return (
        <span
            className={cn('inline-flex items-center gap-1', className)}
            aria-label={`${value} out of ${out}`}
        >
            <span aria-hidden className="inline-flex gap-0.5">
                {Array.from({ length: out }, (_, index) => (
                    <i
                        key={index}
                        className={cn(
                            'size-1.5 rounded-full border border-ribbon',
                            index < value && 'bg-ribbon',
                        )}
                    />
                ))}
            </span>
            <span
                aria-hidden
                className="font-mono text-[0.6875rem] text-muted-foreground tabular-nums"
            >
                {value}/{out}
            </span>
        </span>
    );
}

import { Link } from '@inertiajs/react';
import { Score } from '@/components/ds';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { show as showCompetitor } from '@/routes/competitors';
import type { LandscapeReport } from '@/types';

/**
 * You and every competitor, side by side on the same dimensions. Facts are
 * set in mono because a page printed them; judgements carry a score and say
 * so in the column head, so nobody mistakes the model's opinion for a price.
 */
export function ComparisonMatrix({ report }: { report: LandscapeReport }) {
    return (
        <div className="overflow-x-auto">
            <Table>
                <TableCaption className="sr-only">
                    How you compare with each competitor
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead scope="col" className="min-w-40">
                            Company
                        </TableHead>
                        {report.dimensions.map((dimension) => (
                            <TableHead
                                key={dimension.name}
                                scope="col"
                                className="min-w-36 align-bottom"
                            >
                                <Tooltip>
                                    <TooltipTrigger className="text-left">
                                        {dimension.name}
                                        <span className="block font-mono text-[0.625rem] font-normal tracking-[0.08em] text-muted-foreground normal-case">
                                            {dimension.kind === 'fact'
                                                ? 'from their pages'
                                                : 'judgement'}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {dimension.description}
                                    </TooltipContent>
                                </Tooltip>
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {report.rows.map((row) => (
                        <TableRow
                            key={row.subject}
                            className={cn(row.is_you && 'bg-greenbar/60')}
                        >
                            <TableCell className="align-top">
                                {row.competitor_id ? (
                                    <Link
                                        href={showCompetitor(row.competitor_id)}
                                        className="font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                    >
                                        {row.name}
                                    </Link>
                                ) : (
                                    <span className="font-medium">
                                        {row.name}
                                        <span className="ml-1.5 label-micro text-ribbon">
                                            You
                                        </span>
                                    </span>
                                )}
                                {row.confidence === 'low' && (
                                    <p className="font-mono text-[0.6875rem] tracking-[0.04em] text-ribbon-amber">
                                        little known — low confidence
                                    </p>
                                )}
                            </TableCell>
                            {row.cells.map((cell, index) => {
                                const isFact =
                                    report.dimensions[index]?.kind === 'fact';

                                return (
                                    <TableCell
                                        key={cell.dimension}
                                        className="align-top whitespace-normal"
                                    >
                                        <span
                                            className={cn(
                                                'text-sm',
                                                isFact && 'font-mono',
                                                cell.value === 'Unknown' &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            {cell.value}
                                        </span>
                                        {cell.score !== null && (
                                            <Score
                                                value={cell.score}
                                                className="mt-1 flex"
                                            />
                                        )}
                                    </TableCell>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

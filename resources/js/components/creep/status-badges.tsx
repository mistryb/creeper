import {
    CheckCircle2,
    CircleDashed,
    CircleSlash,
    Clock,
    LoaderCircle,
    PauseCircle,
    TriangleAlert,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { Availability, RunStatus, TargetStatus } from '@/types';

/**
 * Every badge pairs its colour with an icon and a word, so state is never
 * carried by colour alone.
 */

export function AvailabilityBadge({
    availability,
    label,
}: {
    availability: Availability;
    label: string;
}) {
    const config = {
        in_stock: {
            variant: 'outline' as const,
            className:
                'border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            Icon: CheckCircle2,
        },
        out_of_stock: {
            variant: 'outline' as const,
            className:
                'border-rose-600/30 bg-rose-500/10 text-rose-700 dark:text-rose-400',
            Icon: CircleSlash,
        },
        preorder: {
            variant: 'outline' as const,
            className:
                'border-amber-600/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
            Icon: Clock,
        },
        unknown: {
            variant: 'secondary' as const,
            className: '',
            Icon: CircleDashed,
        },
    }[availability];

    return (
        <Badge variant={config.variant} className={config.className}>
            <config.Icon aria-hidden />
            {label}
        </Badge>
    );
}

export function TargetStatusBadge({
    status,
    label,
}: {
    status: TargetStatus;
    label: string;
}) {
    const config = {
        active: { variant: 'secondary' as const, Icon: CheckCircle2 },
        paused: { variant: 'outline' as const, Icon: PauseCircle },
        failed: { variant: 'destructive' as const, Icon: TriangleAlert },
    }[status];

    return (
        <Badge variant={config.variant}>
            <config.Icon aria-hidden />
            {label}
        </Badge>
    );
}

export function RunStatusBadge({
    status,
    label,
}: {
    status: RunStatus;
    label: string;
}) {
    const config = {
        queued: { variant: 'outline' as const, Icon: CircleDashed },
        running: { variant: 'outline' as const, Icon: LoaderCircle },
        succeeded: { variant: 'secondary' as const, Icon: CheckCircle2 },
        failed: { variant: 'destructive' as const, Icon: TriangleAlert },
    }[status];

    return (
        <Badge variant={config.variant}>
            <config.Icon
                aria-hidden
                className={status === 'running' ? 'animate-spin' : undefined}
            />
            {label}
        </Badge>
    );
}

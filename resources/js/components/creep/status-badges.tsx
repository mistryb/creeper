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
 * Every badge pairs its ribbon colour with an icon and a word, so state is
 * never carried by colour alone. The tones come from Badge's variants rather
 * than from ad-hoc classes: green is ordinary, amber wants attention, red is
 * damage, grey is "not known yet".
 */

export function AvailabilityBadge({
    availability,
    label,
}: {
    availability: Availability;
    label: string;
}) {
    const config = {
        in_stock: { variant: 'ok' as const, Icon: CheckCircle2 },
        out_of_stock: { variant: 'bad' as const, Icon: CircleSlash },
        preorder: { variant: 'warn' as const, Icon: Clock },
        unknown: { variant: 'muted' as const, Icon: CircleDashed },
    }[availability];

    return (
        <Badge variant={config.variant}>
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
        active: { variant: 'ok' as const, Icon: CheckCircle2 },
        paused: { variant: 'muted' as const, Icon: PauseCircle },
        failed: { variant: 'bad' as const, Icon: TriangleAlert },
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
        queued: { variant: 'muted' as const, Icon: CircleDashed },
        running: { variant: 'warn' as const, Icon: LoaderCircle },
        succeeded: { variant: 'ok' as const, Icon: CheckCircle2 },
        failed: { variant: 'bad' as const, Icon: TriangleAlert },
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

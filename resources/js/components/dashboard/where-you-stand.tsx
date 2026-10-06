import { Form, Link } from '@inertiajs/react';
import { KeyRound, LoaderCircle, Scale } from 'lucide-react';
import LandscapeController from '@/actions/App/Http/Controllers/LandscapeController';
import { EmptyState, Panel, PanelBar } from '@/components/ds';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatRelative } from '@/lib/format';
import { index as apiKeysSettings } from '@/routes/api-keys';
import type { Business, LandscapeAnalysis, SelectOption } from '@/types';

/**
 * The top of the dashboard: where the business stands against everyone it
 * competes with, and what to do about it — plus the button that asks again.
 */
export function WhereYouStand({
    business,
    landscape,
    latestRun,
    isComparing,
    apiKeys,
    hasCompetitors,
}: {
    business: Business;
    /** The newest comparison that succeeded. */
    landscape: LandscapeAnalysis | null;
    /** The newest comparison of any kind, to explain a failure since. */
    latestRun: LandscapeAnalysis | null;
    isComparing: boolean;
    apiKeys: SelectOption[];
    hasCompetitors: boolean;
}) {
    const failedSince =
        latestRun?.status === 'failed' && latestRun.id !== landscape?.id
            ? latestRun
            : null;

    const run = (
        <RunComparison
            business={business}
            apiKeys={apiKeys}
            isComparing={isComparing}
            hasRun={landscape !== null}
            disabled={!hasCompetitors}
        />
    );

    return (
        <div className="space-y-4">
            {failedSince && (
                <Alert variant="destructive">
                    <AlertTitle>The last comparison failed</AlertTitle>
                    <AlertDescription>
                        <p>{failedSince.error}</p>
                    </AlertDescription>
                </Alert>
            )}

            {landscape?.report ? (
                <Panel>
                    <PanelBar
                        title="Where you stand"
                        meta={`compared ${formatRelative(landscape.finished_at)}`}
                    />
                    <div className="grid gap-6 p-5 lg:grid-cols-5">
                        <div className="space-y-4 lg:col-span-3">
                            <p className="max-w-prose text-base leading-relaxed">
                                {landscape.report.summary}
                            </p>
                            {run}
                        </div>

                        {landscape.report.actions.length > 0 && (
                            <div className="space-y-2 lg:col-span-2">
                                <p className="label-micro text-muted-foreground">
                                    Worth doing next
                                </p>
                                <ol className="space-y-2.5 text-sm leading-relaxed">
                                    {landscape.report.actions.map(
                                        (action, index) => (
                                            <li
                                                key={action}
                                                className="flex gap-3"
                                            >
                                                <span className="font-mono text-xs text-ribbon tabular-nums">
                                                    {index + 1}.
                                                </span>
                                                {action}
                                            </li>
                                        ),
                                    )}
                                </ol>
                            </div>
                        )}
                    </div>
                </Panel>
            ) : (
                <EmptyState
                    icon={isComparing ? LoaderCircle : Scale}
                    title={isComparing ? 'Comparing' : 'Not compared yet'}
                    actions={
                        apiKeys.length === 0 ? (
                            <Button asChild>
                                <Link href={apiKeysSettings()}>
                                    <KeyRound aria-hidden />
                                    Add an API key
                                </Link>
                            </Button>
                        ) : (
                            run
                        )
                    }
                >
                    {isComparing
                        ? 'Reading everything Creeper knows about you and your competitors. This takes a minute or two; the page updates by itself.'
                        : hasCompetitors
                          ? `Creeper sets ${business.name} against every competitor on the dimensions that matter in your market, and says where you stand.`
                          : 'Add a competitor first — there is nobody to compare against yet.'}
                </EmptyState>
            )}
        </div>
    );
}

function RunComparison({
    business,
    apiKeys,
    isComparing,
    hasRun,
    disabled,
}: {
    business: Business;
    apiKeys: SelectOption[];
    isComparing: boolean;
    hasRun: boolean;
    disabled: boolean;
}) {
    if (apiKeys.length === 0) {
        return null;
    }

    return (
        <Form
            {...LandscapeController.store.form(business.id)}
            options={{ preserveScroll: true }}
            className="flex flex-wrap items-center gap-2"
        >
            {({ processing }) => (
                <>
                    {apiKeys.length > 1 ? (
                        <Select
                            name="api_key_id"
                            defaultValue={apiKeys[0].value}
                        >
                            <SelectTrigger
                                aria-label="API key to spend"
                                className="w-64"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {apiKeys.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    ) : (
                        <input
                            type="hidden"
                            name="api_key_id"
                            value={apiKeys[0].value}
                        />
                    )}

                    <Button
                        type="submit"
                        variant={hasRun ? 'outline' : 'default'}
                        disabled={processing || isComparing || disabled}
                    >
                        {isComparing ? (
                            <LoaderCircle
                                aria-hidden
                                className="animate-spin"
                            />
                        ) : (
                            <Scale aria-hidden />
                        )}
                        {isComparing
                            ? 'Comparing…'
                            : hasRun
                              ? 'Compare again'
                              : 'Compare'}
                    </Button>
                </>
            )}
        </Form>
    );
}

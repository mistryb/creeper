import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { ExternalLink, FileSearch, Plus, ScanSearch } from 'lucide-react';
import CompetitorController from '@/actions/App/Http/Controllers/CompetitorController';
import { CompetitorFields } from '@/components/competitor/competitor-fields';
import { LatestReading } from '@/components/creep/latest-reading';
import { PauseButton } from '@/components/creep/pause-button';
import { PageStatusBadge } from '@/components/creep/status-badges';
import { EmptyState, FormActions, Page, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatRelative, hostOf } from '@/lib/format';
import { index as competitorsIndex } from '@/routes/businesses/competitors';
import { show } from '@/routes/competitors';
import { index as analysis } from '@/routes/competitors/analysis';
import { create as createPage } from '@/routes/competitors/watched-pages';
import { show as showPage } from '@/routes/watched-pages';
import type {
    Business,
    Competitor,
    ResourceCollection,
    WatchedPage,
} from '@/types';

export default function ShowCompetitor({
    business: { data: business },
    competitor: { data: competitor },
    watchedPages,
}: {
    business: { data: Business };
    competitor: { data: Competitor };
    watchedPages: ResourceCollection<WatchedPage>;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Competitors', href: competitorsIndex(business.id) },
            { title: competitor.name, href: show(competitor.id) },
        ],
    });

    return (
        <>
            <Head title={competitor.name} />

            <Page>
                <SectionHeading
                    title={competitor.name}
                    note={`Competitor of ${business.name}`}
                    description={
                        competitor.url && (
                            <a
                                href={competitor.url}
                                target="_blank"
                                rel="noreferrer noopener"
                                className="inline-flex items-center gap-1.5 font-mono text-xs tracking-[0.04em] text-muted-foreground underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                            >
                                {hostOf(competitor.url)}
                                <ExternalLink aria-hidden className="size-3" />
                            </a>
                        )
                    }
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={analysis(competitor.id)} prefetch>
                                    <ScanSearch aria-hidden />
                                    Analysis
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={createPage(competitor.id)}>
                                    <Plus aria-hidden />
                                    Watch a page
                                </Link>
                            </Button>
                        </>
                    }
                />

                <WatchedPages
                    competitor={competitor}
                    watchedPages={watchedPages.data}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>About {competitor.name}</CardTitle>
                        <CardDescription>
                            Read alongside their website when you analyse them.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...CompetitorController.update.form(competitor.id)}
                            options={{ preserveScroll: true }}
                            className="max-w-xl space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <CompetitorFields
                                        competitor={competitor}
                                        errors={errors}
                                    />

                                    <FormActions
                                        aside={
                                            <DeleteCompetitor
                                                competitor={competitor}
                                            />
                                        }
                                    >
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save changes
                                        </Button>
                                    </FormActions>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </Page>
        </>
    );
}

function WatchedPages({
    competitor,
    watchedPages,
}: {
    competitor: Competitor;
    watchedPages: WatchedPage[];
}) {
    if (watchedPages.length === 0) {
        return (
            <EmptyState
                icon={FileSearch}
                title="No pages watched"
                actions={
                    <Button asChild>
                        <Link href={createPage(competitor.id)}>
                            <Plus aria-hidden />
                            Watch a page
                        </Link>
                    </Button>
                }
            >
                Point Creeper at {competitor.name}&rsquo;s pricing page, a
                product, or their changelog. It reads it on a schedule and tells
                you when something moves.
            </EmptyState>
        );
    }

    return (
        <Table>
            <TableCaption className="sr-only">
                Pages of {competitor.name}&rsquo;s being watched
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead scope="col">Page</TableHead>
                    <TableHead scope="col">Latest</TableHead>
                    <TableHead scope="col">Status</TableHead>
                    <TableHead scope="col">Last crept</TableHead>
                    <TableHead scope="col">
                        <span className="sr-only">Pause or resume</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {watchedPages.map((watchedPage) => (
                    <TableRow key={watchedPage.id}>
                        <TableCell>
                            <Link
                                href={showPage(watchedPage.id)}
                                className="font-medium underline decoration-transparent underline-offset-4 hover:decoration-ribbon"
                                prefetch
                            >
                                {watchedPage.display_name}
                            </Link>
                            <p className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                                {hostOf(watchedPage.url)}
                                <span aria-hidden> · </span>
                                {watchedPage.frequency_label.toLowerCase()}
                            </p>
                        </TableCell>
                        <TableCell>
                            <LatestReading watchedPage={watchedPage} />
                        </TableCell>
                        <TableCell>
                            <PageStatusBadge
                                status={watchedPage.status}
                                label={watchedPage.status_label}
                            />
                        </TableCell>
                        <TableCell className="font-mono text-[0.6875rem] tracking-[0.04em] text-muted-foreground">
                            {formatRelative(watchedPage.last_crept_at)}
                        </TableCell>
                        <TableCell className="text-right">
                            <PauseButton
                                watchedPage={watchedPage}
                                variant="ghost"
                                compact
                            />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function DeleteCompetitor({ competitor }: { competitor: Competitor }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" className="hover:text-ribbon-red">
                    Delete competitor
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {competitor.name}?</DialogTitle>
                <DialogDescription>
                    Every page watched on them, everything Creeper found, and
                    every analysis goes with them. This cannot be undone.
                </DialogDescription>

                <Form {...CompetitorController.destroy.form(competitor.id)}>
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                type="submit"
                                disabled={processing}
                            >
                                Delete for good
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

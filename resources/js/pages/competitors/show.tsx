import { Form, Head, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import CompetitorController from '@/actions/App/Http/Controllers/CompetitorController';
import { CompetitorFields } from '@/components/competitor/competitor-fields';
import { FormActions, Page, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { formatRelative, hostOf } from '@/lib/format';
import { index as competitorsIndex } from '@/routes/businesses/competitors';
import type { Business, Competitor } from '@/types';

export default function ShowCompetitor({
    business: { data: business },
    competitor: { data: competitor },
}: {
    business: { data: Business };
    competitor: { data: Competitor };
}) {
    return (
        <>
            <Head title={competitor.name} />

            <Page>
                <p className="label-micro text-muted-foreground">
                    <Link
                        href={competitorsIndex(business.id)}
                        className="hover:text-ink"
                    >
                        ← Competitors
                    </Link>
                </p>

                <SectionHeading
                    title={competitor.name}
                    note={
                        competitor.discovered_at
                            ? `Found by Creeper ${formatRelative(competitor.discovered_at)}`
                            : `Competitor of ${business.name}, added by you`
                    }
                    description={
                        <div className="space-y-2">
                            {competitor.url && (
                                <a
                                    href={competitor.url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="inline-flex items-center gap-1.5 font-mono text-xs tracking-[0.04em] text-muted-foreground underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                                >
                                    {hostOf(competitor.url)}
                                    <ExternalLink
                                        aria-hidden
                                        className="size-3"
                                    />
                                </a>
                            )}
                            {competitor.reason && (
                                <p className="max-w-2xl text-sm text-muted-foreground">
                                    {competitor.reason}
                                </p>
                            )}
                        </div>
                    }
                />

                <section className="space-y-5 border-t border-rule pt-6">
                    <div className="space-y-1">
                        <h2 className="label-micro text-ink">
                            About {competitor.name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Handed to the model every time a digest is written,
                            so it knows what to look for.
                        </p>
                    </div>
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
                                    <Button type="submit" disabled={processing}>
                                        Save changes
                                    </Button>
                                </FormActions>
                            </>
                        )}
                    </Form>
                </section>
            </Page>
        </>
    );
}

function DeleteCompetitor({ competitor }: { competitor: Competitor }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" className="hover:text-ribbon-red">
                    Remove competitor
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Remove {competitor.name}?</DialogTitle>
                <DialogDescription>
                    They drop out of every digest from now on. Digests already
                    sent are kept. This cannot be undone.
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
                                Remove
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

import { Form, Head } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import BusinessController from '@/actions/App/Http/Controllers/BusinessController';
import { BusinessFields } from '@/components/business/business-fields';
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
import { formatDateTime, hostOf } from '@/lib/format';
import type { Business, SelectOption } from '@/types';

export default function ShowBusiness({
    business: { data: business },
    frequencies,
    apiKeys,
}: {
    business: { data: Business };
    frequencies: SelectOption[];
    apiKeys: SelectOption[];
}) {
    return (
        <>
            <Head title={business.name} />

            <Page>
                <SectionHeading
                    title={business.name}
                    note={`Set up ${formatDateTime(business.created_at)}`}
                    description={
                        business.url && (
                            <a
                                href={business.url}
                                target="_blank"
                                rel="noreferrer noopener"
                                className="inline-flex items-center gap-1.5 font-mono text-xs tracking-[0.04em] text-muted-foreground underline decoration-rule underline-offset-4 hover:text-ribbon hover:decoration-ribbon"
                            >
                                {hostOf(business.url)}
                                <ExternalLink aria-hidden className="size-3" />
                            </a>
                        )
                    }
                />

                <section className="space-y-5 border-t border-rule pt-6">
                    <div className="space-y-1">
                        <h2 className="label-micro text-ink">
                            About this company
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Competitors are found and written up against this
                            description. Changing the cadence moves the next
                            digest.
                        </p>
                    </div>
                    <Form
                        {...BusinessController.update.form(business.id)}
                        options={{ preserveScroll: true }}
                        className="max-w-xl space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <BusinessFields
                                    business={business}
                                    frequencies={frequencies}
                                    apiKeys={apiKeys}
                                    errors={errors}
                                />

                                <FormActions
                                    aside={
                                        <DeleteBusiness business={business} />
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

function DeleteBusiness({ business }: { business: Business }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" className="hover:text-ribbon-red">
                    Delete company
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {business.name}?</DialogTitle>
                <DialogDescription>
                    Its competitors and every digest written about them go for
                    good. This cannot be undone.
                </DialogDescription>

                <Form {...BusinessController.destroy.form(business.id)}>
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

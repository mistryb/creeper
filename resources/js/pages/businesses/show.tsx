import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import BusinessController from '@/actions/App/Http/Controllers/BusinessController';
import { BusinessFields } from '@/components/business/business-fields';
import { FormActions, Page, SectionHeading } from '@/components/ds';
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
import { formatDateTime, hostOf } from '@/lib/format';
import { index, show } from '@/routes/businesses';
import type { Business } from '@/types';

export default function ShowBusiness({
    business: { data: business },
}: {
    business: { data: Business };
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Businesses', href: index() },
            { title: business.name, href: show(business.id) },
        ],
    });

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

                <Card>
                    <CardHeader>
                        <CardTitle>About this business</CardTitle>
                        <CardDescription>
                            Creeper reads every competitor against this
                            description.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...BusinessController.update.form(business.id)}
                            options={{ preserveScroll: true }}
                            className="max-w-xl space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <BusinessFields
                                        business={business}
                                        errors={errors}
                                    />

                                    <FormActions
                                        aside={
                                            <DeleteBusiness
                                                business={business}
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

function DeleteBusiness({ business }: { business: Business }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="ghost" className="hover:text-ribbon-red">
                    Delete business
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Delete {business.name}?</DialogTitle>
                <DialogDescription>
                    Its name and description go for good. This cannot be undone.
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

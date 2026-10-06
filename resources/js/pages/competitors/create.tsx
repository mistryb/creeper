import { Form, Head, setLayoutProps } from '@inertiajs/react';
import CompetitorController from '@/actions/App/Http/Controllers/CompetitorController';
import { CompetitorFields } from '@/components/competitor/competitor-fields';
import { FormActions, Page, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/businesses/competitors';
import type { Business } from '@/types';

export default function CreateCompetitor({
    business: { data: business },
}: {
    business: { data: Business };
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Competitors', href: index(business.id) },
            { title: 'New competitor', href: create(business.id) },
        ],
    });

    return (
        <>
            <Head title="New competitor" />

            <Page>
                <SectionHeading
                    title="New competitor"
                    note={`Somebody ${business.name} is up against`}
                />

                <Form
                    {...CompetitorController.store.form(business.id)}
                    className="max-w-xl space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <CompetitorFields errors={errors} autoFocus />

                            <FormActions>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding…' : 'Add competitor'}
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>
            </Page>
        </>
    );
}

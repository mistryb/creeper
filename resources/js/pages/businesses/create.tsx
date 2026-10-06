import { Form, Head } from '@inertiajs/react';
import BusinessController from '@/actions/App/Http/Controllers/BusinessController';
import { BusinessFields } from '@/components/business/business-fields';
import { FormActions, Page, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/businesses';

export default function CreateBusiness() {
    return (
        <>
            <Head title="New business" />

            <Page>
                <SectionHeading
                    title="New business"
                    note="Tell Creeper who you are before it watches who you're up against"
                />

                <Form
                    {...BusinessController.store.form()}
                    className="max-w-xl space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <BusinessFields errors={errors} autoFocus />

                            <FormActions>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Setting up…'
                                        : 'Set up business'}
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>
            </Page>
        </>
    );
}

CreateBusiness.layout = {
    breadcrumbs: [
        { title: 'Businesses', href: index() },
        { title: 'New business', href: create() },
    ],
};

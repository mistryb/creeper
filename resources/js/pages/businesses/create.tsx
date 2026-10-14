import { Form, Head } from '@inertiajs/react';
import BusinessController from '@/actions/App/Http/Controllers/BusinessController';
import { BusinessFields } from '@/components/business/business-fields';
import { FormActions, Page, SectionHeading } from '@/components/ds';
import { Button } from '@/components/ui/button';
import type { SelectOption } from '@/types';

export default function CreateBusiness({
    frequencies,
    apiKeys,
}: {
    frequencies: SelectOption[];
    apiKeys: SelectOption[];
}) {
    return (
        <>
            <Head title="New company" />

            <Page>
                <SectionHeading
                    title="New company"
                    note="Tell Creeper who you are. It finds who you're up against."
                />

                <Form
                    {...BusinessController.store.form()}
                    className="max-w-xl space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <BusinessFields
                                frequencies={frequencies}
                                apiKeys={apiKeys}
                                errors={errors}
                                autoFocus
                            />

                            <FormActions>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || apiKeys.length === 0
                                    }
                                >
                                    {processing
                                        ? 'Setting up…'
                                        : 'Set up and find competitors'}
                                </Button>
                            </FormActions>
                        </>
                    )}
                </Form>
            </Page>
        </>
    );
}

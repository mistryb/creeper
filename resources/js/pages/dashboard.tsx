import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Page } from '@/components/ds';
import { Button } from '@/components/ui/button';
import { create as createBusiness } from '@/routes/businesses';

/**
 * Only seen before there is a company: signing in with one goes straight to
 * its digests.
 */
export default function Dashboard() {
    return (
        <>
            <Head title="Welcome" />

            <Page className="flex flex-col items-center pt-10 text-center">
                <h1 className="display-dot text-3xl">Start with you</h1>
                <p className="max-w-md text-muted-foreground">
                    Describe your company. Creeper finds who you are up against,
                    and sends you a digest of what they are doing.
                </p>
                <Button asChild>
                    <Link href={createBusiness()}>
                        <Plus aria-hidden />
                        Set up your company
                    </Link>
                </Button>
            </Page>
        </>
    );
}

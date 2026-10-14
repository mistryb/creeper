import { Form } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { RouteDefinition } from '@/wayfinder';

/**
 * The search box over an admin list. A plain GET, so the query lands in the
 * URL and a search can be bookmarked or shared.
 */
export function SearchForm({
    action,
    search,
    placeholder,
}: {
    action: RouteDefinition<'get'>;
    search: string;
    placeholder: string;
}) {
    return (
        <Form action={action} className="flex gap-2" role="search">
            {({ processing }) => (
                <>
                    <Input
                        name="search"
                        type="search"
                        defaultValue={search}
                        placeholder={placeholder}
                        aria-label="Search"
                    />
                    <Button
                        type="submit"
                        variant="secondary"
                        disabled={processing}
                    >
                        <Search aria-hidden />
                        Search
                    </Button>
                </>
            )}
        </Form>
    );
}

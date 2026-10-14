import { Head } from '@inertiajs/react';
import { SearchForm } from '@/components/admin/search-form';
import { EmptyLine, Pager } from '@/components/ds';
import { formatRelative, hostOf } from '@/lib/format';
import { index } from '@/routes/admin/businesses';
import type { AdminBusiness, Paginated } from '@/types';

type Props = {
    businesses: Paginated<AdminBusiness>;
    search: string;
};

export default function AdminBusinesses({ businesses, search }: Props) {
    return (
        <>
            <Head title="Companies · Admin" />

            <SearchForm
                action={index()}
                search={search}
                placeholder="Company, website or owner"
            />

            <div className="overflow-hidden rounded-card border border-rule bg-card shadow-xs">
                {businesses.data.length === 0 ? (
                    <EmptyLine>
                        {search ? 'Nothing matches that' : 'No companies yet'}
                    </EmptyLine>
                ) : (
                    <ul>
                        {businesses.data.map((business) => (
                            <li
                                key={business.id}
                                className="border-b border-rule px-4 py-3 last:border-b-0"
                            >
                                <p className="text-sm">
                                    {business.name}
                                    {business.url && (
                                        <span className="ml-2 font-mono text-xs text-muted-foreground">
                                            {hostOf(business.url)}
                                        </span>
                                    )}
                                </p>
                                <p className="mt-1 font-mono text-xs text-muted-foreground tabular-nums">
                                    {business.owner_email} ·{' '}
                                    {business.competitors_count} competitors ·{' '}
                                    {business.digest_frequency_label.toLowerCase()}{' '}
                                    · last digest{' '}
                                    {formatRelative(business.last_digest_at)}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Pager links={businesses.links} meta={businesses.meta} />
        </>
    );
}

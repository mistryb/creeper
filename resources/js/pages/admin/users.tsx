import { Form, Head } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { SearchForm } from '@/components/admin/search-form';
import { EmptyLine, Pager } from '@/components/ds';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatRelative } from '@/lib/format';
import { index } from '@/routes/admin/users';
import type { AdminUser, Paginated } from '@/types';

type Props = {
    users: Paginated<AdminUser>;
    search: string;
    billingEnabled: boolean;
};

function companies(user: AdminUser): string {
    const count =
        user.businesses_count === 1
            ? '1 company'
            : `${user.businesses_count} companies`;

    return user.business_limit === null
        ? count
        : `${count} of ${user.business_limit}`;
}

export default function AdminUsers({ users, search, billingEnabled }: Props) {
    return (
        <>
            <Head title="Users · Admin" />

            <SearchForm
                action={index()}
                search={search}
                placeholder="Email or name"
            />

            <div className="overflow-hidden rounded-card border border-rule bg-card shadow-xs">
                {users.data.length === 0 ? (
                    <EmptyLine>
                        {search ? 'Nobody matches that' : 'No accounts yet'}
                    </EmptyLine>
                ) : (
                    <ul>
                        {users.data.map((user) => (
                            <li
                                key={user.id}
                                className="flex flex-wrap items-center justify-between gap-4 border-b border-rule px-4 py-3 last:border-b-0"
                            >
                                <div className="min-w-0">
                                    <p className="flex flex-wrap items-center gap-2 text-sm">
                                        <span className="truncate">
                                            {user.email}
                                        </span>
                                        {user.is_admin && (
                                            <Badge variant="outline">
                                                Admin
                                            </Badge>
                                        )}
                                    </p>
                                    <p className="mt-1 font-mono text-xs text-muted-foreground tabular-nums">
                                        {user.status} · {companies(user)} ·
                                        joined {formatRelative(user.created_at)}
                                    </p>
                                </div>

                                {billingEnabled && (
                                    <Form
                                        {...(user.comped
                                            ? UserController.uncomp.form(
                                                  user.id,
                                              )
                                            : UserController.comp.form(
                                                  user.id,
                                              ))}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                {user.comped
                                                    ? 'Remove comp'
                                                    : 'Comp Unlimited'}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Pager links={users.links} meta={users.meta} />
        </>
    );
}

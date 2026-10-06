import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import WatchedPageController from '@/actions/App/Http/Controllers/WatchedPageController';
import { CategoryField } from '@/components/creep/category-field';
import { WatchForField } from '@/components/creep/watch-for-field';
import {
    CheckField,
    EmptyState,
    Field,
    FormActions,
    Page,
    SectionHeading,
} from '@/components/ds';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { WATCH_PRESETS } from '@/lib/watch-presets';
import { index as apiKeysSettings } from '@/routes/api-keys';
import { index as competitorsIndex } from '@/routes/businesses/competitors';
import { show as showCompetitor } from '@/routes/competitors';
import { create } from '@/routes/competitors/watched-pages';
import type { Competitor, PageCategory, SelectOption } from '@/types';

export default function CreateWatchedPage({
    competitor: { data: competitor },
    frequencies,
    categories,
    apiKeys,
}: {
    competitor: { data: Competitor };
    categories: SelectOption[];
    frequencies: SelectOption[];
    apiKeys: SelectOption[];
}) {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Competitors',
                href: competitorsIndex(competitor.business_id),
            },
            { title: competitor.name, href: showCompetitor(competitor.id) },
            { title: 'Watch a page', href: create(competitor.id) },
        ],
    });

    // A preset knows what kind of page it expects, so the URL box can show
    // an example of one.
    const [urlPlaceholder, setUrlPlaceholder] = useState(
        WATCH_PRESETS[0].urlPlaceholder,
    );
    const [category, setCategory] = useState<PageCategory>('other');

    return (
        <>
            <Head title={`Watch a page of ${competitor.name}'s`} />

            <Page>
                <SectionHeading
                    title="Watch a page"
                    note={`One of ${competitor.name}'s — paste a URL and Creeper takes it from there`}
                />

                <div className="max-w-xl space-y-6">
                    {/*
                     * Creeping is paid for with the user's own key, and there
                     * is none in the environment to fall back on, so a keyring
                     * with nothing on it means there is no form to fill in yet.
                     */}
                    {apiKeys.length === 0 ? (
                        <EmptyState
                            icon={KeyRound}
                            title="No API key yet"
                            actions={
                                <Button asChild>
                                    <Link href={apiKeysSettings()}>
                                        Add an API key
                                    </Link>
                                </Button>
                            }
                        >
                            Creeper reads pages with your own model API key, so
                            you pay your provider directly. Add one and come
                            back — you can keep several and choose which one
                            each watched page spends.
                        </EmptyState>
                    ) : (
                        <Form
                            {...WatchedPageController.store.form(competitor.id)}
                            className="space-y-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        label="Page URL"
                                        htmlFor="url"
                                        error={errors.url}
                                        hint={`A page of ${competitor.name}'s that anyone can open without signing in.`}
                                    >
                                        <Input
                                            id="url"
                                            name="url"
                                            type="url"
                                            required
                                            autoFocus
                                            className="font-mono text-sm"
                                            placeholder={urlPlaceholder}
                                        />
                                    </Field>

                                    <WatchForField
                                        error={errors.watch_for}
                                        onPreset={(preset) => {
                                            setUrlPlaceholder(
                                                preset.urlPlaceholder,
                                            );
                                            setCategory(preset.category);
                                        }}
                                    />

                                    <CategoryField
                                        categories={categories}
                                        value={category}
                                        onChange={setCategory}
                                        error={errors.category}
                                    />

                                    <Field
                                        label="Name"
                                        htmlFor="name"
                                        optional
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            placeholder="What you want to call it"
                                        />
                                    </Field>

                                    <Field
                                        label="How often should Creeper check?"
                                        htmlFor="frequency"
                                        error={errors.frequency}
                                    >
                                        <Select
                                            name="frequency"
                                            defaultValue={
                                                frequencies.find(
                                                    (option) =>
                                                        option.value ===
                                                        'daily',
                                                )?.value ??
                                                frequencies[0]?.value
                                            }
                                        >
                                            <SelectTrigger
                                                id="frequency"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {frequencies.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field
                                        label="Which API key should pay for it?"
                                        htmlFor="api_key_id"
                                        error={errors.api_key_id}
                                        hint="Creeper spends this key every time it reads the page. Manage your keys in settings."
                                    >
                                        <Select
                                            name="api_key_id"
                                            defaultValue={apiKeys[0]?.value}
                                        >
                                            <SelectTrigger
                                                id="api_key_id"
                                                className="w-full"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {apiKeys.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <CheckField
                                        htmlFor="notify_on_change"
                                        label="Email me when something changes"
                                        hint="One e-mail per run that finds something new, gone or changed."
                                        control={
                                            <Checkbox
                                                id="notify_on_change"
                                                name="notify_on_change"
                                                value="1"
                                                defaultChecked
                                            />
                                        }
                                    />

                                    <FormActions>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Starting…'
                                                : 'Start creeping'}
                                        </Button>
                                    </FormActions>
                                </>
                            )}
                        </Form>
                    )}
                </div>
            </Page>
        </>
    );
}

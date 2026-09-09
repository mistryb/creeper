/** The kind of instructions a target's creeper works from. */
export type CreepType = 'product' | 'changelog';

export type Availability = 'in_stock' | 'out_of_stock' | 'preorder' | 'unknown';

export type TargetStatus = 'active' | 'paused' | 'failed';

export type RunStatus = 'queued' | 'running' | 'succeeded' | 'failed';

export type ChangeDirection = 'up' | 'down' | 'changed';

export type ProductSnapshot = {
    id: number;
    title: string | null;
    brand: string | null;
    sku: string | null;
    /** Minor units — pence, cents. Format with `formatPrice`. */
    price_amount: number | null;
    currency: string | null;
    availability: Availability;
    availability_label: string;
    rating: number | null;
    review_count: number | null;
    image_url: string | null;
    captured_at: string;
};

/** What sort of change one changelog entry is. */
export type FeatureKind =
    | 'feature'
    | 'improvement'
    | 'fix'
    | 'breaking'
    | 'deprecation'
    | 'security'
    | 'other';

export type ChangelogFeature = {
    title: string;
    description: string | null;
    kind: FeatureKind;
};

export type ChangelogRelease = {
    version: string | null;
    /** YYYY-MM-DD, or null when the page didn't give a real date. */
    released_on: string | null;
    title: string | null;
    summary: string | null;
    features: ChangelogFeature[];
};

export type ChangelogSnapshot = {
    id: number;
    product: string | null;
    latest_version: string | null;
    latest_released_on: string | null;
    release_count: number;
    feature_count: number;
    /** Newest first, as the page listed them. */
    releases: ChangelogRelease[];
    captured_at: string;
};

export type CreepRun = {
    id: number;
    status: RunStatus;
    status_label: string;
    driver: string;
    started_at: string | null;
    finished_at: string | null;
    duration_ms: number | null;
    error: string | null;
    target?: CreepTarget;
};

export type CreepChange = {
    id: number;
    field: string;
    old_value: string | null;
    new_value: string | null;
    direction: ChangeDirection;
    description: string;
    detected_at: string;
    target?: CreepTarget;
};

export type CreepTarget = {
    id: number;
    url: string;
    name: string | null;
    display_name: string;
    type: CreepType;
    type_label: string;
    status: TargetStatus;
    status_label: string;
    frequency: string;
    frequency_label: string;
    notify_on_change: boolean;
    consecutive_failures: number;
    last_crept_at: string | null;
    next_creep_at: string | null;
    created_at: string | null;
    /** Product targets only. */
    latest_snapshot?: ProductSnapshot | null;
    /** Changelog targets only. */
    latest_changelog_snapshot?: ChangelogSnapshot | null;
    latest_run?: CreepRun | null;
};

export type SelectOption = {
    value: string;
    label: string;
};

/** A creep type on the new-target form. */
export type CreepTypeOption = SelectOption & {
    value: CreepType;
};

/** A resource collection: `Resource::collection($models)`. */
export type ResourceCollection<T> = {
    data: T[];
};

/** A paginated resource collection. */
export type PaginatedCollection<T> = ResourceCollection<T> & {
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};

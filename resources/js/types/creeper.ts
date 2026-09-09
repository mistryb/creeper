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
    type: string;
    status: TargetStatus;
    status_label: string;
    frequency: string;
    frequency_label: string;
    notify_on_change: boolean;
    consecutive_failures: number;
    last_crept_at: string | null;
    next_creep_at: string | null;
    created_at: string | null;
    latest_snapshot?: ProductSnapshot | null;
    latest_run?: CreepRun | null;
};

export type SelectOption = {
    value: string;
    label: string;
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

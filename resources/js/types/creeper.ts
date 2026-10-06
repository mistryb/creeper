export type PageStatus = 'active' | 'paused' | 'failed';

export type RunStatus = 'queued' | 'running' | 'succeeded' | 'failed';

/** What kind of news a watched page carries. */
export type PageCategory = 'pricing' | 'releases' | 'messaging' | 'other';

/** What happened to one fact between two readings. */
export type ChangeKind = 'added' | 'removed' | 'changed';

/** One thing read off a page: what it is called, and what it says. */
export type PageFact = {
    label: string;
    value: string;
};

/** One reading of a watched page. */
export type PageSnapshot = {
    id: number;
    summary: string;
    facts: PageFact[];
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
    watched_page?: WatchedPage;
};

export type CreepChange = {
    id: number;
    /** The fact that moved, as the page names it. */
    label: string;
    old_value: string | null;
    new_value: string | null;
    kind: ChangeKind;
    kind_label: string;
    description: string;
    detected_at: string;
    watched_page?: WatchedPage;
};

export type WatchedPage = {
    id: number;
    competitor_id: number;
    /** Only sent where it is loaded. */
    competitor?: Competitor;
    url: string;
    name: string | null;
    display_name: string;
    /** What the user wants watched, in their own words. */
    watch_for: string;
    category: PageCategory;
    category_label: string;
    status: PageStatus;
    status_label: string;
    frequency: string;
    frequency_label: string;
    notify_on_change: boolean;
    /** Which saved key pays for this watched page. Null once that key was deleted. */
    api_key_id: string | null;
    /** Only sent where the key is loaded, e.g. a watched page's own page. */
    api_key_label?: string | null;
    consecutive_failures: number;
    last_crept_at: string | null;
    next_creep_at: string | null;
    created_at: string | null;
    latest_snapshot?: PageSnapshot | null;
    latest_run?: CreepRun | null;
};

/** One key on the user's keyring, as the settings screen lists it. */
export type ApiKeySummary = {
    id: number;
    name: string;
    provider: string;
    providerLabel: string;
    /** The last four characters. The key itself never leaves the server. */
    hint: string;
    /** How many watched pages are being crept with it. */
    watchedPages: number;
    created_at: string | null;
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

/** A business the user watches competitors for. */
export type Business = {
    id: number;
    name: string;
    /** The business's own website, if it has one. */
    url: string | null;
    /** In the owner's own words: what they sell, to whom, and why it wins. */
    description: string;
    created_at: string | null;
    updated_at: string | null;
};

/** One entry in the sidebar's business chooser. */
export type BusinessChoice = Pick<Business, 'id' | 'name'>;

/** The chooser's shared prop: the business in use, and every one on offer. */
export type BusinessChoices = {
    /** Null only when the account has no businesses yet. */
    current: BusinessChoice | null;
    all: BusinessChoice[];
};

/** What the model said about a business, once validated. */
export type BusinessAnalysisReport = {
    summary: string;
    offering: string;
    audience: string;
    positioning: string;
    pricing: string | null;
    strengths: string[];
    weaknesses: string[];
    opportunities: string[];
    threats: string[];
    confidence: 'high' | 'medium' | 'low';
    notes: string | null;
};

/** One run of the business analysis. */
export type BusinessAnalysis = {
    id: number;
    status: RunStatus;
    status_label: string;
    /** Null until the run succeeds. */
    report: BusinessAnalysisReport | null;
    /** The website that was read alongside the description, if any. */
    source_url: string | null;
    provider: string | null;
    model: string | null;
    prompt_tokens: number | null;
    completion_tokens: number | null;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
};

/** A company a business competes with. */
export type Competitor = {
    id: number;
    business_id: number;
    name: string;
    /** Their website, if known. */
    url: string | null;
    /** What the user knows about them. */
    description: string | null;
    /** Only sent on lists. */
    watched_pages_count?: number;
    created_at: string | null;
    updated_at: string | null;
};

/** What an analysis screen is about: a business, or one of its competitors. */
export type AnalysisSubject = {
    type: 'business' | 'competitor';
    id: number;
    name: string;
    url: string | null;
};

/** One axis of the landscape: a fact read off pages, or a judgement. */
export type LandscapeDimension = {
    name: string;
    kind: 'fact' | 'judgement';
    description: string;
};

/** Which company a landscape row or map point is about. */
export type LandscapeCompany = {
    /** "you", or "competitor:{id}". */
    subject: string;
    name: string;
    is_you: boolean;
    competitor_id: number | null;
};

export type LandscapeCell = {
    dimension: string;
    value: string;
    /** 1 to 5, for judgements; null for facts. */
    score: number | null;
};

export type LandscapeRow = LandscapeCompany & {
    confidence: 'high' | 'medium' | 'low';
    /** One per dimension, in the dimensions' order. */
    cells: LandscapeCell[];
};

export type LandscapePoint = LandscapeCompany & {
    /** 0 to 10 along each axis. */
    x: number;
    y: number;
};

export type LandscapeReport = {
    summary: string;
    actions: string[];
    dimensions: LandscapeDimension[];
    /** The business first, then its competitors. */
    rows: LandscapeRow[];
    map: {
        x_axis: string;
        y_axis: string;
        points: LandscapePoint[];
    } | null;
};

/** One run of the business against all its competitors. */
export type LandscapeAnalysis = {
    id: number;
    status: RunStatus;
    status_label: string;
    report: LandscapeReport | null;
    error: string | null;
    prompt_tokens: number | null;
    completion_tokens: number | null;
    finished_at: string | null;
    created_at: string | null;
};

/** How busy one competitor's pages were over the dashboard's window. */
export type CompetitorActivity = {
    competitor_id: number;
    name: string;
    watched_pages: number;
    total: number;
    by_category: Record<PageCategory, number>;
    last_change_at: string | null;
};

/** Something missing or broken that weakens the comparison. */
export type DashboardGap = {
    type:
        | 'business_unanalysed'
        | 'business_stale'
        | 'competitor_unwatched'
        | 'competitor_unanalysed'
        | 'competitor_stale'
        | 'page_parked'
        | 'page_keyless';
    name: string;
    business_id?: number;
    competitor_id?: number;
    watched_page_id?: number;
    since?: string | null;
};

export type DashboardFilters = {
    /** Days. */
    window: 7 | 30 | 90;
    competitor: number | null;
    category: PageCategory | null;
};

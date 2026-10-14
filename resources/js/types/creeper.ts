export type RunStatus = 'queued' | 'running' | 'succeeded' | 'failed';

export type DigestFrequency = 'daily' | 'weekly';

/** One key on the user's keyring, as the settings screen lists it. */
export type ApiKeySummary = {
    id: number;
    name: string;
    provider: string;
    providerLabel: string;
    /** The last four characters. The key itself never leaves the server. */
    hint: string;
    /** How many companies spend it. */
    businesses: number;
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

/**
 * A paginated resource collection: `Resource::collection($query->paginate())`.
 */
export type Paginated<T> = {
    data: T[];
    links: {
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

export type PlanName = 'single' | 'unlimited';

/** Where the signed-in account stands with billing, shared on every page. */
export type BillingSummary = {
    plan: PlanName | null;
    /** Set only while the card-free trial is running. */
    trialEndsAt: string | null;
    /** True once the trial or subscription has ended with no plan to replace it. */
    needsPlan: boolean;
};

/** A plan as the billing screen offers it. */
export type PlanOption = {
    value: PlanName;
    label: string;
    /** Dollars a month. */
    price: number;
    /** Null means no limit. */
    businessLimit: number | null;
};

/** An account, as the admin panel lists it. */
export type AdminUser = {
    id: number;
    name: string;
    email: string;
    status: string;
    comped: boolean;
    is_admin: boolean;
    businesses_count: number;
    /** Null means no limit. */
    business_limit: number | null;
    created_at: string | null;
};

/** A company across every account, as the admin panel lists it. */
export type AdminBusiness = {
    id: number;
    name: string;
    url: string | null;
    owner_email: string;
    digest_frequency_label: string;
    competitors_count: number;
    last_digest_at: string | null;
    created_at: string | null;
};

/** A company the user gets digests about. */
export type Business = {
    id: number;
    name: string;
    /** The company's own website, if it has one. */
    url: string | null;
    /** In the owner's own words: what they sell, to whom, and why it wins. */
    description: string;
    digest_frequency: DigestFrequency;
    digest_frequency_label: string;
    /** Which saved key discovery and digests spend. Null once that key was deleted. */
    api_key_id: string | null;
    /** Only sent where the key is loaded, e.g. the dashboard. */
    api_key_label?: string | null;
    last_digest_at: string | null;
    next_digest_at: string | null;
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

/** A company a business competes with. */
export type Competitor = {
    id: number;
    business_id: number;
    name: string;
    /** Their website, if known. */
    url: string | null;
    /** What the user knows about them. */
    description: string | null;
    /** How digests refer to them: "competitor:{id}". */
    subject: string;
    /** When Creeper found them. Null for ones the user added. */
    discovered_at: string | null;
    /** Why Creeper picked them, in one line. */
    reason: string | null;
    created_at: string | null;
    updated_at: string | null;
};

/** One thing the model proposed when looking for competitors. */
export type CompetitorProposal = {
    name: string;
    url: string | null;
    reason: string;
    /** False when it was the company itself, or already on the list. */
    added: boolean;
};

/** One attempt to find a company's competitors. */
export type CompetitorDiscovery = {
    id: number;
    status: RunStatus;
    status_label: string;
    report: {
        proposals: CompetitorProposal[];
        notes: string | null;
    } | null;
    error: string | null;
    finished_at: string | null;
    created_at: string | null;
};

/** One thing a competitor did, as the digest reports it. */
export type DigestItem = {
    title: string;
    detail: string;
    /** Where it was found. */
    url: string | null;
    /** YYYY-MM-DD when known. */
    date: string | null;
};

/** One competitor's section of a digest. */
export type DigestSection = {
    /** "competitor:{id}". */
    subject: string;
    summary: string;
    items: DigestItem[];
};

export type DigestReport = {
    headline: string;
    /** One per competitor, in the order they were listed when it was written. */
    competitors: DigestSection[];
    notes: string | null;
};

/** One digest: a stretch of time, and what every competitor did in it. */
export type Digest = {
    id: number;
    business_id: number;
    status: RunStatus;
    status_label: string;
    /** Null until the run succeeds. */
    report: DigestReport | null;
    period_start: string;
    period_end: string;
    /** When it was emailed. Null until then. */
    sent_at: string | null;
    provider: string | null;
    model: string | null;
    prompt_tokens: number | null;
    completion_tokens: number | null;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
    created_at: string | null;
};

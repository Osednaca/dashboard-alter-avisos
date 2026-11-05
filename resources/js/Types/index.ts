export type Tone =
    | 'positive'
    | 'warning'
    | 'danger'
    | 'info'
    | 'neutral'
    | 'accent';

export interface EnumValue {
    value: string;
    label: string;
    tone?: Tone;
}

export interface Option {
    value: string;
    label: string;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    job_title: string | null;
    status?: string;
    roles: string[];
    permissions: string[];
}

export interface NotificationItem {
    id: number;
    severity: Tone;
    title: string;
    message: string | null;
    triggered_at: string | null;
}

export interface BusinessContext {
    current: {
        id: number;
        name: string;
        slug: string;
        category: string | null;
        category_label: string | null;
        timezone: string;
        logo_url: string | null;
    };
    available: Array<{ id: number; name: string; is_primary: boolean }>;
}

export interface BusinessProfile {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    category: EnumValue | null;
    status: EnumValue;
    timezone: string;
    contact_name: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    metadata: Record<string, unknown> | null;
}

export interface PlaylistSummary {
    id: number;
    name: string;
    type: EnumValue;
    status: EnumValue;
    items_count: number;
    total_duration: number;
    cover: MediaEntity | null;
    updated_at: string | null;
}

export interface PlaylistItemEntity {
    id: number;
    playlist_id: number;
    sort_order: number;
    duration: number;
    transition: string;
    media: MediaEntity | null;
}

export interface ContentScheduleEntity {
    id: number;
    name: string;
    daily_start_time: string | null;
    daily_end_time: string | null;
    days_of_week: number[];
    status: string;
    priority?: number;
    items?: PlaylistItemEntity[];
    is_active_now: boolean;
    playlist: { id: number; name: string } | null;
    location: { id: number; name: string; city: string } | null;
}

export interface PageProps {
    auth: { user: AuthUser | null };
    app: { name: string; logo_url: string; env: string; version: string };
    flash: { success?: string; error?: string; info?: string };
    notifications: { unread: number; recent: NotificationItem[] } | null;
    business: BusinessContext | null;
    [key: string]: unknown;
}

export interface BusinessEntity {
    id: number;
    name: string;
    slug: string;
    category: EnumValue;
    status: EnumValue;
    timezone: string;
    contact_name: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    metadata: Record<string, unknown> | null;
    locations_count: number;
    devices_count: number;
    online_devices_count: number;
    current_layout: { id: number; name: string; ratio: string } | null;
    created_at: string | null;
}

export interface LocationEntity {
    id: number;
    business: { id: number; name: string } | null;
    name: string;
    city: string;
    state: string | null;
    country: string;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    timezone: string;
    status: EnumValue;
    devices_count: number;
    online_devices_count: number;
    created_at: string | null;
}

export interface DeviceEntity {
    id: number;
    uuid: string;
    name: string;
    status: EnumValue;
    is_online: boolean;
    business: { id: number; name: string } | null;
    location: { id: number; name: string; city: string } | null;
    app_version: string | null;
    last_seen_at: string | null;
    last_sync_at: string | null;
    storage_total: number | null;
    storage_free: number | null;
    storage_usage: number | null;
    current_manifest_version: string | null;
    pending_manifest_version: string | null;
    current_layout: { id: number; name: string; ratio: string } | null;
    current_playlist: { id: number; name: string } | null;
    created_at: string | null;
}

export interface AdvertiserEntity {
    id: number;
    name: string;
    slug: string;
    status: EnumValue;
    contact_name: string | null;
    contact_email: string | null;
    contact_phone: string | null;
    billing_name: string | null;
    billing_tax_id: string | null;
    billing_email: string | null;
    billing_address: string | null;
    metadata: Record<string, unknown> | null;
    active_campaigns_count: number;
    campaigns_count?: number;
    total_screens: number;
    total_playbacks: number;
    created_at: string | null;
}

export interface CampaignEntity {
    id: number;
    name: string;
    description: string | null;
    status: EnumValue;
    advertiser: { id: number; name: string } | null;
    starts_at: string | null;
    ends_at: string | null;
    daily_start_time: string | null;
    daily_end_time: string | null;
    days_of_week: number[] | null;
    schedule_label: string;
    priority: number;
    playback_goal: number | null;
    impressions_goal: number | null;
    budget: string | null;
    target_screen_count: number;
    creatives_count: number;
    playbacks_count: number;
    completion_rate: number;
    progress: number | null;
    published_at: string | null;
    updated_at: string | null;
}

export interface MediaEntity {
    live?: import('@/Components/app/LiveStreamDialog').LiveSource | null;
    id: number;
    filename: string;
    type: EnumValue;
    mime_type: string | null;
    url: string;
    thumbnail_url: string;
    resolution: string | null;
    width: number | null;
    height: number | null;
    duration: number | null;
    formatted_duration: string | null;
    filesize: number | null;
    human_filesize: string;
    checksum: string | null;
    processing_status: EnumValue;
    usage_count: number;
    created_at: string | null;
}

export interface CommandEntity {
    id: number;
    command: EnumValue;
    status: EnumValue;
    payload: Record<string, unknown> | null;
    result: Record<string, unknown> | null;
    error: string | null;
    created_by: string | null;
    sent_at: string | null;
    executed_at: string | null;
    created_at: string | null;
}

export interface AlertEntity {
    id: number;
    type: EnumValue;
    severity: EnumValue;
    status: EnumValue;
    title: string;
    message: string | null;
    subject_type: string | null;
    subject_id: number | null;
    metadata: Record<string, unknown> | null;
    triggered_at: string | null;
    acknowledged_at: string | null;
    resolved_at: string | null;
}

export interface AuditEntity {
    id: number;
    user: string;
    action: string;
    entity_type: string | null;
    entity_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
}

export interface QuickPlayDeviceEntity {
    id: number;
    device: { id: number; name: string; business: string | null; city: string | null } | null;
    status: EnumValue;
    error: string | null;
    display_mode: EnumValue;
    duration: number | null;
    sent_at: string | null;
    started_at: string | null;
    completed_at: string | null;
}

export interface QuickPlayEntity {
    id: number;
    media: MediaEntity | null;
    user: string;
    display_mode: EnumValue;
    scope: EnumValue;
    duration: number | null;
    duration_label: string;
    can_retry: boolean;
    retry_id: number | null;
    retry_of_id: number | null;
    status: EnumValue;
    targets_count: number;
    delivered_count: number;
    failed_count: number;
    pending_count: number;
    expires_at: string | null;
    created_at: string | null;
}

export interface SeriesPoint {
    date: string;
    label: string;
    playbacks: number;
    completed?: number;
    failures?: number;
}

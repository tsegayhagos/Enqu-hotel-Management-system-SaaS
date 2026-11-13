import type { BadgeVariant } from './index';

export interface RoleBadge {
    value: string;
    label: string;
    variant: BadgeVariant;
}

export interface StaffMember {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    is_self: boolean;
    role: RoleBadge | null;
}

export interface RoleOption {
    value: string;
    label: string;
}

export interface HotelSettings {
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    country: string;
    currency: string;
    timezone: string;
    check_in_time: string;
    check_out_time: string;
}

export interface RoomStatusOption {
    value: string;
    label: string;
    variant: BadgeVariant;
}

export interface RoomRow {
    id: number;
    room_number: string;
    floor_number: number;
    is_active: boolean;
    notes: string | null;
    status: RoomStatusOption;
    room_type: { id: number; name: string | null };
    /** Status values a person may switch this room to by hand. */
    allowed_statuses: string[];
}

export interface RoomFilters {
    search: string;
    floor: string;
    status: string;
    room_type_id: string;
}

export interface RoomCounts {
    total: number;
    vacant_clean: number;
    vacant_dirty: number;
    occupied: number;
    cleaning: number;
    out_of_order: number;
}

export interface RoomTypeRow {
    id: number;
    name: string;
    description: string | null;
    base_occupancy: number;
    max_occupancy: number;
    base_price: string;
    extra_person_fee: string;
    amenities: string[];
    rooms_count: number;
}

export interface RateRow {
    id: number;
    room_type_id: number;
    rate_name: string;
    start_date: string;
    end_date: string;
    price_per_night: string;
    /** ISO weekdays 1 (Mon) - 7 (Sun); empty = every day. */
    days_of_week: number[];
    is_active: boolean;
}

export interface CalendarDay {
    date: string;
    price: string;
    rate_name: string | null;
    is_override: boolean;
}

export interface RateRoomType {
    value: number;
    label: string;
    base_price: string;
}

export interface HotelRow {
    id: number;
    name: string;
    slug: string;
    city: string;
    country: string;
    currency: string;
    is_active: boolean;
    users_count: number;
    created_at: string | null;
}

export interface FolioRow {
    id: number;
    hotel_id: number;
    reservation_id: number | null;
    guest_id: number | null;
    room_id: number | null;
    folio_number: string;
    guest_name: string | null;
    status: 'open' | 'settled' | 'closed' | 'void';
    total_charges: string;
    total_payments: string;
    balance: string;
    currency: string;
    notes: string | null;
    closed_at: string | null;
    created_at: string;
    room?: { id: number; room_number: string; room_type?: { name: string } };
    transactions?: FolioTransactionRow[];
}

export interface FolioTransactionRow {
    id: number;
    hotel_id: number;
    folio_id: number;
    user_id: number | null;
    transaction_type: string;
    description: string;
    amount: string;
    reference_id: string | null;
    payment_method: string;
    is_night_audit_post: boolean;
    is_immutable: boolean;
    posted_at: string;
    user?: { id: number; name: string } | null;
}

export interface FolioSummary {
    folio_number: string;
    status: string;
    currency: string;
    total_charges: string;
    total_payments: string;
    balance: string;
    charges_by_type: Record<string, string>;
    payments_by_method: Record<string, string>;
    is_settled: boolean;
}

export interface NightAuditLogRow {
    id: number;
    hotel_id: number;
    audit_date: string;
    occupied_rooms_count: number;
    total_room_revenue_posted: string;
    total_tax_posted: string;
    status: string;
    notes: string | null;
    created_at: string;
    executed_by?: { id: number; name: string } | null;
}

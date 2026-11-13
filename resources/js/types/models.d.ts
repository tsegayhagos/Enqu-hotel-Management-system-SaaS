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

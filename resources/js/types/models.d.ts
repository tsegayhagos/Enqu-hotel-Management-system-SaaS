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

export interface GuestRow {
    id: number;
    hotel_id: number;
    first_name: string;
    last_name: string;
    name?: string;
    full_name?: string;
    email: string | null;
    phone: string;
    id_type: 'passport' | 'national_id' | 'drivers_license' | 'other';
    id_number: string;
    nationality: string;
    car_plate_number: string | null;
    vip_level: 'standard' | 'silver' | 'gold' | 'vip';
    is_blacklisted: boolean;
    blacklist_reason: string | null;
    total_stays_count: number;
    special_preferences: string | null;
    reservations_count?: number;
    created_at: string;
}

export interface ReservationRow {
    id: number;
    hotel_id: number;
    reservation_code: string;
    guest_id: number;
    room_id: number | null;
    room_type_id: number;
    check_in_date: string;
    check_out_date: string;
    actual_check_in_at: string | null;
    actual_check_out_at: string | null;
    adults_count: number;
    children_count: number;
    daily_rate: string;
    total_amount: string;
    advance_deposit_paid: string;
    keycard_number: string | null;
    status: 'confirmed' | 'checked_in' | 'checked_out' | 'cancelled' | 'no_show';
    source: 'walk_in' | 'phone' | 'website' | 'ota_booking';
    special_requests: string | null;
    cancellation_reason: string | null;
    created_at: string;
    guest?: GuestRow;
    room?: { id: number; room_number: string; floor_number: number; status: string; room_type?: { name: string } };
    room_type?: { id: number; name: string; base_occupancy: number; max_occupancy: number };
    active_folio?: FolioRow | null;
    folios?: FolioRow[];
}

export interface FrontDeskMetrics {
    expected_arrivals: number;
    expected_departures: number;
    in_house_stays: number;
    available_clean_rooms: number;
    business_date: string;
}

export interface LaundryServiceRow {
    id: number;
    hotel_id: number;
    item_name: string;
    category: 'apparel' | 'delicate' | 'linen' | 'uniform';
    service_type: 'wash_and_iron' | 'iron_only' | 'dry_clean';
    standard_price: string;
    express_price: string;
    is_active: boolean;
}

export interface LaundryOrderItemRow {
    id: number;
    laundry_order_id: number;
    laundry_service_id: number;
    quantity: number;
    unit_price: string;
    subtotal: string;
    notes: string | null;
    service?: LaundryServiceRow;
}

export interface LaundryOrderRow {
    id: number;
    hotel_id: number;
    order_number: string;
    reservation_id: number | null;
    guest_id: number | null;
    room_id: number | null;
    order_type: 'guest_valet' | 'in_house_linen' | 'walk_in';
    is_express: boolean;
    status: 'received' | 'in_wash' | 'ironing' | 'ready' | 'delivered' | 'cancelled';
    payment_method: 'charge_to_room' | 'cash' | 'card' | 'unpaid';
    total_amount: string;
    special_instructions: string | null;
    received_at: string;
    delivered_at: string | null;
    room?: { id: number; room_number: string } | null;
    guest?: { id: number; first_name: string; last_name: string } | null;
    reservation?: { id: number; reservation_code: string } | null;
    processed_by?: { id: number; name: string } | null;
    items?: LaundryOrderItemRow[];
}

export interface OccupiedStayOption {
    reservation_id: number;
    room_id: number;
    room_number: string;
    guest_id: number;
    guest_name: string;
    reservation_code: string;
}

export type HousekeepingTaskType = 'checkout_clean' | 'stayover_clean' | 'deep_clean' | 'turndown';
export type HousekeepingPriority = 'low' | 'normal' | 'urgent';
export type HousekeepingTaskStatus = 'pending' | 'in_progress' | 'inspected' | 'completed';

export interface HousekeepingTaskRow {
    id: number;
    hotel_id: number;
    room_id: number;
    reservation_id: number | null;
    assigned_to_user_id: number | null;
    task_type: HousekeepingTaskType;
    priority: HousekeepingPriority;
    status: HousekeepingTaskStatus;
    due_date: string;
    started_at: string | null;
    completed_at: string | null;
    inspected_by_user_id: number | null;
    notes: string | null;
    room?: {
        id: number;
        room_number: string;
        floor_number: string | number | null;
        status: string;
        room_type?: { id: number; name: string };
    } | null;
    assigned_to?: { id: number; name: string; email: string } | null;
    inspected_by?: { id: number; name: string; email: string } | null;
}

export type MaintenanceCategory =
    | 'plumbing'
    | 'electrical'
    | 'ac_hvac'
    | 'furniture'
    | 'appliance'
    | 'structural'
    | 'other';

export type MaintenancePriority = 'low' | 'medium' | 'high' | 'emergency';
export type MaintenanceTicketStatus = 'open' | 'in_progress' | 'resolved' | 'closed';

export interface MaintenanceTicketPhotoRow {
    id: number;
    maintenance_ticket_id: number;
    photo_path: string;
    caption: string | null;
}

export interface MaintenanceTicketRow {
    id: number;
    hotel_id: number;
    room_id: number | null;
    location_label: string | null;
    reported_by_user_id: number;
    assigned_to_user_id: number | null;
    title: string;
    description: string;
    category: MaintenanceCategory;
    priority: MaintenancePriority;
    status: MaintenanceTicketStatus;
    blocks_room: boolean;
    estimated_cost: string | null;
    actual_cost: string | null;
    resolution_notes: string | null;
    resolved_at: string | null;
    created_at: string;
    room?: {
        id: number;
        room_number: string;
        floor_number: string | number | null;
        status: string;
        room_type?: { id: number; name: string };
    } | null;
    reported_by?: { id: number; name: string; email: string } | null;
    assigned_to?: { id: number; name: string; email: string } | null;
    photos?: MaintenanceTicketPhotoRow[];
}

export interface DepartmentBreakdownItem {
    name: string;
    amount: string;
    amount_numeric: number;
    percentage: number;
    color: string;
}

export interface ReservationSourceBreakdownItem {
    source: string;
    label: string;
    count: number;
    revenue: string;
}

export interface KpiSummaryData {
    start_date: string;
    end_date: string;
    currency: string;
    total_rooms: number;
    out_of_order_rooms: number;
    available_rooms: number;
    occupied_rooms_now: number;
    occupied_room_nights: number;
    available_room_nights: number;
    occupancy_rate: number;
    adr: string;
    revpar: string;
    room_revenue: string;
    laundry_revenue: string;
    restaurant_pos_revenue: string;
    minibar_fee_revenue: string;
    tax_collected: string;
    total_gross_revenue: string;
    payments_collected: string;
    departmental_breakdown: DepartmentBreakdownItem[];
    source_breakdown: ReservationSourceBreakdownItem[];
}

export interface DailyTrendPoint {
    date: string;
    label: string;
    room_revenue: number;
    pos_revenue: number;
    laundry_revenue: number;
    gross_revenue: number;
    payments_collected: number;
    occupancy_rate: number | null;
}

export interface CashierSummaryRow {
    user_id: number | null;
    cashier_name: string;
    cashier_email: string;
    cash: string;
    card: string;
    credit_card: string;
    debit_card: string;
    mobile_money: string;
    bank_transfer: string;
    complimentary: string;
    refunds: string;
    gross_collected: string;
    net_collected: string;
    transaction_count: number;
}

export interface CashierHandoverTransactionRow {
    id: number;
    posted_at: string;
    cashier: string;
    folio_number: string;
    guest_name: string;
    room_number: string;
    transaction_type: string;
    payment_method: string;
    description: string;
    reference_id: string | null;
    amount: string;
    is_refund: boolean;
}

export interface CashierHandoverReportData {
    start_date: string;
    end_date: string;
    currency: string;
    by_cashier: CashierSummaryRow[];
    totals: {
        cash: string;
        card: string;
        mobile_money: string;
        bank_transfer: string;
        complimentary: string;
        refunds: string;
        gross_collected: string;
        net_collected: string;
        transaction_count: number;
    };
    transactions: CashierHandoverTransactionRow[];
}

export interface OccupancyCalendarDay {
    date: string;
    day: number;
    weekday: string;
    occupied: number;
    available: number;
    occupancy_rate: number;
    arrivals: number;
    departures: number;
}

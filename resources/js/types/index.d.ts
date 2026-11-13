export interface AuthUser {
    id: number;
    name: string;
    email: string;
    is_super_admin: boolean;
}

export interface ActiveHotel {
    id: number;
    name: string;
    slug: string;
    currency: string;
    timezone: string;
    check_in_time: string;
    check_out_time: string;
    tax_rate?: number;
}

export interface HotelOption {
    id: number;
    name: string;
}

export interface SharedProps {
    auth: {
        user: AuthUser | null;
        permissions: string[];
        roles: string[];
    };
    hotel: ActiveHotel | null;
    hotels: HotelOption[];
    flash: {
        success: string | null;
        error: string | null;
    };
    [key: string]: unknown;
}

export type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'primary' | 'accent' | 'neutral';

export interface SelectOption {
    value: string | number;
    label: string;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
    from?: number;
    to?: number;
}

export type PaginatedResponse<T> = Paginated<T>;

export * from './models';

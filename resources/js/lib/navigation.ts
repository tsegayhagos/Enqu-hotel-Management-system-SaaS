import {
    BedDouble,
    Building2,
    CalendarDays,
    LayoutDashboard,
    Layers,
    Settings,
    Users,
    type LucideIcon,
} from 'lucide-react';

export interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    /** Spatie permission required to see the link. */
    permission?: string;
    /** Visible to platform Super Admins only. */
    superAdminOnly?: boolean;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

/**
 * Sidebar navigation. Each module appends its own entries here
 * (Rooms, Front Desk, Laundry, Housekeeping, Billing, Reports...).
 */
export const navigation: NavGroup[] = [
    {
        title: 'Overview',
        items: [{ label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard }],
    },
    {
        title: 'Property',
        items: [
            { label: 'Rooms', href: '/rooms', icon: BedDouble, permission: 'room.view' },
            { label: 'Room Types', href: '/room-types', icon: Layers, permission: 'room.manage' },
            { label: 'Rates', href: '/rates', icon: CalendarDays, permission: 'rate.manage' },
        ],
    },
    {
        title: 'Administration',
        items: [
            { label: 'Staff', href: '/settings/staff', icon: Users, permission: 'staff.manage' },
            { label: 'Hotel Settings', href: '/settings/hotel', icon: Settings, permission: 'hotel.manage' },
        ],
    },
    {
        title: 'Platform',
        items: [{ label: 'Hotels', href: '/admin/hotels', icon: Building2, superAdminOnly: true }],
    },
];

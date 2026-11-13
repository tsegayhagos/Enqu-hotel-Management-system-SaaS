import {
    BedDouble,
    Building2,
    CalendarCheck,
    CalendarDays,
    Contact,
    LayoutDashboard,
    Layers,
    Moon,
    PlusCircle,
    Receipt,
    Settings,
    Shirt,
    Sparkles,
    Wrench,
    Tag,
    UserCheck,
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
        title: 'Front Desk',
        items: [
            { label: 'Front Desk Live', href: '/front-desk', icon: UserCheck, permission: 'guest.view' },
            { label: 'Reservations', href: '/reservations', icon: CalendarCheck, permission: 'guest.view' },
            { label: 'Guest CRM', href: '/guests', icon: Contact, permission: 'guest.view' },
        ],
    },
    {
        title: 'Laundry & Valet',
        items: [
            { label: 'Laundry Pipeline', href: '/laundry', icon: Shirt, permission: 'laundry.view' },
            { label: 'New Laundry Order', href: '/laundry/create', icon: PlusCircle, permission: 'laundry.manage' },
            { label: 'Service Catalog', href: '/laundry/services', icon: Tag, permission: 'laundry.view' },
        ],
    },
    {
        title: 'Housekeeping & Eng',
        items: [
            { label: 'My Cleaning Tasks', href: '/housekeeping/my-tasks', icon: Sparkles, permission: 'housekeeping.perform' },
            { label: 'HK Floor Board', href: '/housekeeping/supervisor', icon: Sparkles, permission: 'housekeeping.view' },
            { label: 'Maintenance & Eng', href: '/maintenance', icon: Wrench, permission: 'maintenance.report' },
        ],
    },
    {
        title: 'Finance & Billing',
        items: [
            { label: 'Guest Folios', href: '/folios', icon: Receipt, permission: 'folio.view' },
            { label: 'Night Audit', href: '/night-audit', icon: Moon, permission: 'nightaudit.run' },
            { label: 'Cashier Handover', href: '/reports/cashier-handover', icon: Receipt, permission: 'cashier.report' },
        ],
    },
    {
        title: 'Reports & Analytics',
        items: [
            { label: 'Executive Reports', href: '/reports', icon: Building2, permission: 'report.view' },
            { label: 'Occupancy Calendar', href: '/reports/occupancy-calendar', icon: CalendarDays, permission: 'report.view' },
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

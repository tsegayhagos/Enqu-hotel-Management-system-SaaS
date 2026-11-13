import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    BedDouble,
    Building2,
    CalendarCheck,
    CheckCircle2,
    Clock,
    Coins,
    Moon,
    PlusCircle,
    Receipt,
    Shirt,
    Sparkles,
    UserCheck,
    Users,
    Wrench,
} from 'lucide-react';
import { Badge, type BadgeVariant, Card, CardContent, CardHeader, CardTitle, EmptyState, PageHeader, StatCard } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { SharedProps } from '@/types';

interface Capabilities {
    is_super_admin: boolean;
    view_rooms: boolean;
    manage_rooms: boolean;
    manage_rates: boolean;
    view_front_desk: boolean;
    manage_reservations: boolean;
    checkin: boolean;
    view_laundry: boolean;
    manage_laundry: boolean;
    perform_housekeeping: boolean;
    view_housekeeping: boolean;
    manage_housekeeping: boolean;
    report_maintenance: boolean;
    manage_maintenance: boolean;
    view_folios: boolean;
    manage_folios: boolean;
    run_night_audit: boolean;
    cashier_report: boolean;
    view_reports: boolean;
    manage_staff: boolean;
    manage_hotel: boolean;
}

interface RoomOverview {
    total: number;
    vacant_clean: number;
    vacant_dirty: number;
    occupied: number;
    cleaning: number;
    out_of_order: number;
    available: number;
    occupancy_rate: number;
}

interface RecentReservationItem {
    id: number;
    reservation_code: string;
    guest_name: string;
    room_number: string | null;
    room_type: string;
    check_in_date: string;
    check_out_date: string;
    status: string;
    status_label: string;
    status_variant: BadgeVariant;
}

interface FrontDeskOverview {
    arrivals_due: number;
    departures_due: number;
    in_house: number;
    upcoming: number;
    recent_queue: RecentReservationItem[];
}

interface HousekeepingOverview {
    pending: number;
    in_progress: number;
    inspected: number;
    completed_today: number;
    my_open_tasks: number;
}

interface MaintenanceOverview {
    open_tickets: number;
    urgent_tickets: number;
    blocking_ooo: number;
}

interface LaundryOverview {
    active_orders: number;
    in_process: number;
    ready_for_delivery: number;
    delivered: number;
}

interface FinanceOverview {
    currency: string;
    open_folios_count: number;
    outstanding_balance: string;
    today_revenue_posted: string;
    today_payments_collected: string;
    last_night_audit_date: string | null;
}

interface PlatformHotelItem {
    hotel_id: number;
    hotel_name: string;
    city: string | null;
    currency: string;
    total_rooms: number;
    out_of_order_rooms: number;
    occupancy_rate: number;
    adr: string;
    revpar: string;
    total_gross_revenue: string;
    payments_collected: string;
}

interface PlatformOverview {
    total_hotels: number;
    total_staff: number;
    hotels: PlatformHotelItem[];
}

interface DashboardProps {
    staffCount: number;
    businessDate: string;
    capabilities: Capabilities;
    roomOverview: RoomOverview | null;
    frontDesk: FrontDeskOverview | null;
    housekeeping: HousekeepingOverview | null;
    maintenance: MaintenanceOverview | null;
    laundry: LaundryOverview | null;
    finance: FinanceOverview | null;
    platformOverview: PlatformOverview | null;
}

export default function Dashboard({
    staffCount,
    businessDate,
    capabilities,
    roomOverview,
    frontDesk,
    housekeeping,
    maintenance,
    laundry,
    finance,
    platformOverview,
}: DashboardProps) {
    const { hotel, auth } = usePage<SharedProps>().props;
    const currency = hotel?.currency ?? finance?.currency ?? 'ETB';

    const roomSegments = roomOverview && roomOverview.total > 0
        ? [
              { label: 'Occupied', count: roomOverview.occupied, color: 'bg-indigo-600' },
              { label: 'Vacant Clean', count: roomOverview.vacant_clean, color: 'bg-emerald-500' },
              { label: 'Vacant Dirty', count: roomOverview.vacant_dirty, color: 'bg-amber-500' },
              { label: 'Cleaning', count: roomOverview.cleaning, color: 'bg-sky-500' },
              { label: 'Out of Order', count: roomOverview.out_of_order, color: 'bg-rose-500' },
          ]
        : [];

    const quickActions = [
        {
            show: capabilities.view_front_desk,
            label: 'Front Desk Live',
            href: '/front-desk',
            icon: UserCheck,
        },
        {
            show: capabilities.manage_reservations || capabilities.view_front_desk,
            label: 'Reservations',
            href: '/reservations',
            icon: CalendarCheck,
        },
        {
            show: capabilities.view_folios,
            label: 'Guest Folios',
            href: '/folios',
            icon: Receipt,
        },
        {
            show: capabilities.run_night_audit,
            label: 'Night Audit',
            href: '/night-audit',
            icon: Moon,
        },
        {
            show: capabilities.manage_laundry,
            label: 'New Laundry Order',
            href: '/laundry/create',
            icon: PlusCircle,
        },
        {
            show: capabilities.view_laundry && !capabilities.manage_laundry,
            label: 'Laundry Pipeline',
            href: '/laundry',
            icon: Shirt,
        },
        {
            show: capabilities.perform_housekeeping,
            label: 'My Cleaning Tasks',
            href: '/housekeeping/my-tasks',
            icon: Sparkles,
        },
        {
            show: capabilities.view_housekeeping,
            label: 'HK Floor Board',
            href: '/housekeeping/supervisor',
            icon: BedDouble,
        },
        {
            show: capabilities.report_maintenance || capabilities.manage_maintenance,
            label: 'Maintenance',
            href: '/maintenance',
            icon: Wrench,
        },
        {
            show: capabilities.view_reports,
            label: 'Executive Reports',
            href: '/reports',
            icon: BarChart3,
        },
        {
            show: capabilities.cashier_report && !capabilities.view_reports,
            label: 'Cashier Handover',
            href: '/reports/cashier-handover',
            icon: Receipt,
        },
        {
            show: capabilities.manage_staff,
            label: 'Team & Roles',
            href: '/settings/staff',
            icon: Users,
        },
        {
            show: capabilities.is_super_admin,
            label: 'Platform Hotels',
            href: '/admin/hotels',
            icon: Building2,
        },
    ].filter((action) => action.show);

    return (
        <AuthenticatedLayout title="Dashboard">
            <PageHeader
                title={hotel ? hotel.name : 'Platform Command Center'}
                subtitle={`Welcome back, ${auth.user?.name ?? 'User'} — Business Date: ${businessDate}`}
                breadcrumbs={[{ label: 'Dashboard' }]}
            />

            {/* Quick Action Hub */}
            {quickActions.length > 0 && (
                <div className="mb-6 flex flex-wrap gap-2">
                    {quickActions.map((action) => {
                        const Icon = action.icon;
                        return (
                            <Link
                                key={action.href}
                                href={action.href}
                                className="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 shadow-sm transition-colors hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-primary-700 dark:hover:bg-primary-950/30"
                            >
                                <Icon className="h-3.5 w-3.5 text-primary-600 dark:text-primary-400" />
                                {action.label}
                            </Link>
                        );
                    })}
                </div>
            )}

            {/* Top Role-Aware KPI Strip */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {roomOverview && (
                    <StatCard
                        title="Live Occupancy"
                        value={`${roomOverview.occupancy_rate}%`}
                        subtitle={`${roomOverview.occupied} / ${roomOverview.available} sellable rooms`}
                        icon={<BedDouble className="h-5 w-5" />}
                    />
                )}

                {frontDesk && (
                    <StatCard
                        title="Today's Front Desk"
                        value={`${frontDesk.arrivals_due} in / ${frontDesk.departures_due} out`}
                        subtitle={`${frontDesk.in_house} in-house · ${frontDesk.upcoming} upcoming`}
                        icon={<UserCheck className="h-5 w-5" />}
                    />
                )}

                {finance && (
                    <StatCard
                        title="Today's Posted Revenue"
                        value={`${currency} ${finance.today_revenue_posted}`}
                        subtitle={`${finance.open_folios_count} open folios (${currency} ${finance.outstanding_balance})`}
                        icon={<Coins className="h-5 w-5" />}
                    />
                )}

                {housekeeping && !finance && (
                    <StatCard
                        title="Housekeeping Queue"
                        value={capabilities.perform_housekeeping ? housekeeping.my_open_tasks : housekeeping.pending + housekeeping.in_progress}
                        subtitle={`${housekeeping.completed_today} cleaned today · ${housekeeping.inspected} awaiting inspection`}
                        icon={<Sparkles className="h-5 w-5" />}
                    />
                )}

                {laundry && !frontDesk && (
                    <StatCard
                        title="Laundry Pipeline"
                        value={laundry.active_orders}
                        subtitle={`${laundry.in_process} washing/ironing · ${laundry.ready_for_delivery} ready`}
                        icon={<Shirt className="h-5 w-5" />}
                    />
                )}

                {hotel && (
                    <StatCard
                        title="Active Team Members"
                        value={staffCount}
                        subtitle={`${hotel.currency} · ${hotel.timezone}`}
                        icon={<Users className="h-5 w-5" />}
                    />
                )}

                {!hotel && platformOverview && (
                    <>
                        <StatCard
                            title="Active Properties"
                            value={platformOverview.total_hotels}
                            subtitle="Multi-tenant hotels"
                            icon={<Building2 className="h-5 w-5" />}
                        />
                        <StatCard
                            title="Platform Users"
                            value={platformOverview.total_staff}
                            subtitle="All hotel staff & admins"
                            icon={<Users className="h-5 w-5" />}
                        />
                    </>
                )}
            </div>

            {/* Room Inventory Status Distribution Bar */}
            {roomOverview && roomOverview.total > 0 && (
                <Card className="mt-6">
                    <CardHeader className="flex flex-row items-center justify-between py-4">
                        <CardTitle className="text-base">Room Inventory & Cleanliness Status ({roomOverview.total} Rooms)</CardTitle>
                        <Link href="/rooms" className="text-xs font-medium text-primary-700 hover:underline dark:text-primary-400">
                            Open Room Rack &rarr;
                        </Link>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex h-3.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            {roomSegments.map((seg) => {
                                const widthPct = (seg.count / roomOverview.total) * 100;
                                if (widthPct <= 0) {
                                    return null;
                                }
                                return (
                                    <div
                                        key={seg.label}
                                        className={`${seg.color} transition-all`}
                                        style={{ width: `${widthPct}%` }}
                                        title={`${seg.label}: ${seg.count}`}
                                    />
                                );
                            })}
                        </div>
                        <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600 dark:text-slate-300">
                            {roomSegments.map((seg) => (
                                <div key={seg.label} className="flex items-center gap-1.5">
                                    <span className={`h-2.5 w-2.5 rounded-full ${seg.color}`} />
                                    <span>{seg.label}:</span>
                                    <span className="font-semibold text-slate-900 dark:text-slate-100">{seg.count}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Main Operational Grid */}
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Front Desk Arrivals / In-House Queue (2 cols) */}
                {frontDesk && (
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Front Desk Queue</CardTitle>
                                <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    Upcoming check-ins and active in-house guests
                                </p>
                            </div>
                            <Link href="/front-desk" className="text-xs font-medium text-primary-700 hover:underline dark:text-primary-400">
                                Full Front Desk &rarr;
                            </Link>
                        </CardHeader>
                        <CardContent>
                            {frontDesk.recent_queue.length === 0 ? (
                                <EmptyState
                                    title="No active or pending reservations"
                                    description="Create a booking or walk-in stay from Reservations."
                                    icon={<CalendarCheck className="h-7 w-7" />}
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm">
                                        <thead>
                                            <tr className="border-b border-slate-100 text-xs uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                                <th className="pb-2.5 font-medium">Code</th>
                                                <th className="pb-2.5 font-medium">Guest</th>
                                                <th className="pb-2.5 font-medium">Room</th>
                                                <th className="pb-2.5 font-medium">Dates</th>
                                                <th className="pb-2.5 font-medium">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                            {frontDesk.recent_queue.map((res) => (
                                                <tr key={res.id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                                    <td className="py-2.5 font-mono text-xs font-medium text-primary-700 dark:text-primary-400">
                                                        <Link href={`/reservations/${res.id}`} className="hover:underline">
                                                            {res.reservation_code}
                                                        </Link>
                                                    </td>
                                                    <td className="py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                        {res.guest_name}
                                                    </td>
                                                    <td className="py-2.5 text-slate-600 dark:text-slate-300">
                                                        {res.room_number ? `Rm ${res.room_number}` : res.room_type}
                                                    </td>
                                                    <td className="py-2.5 text-xs text-slate-500 dark:text-slate-400">
                                                        {res.check_in_date} &rarr; {res.check_out_date}
                                                    </td>
                                                    <td className="py-2.5">
                                                        <Badge variant={res.status_variant} dot>
                                                            {res.status_label}
                                                        </Badge>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Department Summary Stack */}
                <div className={`space-y-4 ${frontDesk ? '' : 'lg:col-span-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 space-y-0'}`}>
                    {housekeeping && (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between py-4">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Sparkles className="h-4 w-4 text-emerald-600" />
                                    Housekeeping Operations
                                </CardTitle>
                                <Link
                                    href={capabilities.view_housekeeping ? '/housekeeping/supervisor' : '/housekeeping/my-tasks'}
                                    className="text-xs text-primary-700 hover:underline dark:text-primary-400"
                                >
                                    Open &rarr;
                                </Link>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-3 text-xs">
                                <div className="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/60">
                                    <span className="text-slate-500 dark:text-slate-400">Pending</span>
                                    <p className="mt-1 text-lg font-bold text-slate-900 dark:text-slate-100">{housekeeping.pending}</p>
                                </div>
                                <div className="rounded-lg bg-sky-50 p-2.5 dark:bg-sky-950/30">
                                    <span className="text-sky-700 dark:text-sky-300">In Progress</span>
                                    <p className="mt-1 text-lg font-bold text-sky-900 dark:text-sky-100">{housekeeping.in_progress}</p>
                                </div>
                                <div className="rounded-lg bg-amber-50 p-2.5 dark:bg-amber-950/30">
                                    <span className="text-amber-700 dark:text-amber-300">Needs Inspection</span>
                                    <p className="mt-1 text-lg font-bold text-amber-900 dark:text-amber-100">{housekeeping.inspected}</p>
                                </div>
                                <div className="rounded-lg bg-emerald-50 p-2.5 dark:bg-emerald-950/30">
                                    <span className="text-emerald-700 dark:text-emerald-300">Done Today</span>
                                    <p className="mt-1 text-lg font-bold text-emerald-900 dark:text-emerald-100">{housekeeping.completed_today}</p>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {maintenance && (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between py-4">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Wrench className="h-4 w-4 text-amber-600" />
                                    Maintenance & Engineering
                                </CardTitle>
                                <Link href="/maintenance" className="text-xs text-primary-700 hover:underline dark:text-primary-400">
                                    Tickets &rarr;
                                </Link>
                            </CardHeader>
                            <CardContent className="space-y-2 text-xs">
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-600 dark:text-slate-300">Open Work Orders</span>
                                    <Badge variant={maintenance.open_tickets > 0 ? 'warning' : 'success'}>
                                        {maintenance.open_tickets}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-600 dark:text-slate-300">High / Emergency Priority</span>
                                    <Badge variant={maintenance.urgent_tickets > 0 ? 'danger' : 'neutral'}>
                                        {maintenance.urgent_tickets}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                        <AlertTriangle className="h-3.5 w-3.5 text-rose-500" />
                                        Rooms Blocked (OOO)
                                    </span>
                                    <Badge variant={maintenance.blocking_ooo > 0 ? 'danger' : 'success'}>
                                        {maintenance.blocking_ooo}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {laundry && (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between py-4">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Shirt className="h-4 w-4 text-indigo-600" />
                                    Laundry & Valet
                                </CardTitle>
                                <Link href="/laundry" className="text-xs text-primary-700 hover:underline dark:text-primary-400">
                                    Board &rarr;
                                </Link>
                            </CardHeader>
                            <CardContent className="space-y-2 text-xs">
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-600 dark:text-slate-300">Active Orders</span>
                                    <span className="font-semibold text-slate-900 dark:text-slate-100">{laundry.active_orders}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-600 dark:text-slate-300">Washing / Ironing</span>
                                    <span className="font-semibold text-slate-900 dark:text-slate-100">{laundry.in_process}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-slate-600 dark:text-slate-300">Ready for Delivery</span>
                                    <Badge variant={laundry.ready_for_delivery > 0 ? 'success' : 'neutral'}>
                                        {laundry.ready_for_delivery}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {finance && (
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between py-4">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Receipt className="h-4 w-4 text-emerald-600" />
                                    Finance & Audit Pulse
                                </CardTitle>
                                <Link
                                    href={capabilities.view_reports ? '/reports' : '/folios'}
                                    className="text-xs text-primary-700 hover:underline dark:text-primary-400"
                                >
                                    Details &rarr;
                                </Link>
                            </CardHeader>
                            <CardContent className="space-y-2 text-xs">
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                        <ArrowUpRight className="h-3.5 w-3.5 text-emerald-500" />
                                        Payments Today
                                    </span>
                                    <span className="font-semibold text-slate-900 dark:text-slate-100">
                                        {currency} {finance.today_payments_collected}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                        <ArrowDownRight className="h-3.5 w-3.5 text-amber-500" />
                                        Open Folio Balance
                                    </span>
                                    <span className="font-semibold text-slate-900 dark:text-slate-100">
                                        {currency} {finance.outstanding_balance}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                        <Clock className="h-3.5 w-3.5 text-slate-400" />
                                        Last Night Audit
                                    </span>
                                    <Badge variant={finance.last_night_audit_date ? 'info' : 'warning'}>
                                        {finance.last_night_audit_date ?? 'Pending First Run'}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            {/* Platform Multi-Hotel Rollup (Super Admin only) */}
            {platformOverview && platformOverview.hotels.length > 0 && (
                <Card className="mt-6">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>SaaS Multi-Property Summary (Last 7 Days)</CardTitle>
                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Live occupancy and gross revenue across every active hotel tenant
                            </p>
                        </div>
                        <Link href="/admin/hotels" className="text-xs font-medium text-primary-700 hover:underline dark:text-primary-400">
                            Manage Properties &rarr;
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-xs uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                        <th className="pb-2.5 font-medium">Property</th>
                                        <th className="pb-2.5 font-medium">Rooms</th>
                                        <th className="pb-2.5 font-medium">Occupancy</th>
                                        <th className="pb-2.5 font-medium">ADR</th>
                                        <th className="pb-2.5 font-medium">Gross Revenue</th>
                                        <th className="pb-2.5 font-medium">Collected</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {platformOverview.hotels.map((item) => (
                                        <tr key={item.hotel_id} className="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                            <td className="py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                                {item.hotel_name}
                                                {item.city && <span className="ml-1.5 text-xs text-slate-400">({item.city})</span>}
                                            </td>
                                            <td className="py-2.5 text-slate-600 dark:text-slate-300">
                                                {item.total_rooms}
                                                {item.out_of_order_rooms > 0 && (
                                                    <span className="ml-1 text-xs text-rose-600">({item.out_of_order_rooms} OOO)</span>
                                                )}
                                            </td>
                                            <td className="py-2.5">
                                                <Badge variant={item.occupancy_rate >= 70 ? 'success' : 'primary'}>
                                                    {item.occupancy_rate}%
                                                </Badge>
                                            </td>
                                            <td className="py-2.5 text-slate-700 dark:text-slate-200">
                                                {item.currency} {item.adr}
                                            </td>
                                            <td className="py-2.5 font-semibold text-slate-900 dark:text-slate-100">
                                                {item.currency} {item.total_gross_revenue}
                                            </td>
                                            <td className="py-2.5 text-emerald-700 dark:text-emerald-400">
                                                {item.currency} {item.payments_collected}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Helpful fallback if a brand-new user has zero module panels */}
            {!roomOverview && !frontDesk && !housekeeping && !maintenance && !laundry && !finance && !platformOverview && (
                <Card className="mt-6">
                    <CardContent className="p-8">
                        <EmptyState
                            icon={<CheckCircle2 className="h-8 w-8 text-emerald-600" />}
                            title="Ready to get started"
                            description="Contact your Hotel Manager to verify your role permissions and workspace modules."
                        />
                    </CardContent>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}

import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    BedDouble,
    CalendarCheck,
    CheckCircle2,
    Clock,
    FileText,
    LogOut,
    Plus,
    Search,
    Sparkles,
    UserCheck,
    Users,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { CheckInModal } from '@/Pages/FrontDesk/CheckInModal';
import { CheckOutModal } from '@/Pages/FrontDesk/CheckOutModal';
import type { FrontDeskMetrics, ReservationRow } from '@/types/models';

interface FrontDeskDashboardProps {
    metrics: FrontDeskMetrics;
    arrivals: ReservationRow[];
    departures: ReservationRow[];
    inHouse: ReservationRow[];
    vacantCleanRooms: any[];
    roomTypes: any[];
}

export default function Dashboard({
    metrics,
    arrivals,
    departures,
    inHouse,
    vacantCleanRooms,
    roomTypes,
}: FrontDeskDashboardProps) {
    const [activeTab, setActiveTab] = useState<'arrivals' | 'departures' | 'in_house'>('arrivals');
    const [checkInModalOpen, setCheckInModalOpen] = useState(false);
    const [selectedReservationForCheckIn, setSelectedReservationForCheckIn] = useState<ReservationRow | null>(null);

    const [checkOutModalOpen, setCheckOutModalOpen] = useState(false);
    const [selectedReservationForCheckOut, setSelectedReservationForCheckOut] = useState<ReservationRow | null>(null);

    const openCheckIn = (reservation?: ReservationRow) => {
        setSelectedReservationForCheckIn(reservation || null);
        setCheckInModalOpen(true);
    };

    const openCheckOut = (reservation: ReservationRow) => {
        setSelectedReservationForCheckOut(reservation);
        setCheckOutModalOpen(true);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Front Desk Live" />

            <div className="space-y-6">
                <PageHeader
                    title="Front Desk Operations"
                    subtitle={`Live reception board for ${metrics.business_date}. Process arrivals, departures, and walk-in stays.`}
                    actions={
                        <div className="flex items-center gap-2">
                            <Link href="/reservations">
                                <Button variant="outline">
                                    <CalendarCheck className="mr-2 h-4 w-4" />
                                    All Reservations
                                </Button>
                            </Link>
                            <Button onClick={() => openCheckIn()}>
                                <Plus className="mr-2 h-4 w-4" />
                                Quick Walk-In Check-In
                            </Button>
                        </div>
                    }
                />

                {/* KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Today's Arrivals"
                        value={metrics.expected_arrivals}
                        icon={<ArrowDownRight className="h-5 w-5 text-amber-600 dark:text-amber-400" />}
                        subtitle="Pending check-ins"
                    />
                    <StatCard
                        title="Today's Departures"
                        value={metrics.expected_departures}
                        icon={<ArrowUpRight className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />}
                        subtitle="Scheduled check-outs"
                    />
                    <StatCard
                        title="In-House Stays"
                        value={metrics.in_house_stays}
                        icon={<Users className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />}
                        subtitle="Currently occupied"
                    />
                    <StatCard
                        title="Available Clean Rooms"
                        value={metrics.available_clean_rooms}
                        icon={<Sparkles className="h-5 w-5 text-sky-600 dark:text-sky-400" />}
                        subtitle="Vacant & clean ready"
                    />
                </div>

                {/* Main Tabs Container */}
                <div className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    {/* Tab Navigation */}
                    <div className="flex border-b border-slate-200 px-4 dark:border-slate-800">
                        <button
                            type="button"
                            onClick={() => setActiveTab('arrivals')}
                            className={`flex items-center gap-2 border-b-2 px-4 py-3.5 text-sm font-medium transition-colors ${
                                activeTab === 'arrivals'
                                    ? 'border-amber-600 text-amber-600 dark:border-amber-400 dark:text-amber-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                            }`}
                        >
                            <ArrowDownRight className="h-4 w-4" />
                            Expected Arrivals
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                {arrivals.length}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => setActiveTab('departures')}
                            className={`flex items-center gap-2 border-b-2 px-4 py-3.5 text-sm font-medium transition-colors ${
                                activeTab === 'departures'
                                    ? 'border-amber-600 text-amber-600 dark:border-amber-400 dark:text-amber-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                            }`}
                        >
                            <ArrowUpRight className="h-4 w-4" />
                            Expected Departures
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                {departures.length}
                            </span>
                        </button>

                        <button
                            type="button"
                            onClick={() => setActiveTab('in_house')}
                            className={`flex items-center gap-2 border-b-2 px-4 py-3.5 text-sm font-medium transition-colors ${
                                activeTab === 'in_house'
                                    ? 'border-amber-600 text-amber-600 dark:border-amber-400 dark:text-amber-400'
                                    : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                            }`}
                        >
                            <Users className="h-4 w-4" />
                            In-House Guests
                            <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                {inHouse.length}
                            </span>
                        </button>
                    </div>

                    {/* Tab 1: Expected Arrivals */}
                    {activeTab === 'arrivals' && (
                        <div className="p-4">
                            {arrivals.length === 0 ? (
                                <EmptyState
                                    icon={<CalendarCheck className="h-10 w-10 text-slate-400" />}
                                    title="No Pending Arrivals Today"
                                    description="All scheduled guests for today have checked in or no bookings remain."
                                    action={
                                        <Button onClick={() => openCheckIn()}>
                                            <Plus className="mr-2 h-4 w-4" /> Walk-In Check-In
                                        </Button>
                                    }
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                        <thead className="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                            <tr>
                                                <th className="px-4 py-3">Code / Guest</th>
                                                <th className="px-4 py-3">Room Type & Room</th>
                                                <th className="px-4 py-3">Dates</th>
                                                <th className="px-4 py-3">Rate / Total</th>
                                                <th className="px-4 py-3">Status</th>
                                                <th className="px-4 py-3 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                            {arrivals.map((res) => (
                                                <tr key={res.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                                    <td className="px-4 py-3">
                                                        <div className="font-semibold text-slate-900 dark:text-slate-100">
                                                            {res.guest?.first_name} {res.guest?.last_name}
                                                        </div>
                                                        <div className="text-xs text-slate-500">
                                                            {res.reservation_code} • {res.guest?.phone}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium text-slate-800 dark:text-slate-200">
                                                            {res.room_type?.name}
                                                        </div>
                                                        <div className="text-xs text-slate-500">
                                                            {res.room ? `Room ${res.room.room_number}` : 'Unassigned'}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div>{res.check_in_date} → {res.check_out_date}</div>
                                                        <div className="text-xs text-slate-500">
                                                            {res.adults_count} Adults, {res.children_count} Children
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="font-medium">{res.daily_rate} / night</div>
                                                        <div className="text-xs text-slate-500">Total: {res.total_amount}</div>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <Badge variant="info">Confirmed</Badge>
                                                    </td>
                                                    <td className="px-4 py-3 text-right">
                                                        <Button size="sm" onClick={() => openCheckIn(res)}>
                                                            <UserCheck className="mr-1.5 h-3.5 w-3.5" />
                                                            Check In
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab 2: Expected Departures */}
                    {activeTab === 'departures' && (
                        <div className="p-4">
                            {departures.length === 0 ? (
                                <EmptyState
                                    icon={<CheckCircle2 className="h-10 w-10 text-slate-400" />}
                                    title="No Pending Departures"
                                    description="No in-house guests are currently scheduled to check out today."
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                        <thead className="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                            <tr>
                                                <th className="px-4 py-3">Room / Guest</th>
                                                <th className="px-4 py-3">Stay Dates</th>
                                                <th className="px-4 py-3">Folio Status</th>
                                                <th className="px-4 py-3">Keycard</th>
                                                <th className="px-4 py-3 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                            {departures.map((res) => {
                                                const folio = res.active_folio;
                                                const balance = folio ? parseFloat(folio.balance) : 0;
                                                return (
                                                    <tr key={res.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold text-slate-900 dark:text-slate-100">
                                                                Room {res.room?.room_number} • {res.guest?.first_name} {res.guest?.last_name}
                                                            </div>
                                                            <div className="text-xs text-slate-500">
                                                                {res.reservation_code} • {res.room_type?.name}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div>{res.check_in_date} → {res.check_out_date}</div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {folio ? (
                                                                <div className="flex items-center gap-2">
                                                                    <Badge variant={balance > 0 ? 'danger' : 'success'}>
                                                                        {balance > 0 ? `Due: ${folio.balance} ${folio.currency}` : 'Settled'}
                                                                    </Badge>
                                                                    <Link
                                                                        href={`/folios/${folio.id}`}
                                                                        className="text-xs text-amber-600 hover:underline"
                                                                    >
                                                                        Folio #{folio.folio_number}
                                                                    </Link>
                                                                </div>
                                                            ) : (
                                                                <span className="text-xs text-slate-400">No Folio</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className="text-xs font-mono">{res.keycard_number || 'None'}</span>
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <Button size="sm" variant="outline" onClick={() => openCheckOut(res)}>
                                                                <LogOut className="mr-1.5 h-3.5 w-3.5" />
                                                                Check Out
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Tab 3: In-House Guests */}
                    {activeTab === 'in_house' && (
                        <div className="p-4">
                            {inHouse.length === 0 ? (
                                <EmptyState
                                    icon={<BedDouble className="h-10 w-10 text-slate-400" />}
                                    title="No Guests Currently In-House"
                                    description="There are no active checked-in guests in the hotel."
                                    action={
                                        <Button onClick={() => openCheckIn()}>
                                            <Plus className="mr-2 h-4 w-4" /> Walk-In Check-In
                                        </Button>
                                    }
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                        <thead className="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                            <tr>
                                                <th className="px-4 py-3">Room / Guest</th>
                                                <th className="px-4 py-3">Check-In / Departure</th>
                                                <th className="px-4 py-3">Active Folio</th>
                                                <th className="px-4 py-3">Keycard</th>
                                                <th className="px-4 py-3 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                            {inHouse.map((res) => {
                                                const folio = res.active_folio;
                                                const balance = folio ? parseFloat(folio.balance) : 0;
                                                return (
                                                    <tr key={res.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold text-slate-900 dark:text-slate-100">
                                                                Room {res.room?.room_number} • {res.guest?.first_name} {res.guest?.last_name}
                                                            </div>
                                                            <div className="text-xs text-slate-500">
                                                                {res.reservation_code} • {res.guest?.phone}
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <div>{res.check_in_date} → {res.check_out_date}</div>
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            {folio ? (
                                                                <div className="flex items-center gap-2">
                                                                    <Badge variant={balance > 0 ? 'warning' : 'success'}>
                                                                        Bal: {folio.balance} {folio.currency}
                                                                    </Badge>
                                                                    <Link
                                                                        href={`/folios/${folio.id}`}
                                                                        className="text-xs text-amber-600 hover:underline"
                                                                    >
                                                                        Folio #{folio.folio_number}
                                                                    </Link>
                                                                </div>
                                                            ) : (
                                                                <span className="text-xs text-slate-400">No Folio</span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span className="text-xs font-mono">{res.keycard_number || 'None'}</span>
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <Button size="sm" variant="outline" onClick={() => openCheckOut(res)}>
                                                                <LogOut className="mr-1.5 h-3.5 w-3.5" />
                                                                Check Out
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Check-In Modal */}
            <CheckInModal
                open={checkInModalOpen}
                onClose={() => setCheckInModalOpen(false)}
                reservation={selectedReservationForCheckIn}
                cleanRooms={vacantCleanRooms}
                roomTypes={roomTypes}
            />

            {/* Check-Out Modal */}
            <CheckOutModal
                open={checkOutModalOpen}
                onClose={() => setCheckOutModalOpen(false)}
                reservation={selectedReservationForCheckOut}
            />
        </AuthenticatedLayout>
    );
}

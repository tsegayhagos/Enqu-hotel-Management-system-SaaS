import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarDays,
    Eye,
    Filter,
    Plus,
    Search,
    UserCheck,
    Users,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Modal } from '@/Components/ui/Modal';
import { GuestAutocomplete } from '@/Components/GuestAutocomplete';
import type { GuestRow, ReservationRow } from '@/types/models';

interface RoomTypeOption {
    id: number;
    name: string;
}

interface ReservationsIndexProps {
    reservations: {
        data: ReservationRow[];
        links: any[];
        total: number;
    };
    filters: {
        status: string;
        search: string;
    };
    roomTypes: RoomTypeOption[];
}

export default function Index({
    reservations,
    filters,
    roomTypes,
}: ReservationsIndexProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [selectedGuest, setSelectedGuest] = useState<GuestRow | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        guest_id: '',
        room_type_id: roomTypes[0]?.id ? String(roomTypes[0].id) : '',
        room_id: '',
        check_in_date: new Date().toISOString().split('T')[0],
        check_out_date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
        adults_count: 1,
        children_count: 0,
        daily_rate: '150.00',
        advance_deposit_paid: '0',
        source: 'phone',
        special_requests: '',
    });

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/reservations',
            { search, status: statusFilter },
            { preserveState: true }
        );
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/reservations', {
            onSuccess: () => {
                reset();
                setSelectedGuest(null);
                setCreateModalOpen(false);
            },
        });
    };

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'confirmed':
                return 'info';
            case 'checked_in':
                return 'success';
            case 'checked_out':
                return 'neutral';
            case 'cancelled':
                return 'danger';
            case 'no_show':
                return 'warning';
            default:
                return 'neutral';
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Reservations" />

            <div className="space-y-6">
                <PageHeader
                    title="Reservations"
                    subtitle="Manage upcoming bookings, advance reservations, and guest arrivals."
                    actions={
                        <Button onClick={() => setCreateModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            New Reservation
                        </Button>
                    }
                />

                {/* Filters */}
                <form
                    onSubmit={handleFilterSubmit}
                    className="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div className="relative min-w-[240px] flex-1">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search reservation code, guest name, phone..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-900"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <option value="all">All Statuses</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="checked_in">Checked In</option>
                            <option value="checked_out">Checked Out</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="no_show">No Show</option>
                        </select>

                        <Button type="submit" variant="secondary">
                            <Filter className="mr-1.5 h-4 w-4" /> Filter
                        </Button>
                    </div>
                </form>

                {/* Reservations Table */}
                <div className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    {reservations.data.length === 0 ? (
                        <div className="p-8">
                            <EmptyState
                                icon={<CalendarDays className="h-10 w-10 text-slate-400" />}
                                title="No Reservations Found"
                                description="No reservation matching your filter criteria was found."
                                action={
                                    <Button onClick={() => setCreateModalOpen(true)}>
                                        <Plus className="mr-2 h-4 w-4" /> Create Reservation
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead className="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                    <tr>
                                        <th className="px-4 py-3">Code</th>
                                        <th className="px-4 py-3">Guest Details</th>
                                        <th className="px-4 py-3">Room Type / Room</th>
                                        <th className="px-4 py-3">Stay Dates</th>
                                        <th className="px-4 py-3">Rate & Total</th>
                                        <th className="px-4 py-3">Source</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {reservations.data.map((res) => (
                                        <tr key={res.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                            <td className="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-slate-100">
                                                {res.reservation_code}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-900 dark:text-slate-100">
                                                    {res.guest?.first_name} {res.guest?.last_name}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {res.guest?.phone} {res.guest?.email ? `• ${res.guest.email}` : ''}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium">{res.room_type?.name}</div>
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
                                                <div>{res.daily_rate} / night</div>
                                                <div className="text-xs font-semibold text-slate-800 dark:text-slate-200">
                                                    Total: {res.total_amount}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs uppercase text-slate-500">
                                                {res.source.replace('_', ' ')}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={getStatusVariant(res.status) as any}>
                                                    {res.status.replace('_', ' ')}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    {res.active_folio && (
                                                        <Link
                                                            href={`/folios/${res.active_folio.id}`}
                                                            className="text-xs font-medium text-amber-600 hover:underline"
                                                        >
                                                            Folio
                                                        </Link>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Create Reservation Modal */}
            <Modal
                open={createModalOpen}
                onClose={() => setCreateModalOpen(false)}
                title="Create Advance Booking"
            >
                <form onSubmit={handleCreateSubmit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                            Guest Profile *
                        </label>
                        <GuestAutocomplete
                            selectedGuest={selectedGuest}
                            onSelectGuest={(guest) => {
                                setSelectedGuest(guest);
                                setData('guest_id', guest ? String(guest.id) : '');
                            }}
                            placeholder="Search guest by name or phone..."
                        />
                        {errors.guest_id && <p className="mt-1 text-xs text-red-600">{errors.guest_id}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Room Type *
                            </label>
                            <select
                                value={data.room_type_id}
                                onChange={(e) => setData('room_type_id', e.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                {roomTypes.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.name}
                                    </option>
                                ))}
                            </select>
                            {errors.room_type_id && <p className="mt-1 text-xs text-red-600">{errors.room_type_id}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Booking Source
                            </label>
                            <select
                                value={data.source}
                                onChange={(e) => setData('source', e.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="phone">Phone Booking</option>
                                <option value="walk_in">Walk-In</option>
                                <option value="website">Direct Website</option>
                                <option value="ota_booking">OTA (Booking.com / Expedia)</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Check-In Date *
                            </label>
                            <input
                                type="date"
                                required
                                value={data.check_in_date}
                                onChange={(e) => setData('check_in_date', e.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            {errors.check_in_date && <p className="mt-1 text-xs text-red-600">{errors.check_in_date}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Check-Out Date *
                            </label>
                            <input
                                type="date"
                                required
                                value={data.check_out_date}
                                onChange={(e) => setData('check_out_date', e.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            {errors.check_out_date && <p className="mt-1 text-xs text-red-600">{errors.check_out_date}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Daily Room Rate *
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                required
                                value={data.daily_rate}
                                onChange={(e) => setData('daily_rate', e.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            {errors.daily_rate && <p className="mt-1 text-xs text-red-600">{errors.daily_rate}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Advance Deposit
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                value={data.advance_deposit_paid}
                                onChange={(e) => setData('advance_deposit_paid', e.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Special Requests / Preferences
                        </label>
                        <textarea
                            rows={2}
                            value={data.special_requests}
                            onChange={(e) => setData('special_requests', e.target.value)}
                            placeholder="e.g. Quiet room, airport shuttle needed, early arrival..."
                            className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                        />
                    </div>

                    <div className="flex justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                        <Button type="button" variant="outline" onClick={() => setCreateModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={processing} disabled={!data.guest_id}>
                            Confirm Reservation
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

import React, { useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    Camera,
    CheckCircle2,
    Hammer,
    Plus,
    Search,
    ShieldAlert,
    Wrench,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Badge, type BadgeVariant } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { EmptyState } from '@/Components/ui/EmptyState';
import type { MaintenanceTicketRow } from '@/types/models';

interface MaintenanceIndexProps {
    tickets: MaintenanceTicketRow[];
    rooms: { id: number; room_number: string; floor_number: string | number | null; status: string }[];
    staff: { id: number; name: string; email: string }[];
    currency: string;
    canManage: boolean;
}

const PRIORITY_BADGE: Record<MaintenanceTicketRow['priority'], BadgeVariant> = {
    low: 'neutral',
    medium: 'info',
    high: 'warning',
    emergency: 'danger',
};

const STATUS_BADGE: Record<MaintenanceTicketRow['status'], BadgeVariant> = {
    open: 'danger',
    in_progress: 'warning',
    resolved: 'success',
    closed: 'neutral',
};

export default function Index({
    tickets,
    rooms,
    staff,
    currency,
    canManage,
}: MaintenanceIndexProps) {
    const [statusFilter, setStatusFilter] = useState<string>('active');
    const [search, setSearch] = useState<string>('');
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [resolveTicketTarget, setResolveTicketTarget] = useState<MaintenanceTicketRow | null>(null);

    const createForm = useForm<{
        room_id: string;
        location_label: string;
        assigned_to_user_id: string;
        title: string;
        description: string;
        category: string;
        priority: string;
        blocks_room: boolean;
        estimated_cost: string;
        photos: File[];
    }>({
        room_id: '',
        location_label: '',
        assigned_to_user_id: '',
        title: '',
        description: '',
        category: 'plumbing',
        priority: 'medium',
        blocks_room: false,
        estimated_cost: '',
        photos: [],
    });

    const resolveForm = useForm<{
        resolution_notes: string;
        actual_cost: string;
        assigned_to_user_id: string;
    }>({
        resolution_notes: '',
        actual_cost: '',
        assigned_to_user_id: '',
    });

    const filteredTickets = useMemo(() => {
        return tickets.filter((t) => {
            if (statusFilter === 'active' && !['open', 'in_progress'].includes(t.status)) {
                return false;
            }
            if (statusFilter !== 'all' && statusFilter !== 'active' && t.status !== statusFilter) {
                return false;
            }
            if (search.trim()) {
                const q = search.toLowerCase();
                const matchTitle = t.title.toLowerCase().includes(q);
                const matchLoc = (t.location_label ?? '').toLowerCase().includes(q);
                const matchRoom = (t.room?.room_number ?? '').toLowerCase().includes(q);
                return matchTitle || matchLoc || matchRoom;
            }
            return true;
        });
    }, [tickets, statusFilter, search]);

    const openCount = tickets.filter((t) => t.status === 'open').length;
    const emergencyCount = tickets.filter(
        (t) => ['open', 'in_progress'].includes(t.status) && ['high', 'emergency'].includes(t.priority)
    ).length;
    const blockedCount = tickets.filter(
        (t) => ['open', 'in_progress'].includes(t.status) && t.blocks_room
    ).length;
    const resolvedCount = tickets.filter((t) => t.status === 'resolved' || t.status === 'closed').length;

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/maintenance/tickets', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setCreateModalOpen(false);
                createForm.reset();
            },
        });
    };

    const handleStartWork = (ticket: MaintenanceTicketRow) => {
        router.patch(
            `/maintenance/tickets/${ticket.id}`,
            { status: 'in_progress' },
            { preserveScroll: true }
        );
    };

    const handleAssignEngineer = (ticket: MaintenanceTicketRow, userId: string) => {
        router.patch(
            `/maintenance/tickets/${ticket.id}`,
            { assigned_to_user_id: userId ? Number(userId) : null },
            { preserveScroll: true }
        );
    };

    const openResolveModal = (ticket: MaintenanceTicketRow) => {
        setResolveTicketTarget(ticket);
        resolveForm.setData({
            resolution_notes: ticket.resolution_notes ?? '',
            actual_cost: ticket.actual_cost ?? ticket.estimated_cost ?? '',
            assigned_to_user_id: ticket.assigned_to_user_id ? String(ticket.assigned_to_user_id) : '',
        });
    };

    const handleResolveSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resolveTicketTarget) {
            return;
        }
        resolveForm.patch(`/maintenance/tickets/${resolveTicketTarget.id}/resolve`, {
            preserveScroll: true,
            onSuccess: () => {
                setResolveTicketTarget(null);
                resolveForm.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Maintenance & Engineering" />

            <div className="space-y-6">
                <PageHeader
                    title="Maintenance & Engineering"
                    subtitle="Track room defects, safety issues, Out-of-Order blocks, and engineering repair costs."
                    actions={
                        <Button onClick={() => setCreateModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Log Maintenance Ticket
                        </Button>
                    }
                />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="Open Tickets" value={openCount} icon={<Wrench className="h-5 w-5 text-rose-500" />} />
                    <StatCard title="High / Emergency" value={emergencyCount} icon={<AlertOctagon className="h-5 w-5 text-amber-500" />} />
                    <StatCard title="Rooms Blocked (OOO)" value={blockedCount} icon={<ShieldAlert className="h-5 w-5 text-rose-600" />} />
                    <StatCard title="Resolved Tickets" value={resolvedCount} icon={<CheckCircle2 className="h-5 w-5 text-emerald-500" />} />
                </div>

                {/* Filters */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-wrap gap-2">
                        {[
                            { key: 'active', label: 'Active Queue' },
                            { key: 'open', label: 'Open' },
                            { key: 'in_progress', label: 'In Repair' },
                            { key: 'resolved', label: 'Resolved' },
                            { key: 'all', label: 'All Tickets' },
                        ].map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setStatusFilter(tab.key)}
                                className={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                                    statusFilter === tab.key
                                        ? 'bg-primary-700 text-white'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="relative w-full sm:w-64">
                        <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search room or issue..."
                            className="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-9 pr-3 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                </div>

                {/* Ticket Cards */}
                {filteredTickets.length === 0 ? (
                    <EmptyState
                        icon={<Wrench className="h-8 w-8 text-slate-400" />}
                        title="No Matching Maintenance Tickets"
                        description="All rooms and facilities in this filter view are operating smoothly."
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {filteredTickets.map((ticket) => (
                            <div
                                key={ticket.id}
                                className={`rounded-xl border bg-white p-5 shadow-sm dark:bg-slate-900 ${
                                    ticket.blocks_room && ['open', 'in_progress'].includes(ticket.status)
                                        ? 'border-rose-300 ring-1 ring-rose-200 dark:border-rose-800'
                                        : 'border-slate-200 dark:border-slate-800'
                                }`}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-xs font-semibold text-slate-500">
                                                #{ticket.id} • {ticket.location_label ?? (ticket.room ? `Room ${ticket.room.room_number}` : 'Public Area')}
                                            </span>
                                            <Badge variant={PRIORITY_BADGE[ticket.priority]}>
                                                {ticket.priority.toUpperCase()}
                                            </Badge>
                                            <Badge variant={STATUS_BADGE[ticket.status]}>
                                                {ticket.status.replace('_', ' ').toUpperCase()}
                                            </Badge>
                                            {ticket.blocks_room && ['open', 'in_progress'].includes(ticket.status) && (
                                                <Badge variant="danger">
                                                    <AlertTriangle className="mr-1 h-3 w-3" />
                                                    ROOM OOO
                                                </Badge>
                                            )}
                                        </div>
                                        <h3 className="mt-1.5 text-base font-bold text-slate-900 dark:text-white">
                                            {ticket.title}
                                        </h3>
                                    </div>

                                    <span className="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-medium uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {ticket.category.replace('_', '/')}
                                    </span>
                                </div>

                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                    {ticket.description}
                                </p>

                                {ticket.photos && ticket.photos.length > 0 && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {ticket.photos.map((photo) => (
                                            <a
                                                key={photo.id}
                                                href={`/storage/${photo.photo_path}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="block overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700"
                                            >
                                                <img
                                                    src={`/storage/${photo.photo_path}`}
                                                    alt="Defect evidence"
                                                    className="h-14 w-14 object-cover"
                                                />
                                            </a>
                                        ))}
                                    </div>
                                )}

                                {ticket.resolution_notes && (
                                    <div className="mt-3 rounded-lg bg-emerald-50 p-2.5 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300">
                                        <span className="font-semibold">Resolution:</span> {ticket.resolution_notes}
                                    </div>
                                )}

                                <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800">
                                    <div className="space-y-0.5">
                                        <div>
                                            Reported by <span className="font-medium text-slate-700 dark:text-slate-300">{ticket.reported_by?.name ?? 'Staff'}</span>
                                        </div>
                                        <div>
                                            Est: {ticket.estimated_cost ? `${currency} ${ticket.estimated_cost}` : '—'} • Actual:{' '}
                                            {ticket.actual_cost ? `${currency} ${ticket.actual_cost}` : '—'}
                                        </div>
                                    </div>

                                    {canManage && ['open', 'in_progress'].includes(ticket.status) && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <select
                                                value={ticket.assigned_to_user_id ?? ''}
                                                onChange={(e) => handleAssignEngineer(ticket, e.target.value)}
                                                className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                            >
                                                <option value="">Assign technician...</option>
                                                {staff.map((u) => (
                                                    <option key={u.id} value={u.id}>
                                                        {u.name}
                                                    </option>
                                                ))}
                                            </select>

                                            {ticket.status === 'open' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleStartWork(ticket)}
                                                >
                                                    <Hammer className="mr-1 h-3.5 w-3.5" />
                                                    Start Work
                                                </Button>
                                            )}

                                            <Button size="sm" onClick={() => openResolveModal(ticket)}>
                                                <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                                                Resolve
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Create Ticket Modal */}
            <Modal
                open={createModalOpen}
                title="Log Maintenance Work Order"
                onClose={() => setCreateModalOpen(false)}
            >
                <form onSubmit={handleCreate} className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Room (leave blank for Public Area)
                            </label>
                            <select
                                value={createForm.data.room_id}
                                onChange={(e) => {
                                    const rid = e.target.value;
                                    const room = rooms.find((r) => String(r.id) === rid);
                                    createForm.setData({
                                        ...createForm.data,
                                        room_id: rid,
                                        location_label: room ? `Room ${room.room_number}` : createForm.data.location_label,
                                        blocks_room:
                                            Boolean(rid) &&
                                            ['high', 'emergency'].includes(createForm.data.priority),
                                    });
                                }}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="">Public Area / Facility</option>
                                {rooms.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        Room {r.room_number} ({r.status.replace('_', ' ')})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Location Label
                            </label>
                            <input
                                type="text"
                                placeholder="e.g. Main Lobby AC / Room 204"
                                value={createForm.data.location_label}
                                onChange={(e) => createForm.setData('location_label', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Issue Title
                        </label>
                        <input
                            type="text"
                            required
                            placeholder="e.g. Leaking main shower mixer"
                            value={createForm.data.title}
                            onChange={(e) => createForm.setData('title', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {createForm.errors.title && (
                            <p className="mt-1 text-xs text-rose-500">{createForm.errors.title}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Category
                            </label>
                            <select
                                value={createForm.data.category}
                                onChange={(e) => createForm.setData('category', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="plumbing">Plumbing</option>
                                <option value="electrical">Electrical</option>
                                <option value="ac_hvac">AC / HVAC</option>
                                <option value="furniture">Furniture</option>
                                <option value="appliance">Appliance</option>
                                <option value="structural">Structural</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Priority
                            </label>
                            <select
                                value={createForm.data.priority}
                                onChange={(e) => {
                                    const p = e.target.value;
                                    createForm.setData({
                                        ...createForm.data,
                                        priority: p,
                                        blocks_room: Boolean(createForm.data.room_id) && ['high', 'emergency'].includes(p),
                                    });
                                }}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Estimated Cost ({currency})
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={createForm.data.estimated_cost}
                                onChange={(e) => createForm.setData('estimated_cost', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Assign Technician
                            </label>
                            <select
                                value={createForm.data.assigned_to_user_id}
                                onChange={(e) => createForm.setData('assigned_to_user_id', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="">Unassigned</option>
                                {staff.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div className="flex items-end pb-2">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    checked={createForm.data.blocks_room}
                                    disabled={!createForm.data.room_id}
                                    onChange={(e) => createForm.setData('blocks_room', e.target.checked)}
                                    className="rounded border-slate-300 text-rose-600"
                                />
                                Set room Out of Order
                            </label>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Description
                        </label>
                        <textarea
                            rows={3}
                            required
                            value={createForm.data.description}
                            onChange={(e) => createForm.setData('description', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {createForm.errors.blocks_room && (
                            <p className="mt-1 text-xs text-rose-500">{createForm.errors.blocks_room}</p>
                        )}
                    </div>

                    <div>
                        <label className="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                            <Camera className="h-3.5 w-3.5" />
                            Photos (optional)
                        </label>
                        <input
                            type="file"
                            multiple
                            accept="image/*"
                            onChange={(e) => createForm.setData('photos', Array.from(e.target.files ?? []))}
                            className="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setCreateModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={createForm.processing}>
                            Create Ticket
                        </Button>
                    </div>
                </form>
            </Modal>

            {/* Resolve Ticket Modal */}
            <Modal
                open={resolveTicketTarget !== null}
                title={`Resolve Ticket #${resolveTicketTarget?.id ?? ''}`}
                onClose={() => setResolveTicketTarget(null)}
            >
                <form onSubmit={handleResolveSubmit} className="space-y-4">
                    {resolveTicketTarget?.blocks_room && resolveTicketTarget.room && (
                        <div className="rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            Resolving this ticket will automatically release{' '}
                            <strong>Room {resolveTicketTarget.room.room_number}</strong> from{' '}
                            <strong>Out of Order</strong> back to <strong>Vacant Dirty</strong> for housekeeping.
                        </div>
                    )}

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Technician Who Completed Repair
                        </label>
                        <select
                            value={resolveForm.data.assigned_to_user_id}
                            onChange={(e) => resolveForm.setData('assigned_to_user_id', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="">Keep current</option>
                            {staff.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Actual Cost ({currency})
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={resolveForm.data.actual_cost}
                            onChange={(e) => resolveForm.setData('actual_cost', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Resolution Notes
                        </label>
                        <textarea
                            rows={3}
                            required
                            placeholder="What was repaired or replaced?"
                            value={resolveForm.data.resolution_notes}
                            onChange={(e) => resolveForm.setData('resolution_notes', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setResolveTicketTarget(null)}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={resolveForm.processing}>
                            Mark Resolved
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

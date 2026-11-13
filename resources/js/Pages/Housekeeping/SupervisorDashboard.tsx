import React, { useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    BedDouble,
    CheckCircle2,
    Layers,
    Plus,
    RefreshCw,
    RotateCcw,
    ShieldCheck,
    Sparkles,
    UserCheck,
    Wrench,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Badge, type BadgeVariant } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { EmptyState } from '@/Components/ui/EmptyState';
import type { HousekeepingTaskRow } from '@/types/models';

interface RoomGridRow {
    id: number;
    room_number: string;
    floor_number: string | number | null;
    status: 'vacant_clean' | 'vacant_dirty' | 'occupied' | 'cleaning' | 'out_of_order';
    room_type?: { id: number; name: string } | null;
}

interface StaffOption {
    id: number;
    name: string;
    email: string;
}

interface SupervisorDashboardProps {
    rooms: RoomGridRow[];
    tasks: HousekeepingTaskRow[];
    staff: StaffOption[];
    blockingRoomIds: number[];
}

const ROOM_STATUS_BADGE: Record<RoomGridRow['status'], { label: string; variant: BadgeVariant }> = {
    vacant_clean: { label: 'Vacant Clean', variant: 'success' },
    vacant_dirty: { label: 'Vacant Dirty', variant: 'danger' },
    cleaning: { label: 'Cleaning', variant: 'info' },
    occupied: { label: 'Occupied', variant: 'primary' },
    out_of_order: { label: 'Out of Order', variant: 'warning' },
};

const TASK_STATUS_BADGE: Record<HousekeepingTaskRow['status'], BadgeVariant> = {
    pending: 'neutral',
    in_progress: 'info',
    inspected: 'warning',
    completed: 'success',
};

const PRIORITY_BADGE: Record<HousekeepingTaskRow['priority'], BadgeVariant> = {
    low: 'neutral',
    normal: 'info',
    urgent: 'danger',
};

export default function SupervisorDashboard({
    rooms,
    tasks,
    staff,
    blockingRoomIds,
}: SupervisorDashboardProps) {
    const [floorFilter, setFloorFilter] = useState<string>('all');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [selectedTaskIds, setSelectedTaskIds] = useState<number[]>([]);
    const [bulkAssigneeId, setBulkAssigneeId] = useState<string>('');
    const [createModalOpen, setCreateModalOpen] = useState(false);

    const createForm = useForm<{
        room_id: string;
        assigned_to_user_id: string;
        task_type: string;
        priority: string;
        due_date: string;
        notes: string;
    }>({
        room_id: '',
        assigned_to_user_id: '',
        task_type: 'checkout_clean',
        priority: 'normal',
        due_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    const blockingSet = useMemo(() => new Set(blockingRoomIds), [blockingRoomIds]);

    const floors = useMemo(() => {
        const uniq = Array.from(new Set(rooms.map((r) => String(r.floor_number ?? '1'))));
        return uniq.sort();
    }, [rooms]);

    const filteredRooms = useMemo(() => {
        return rooms.filter((r) => {
            if (floorFilter !== 'all' && String(r.floor_number ?? '1') !== floorFilter) {
                return false;
            }
            if (statusFilter !== 'all' && r.status !== statusFilter) {
                return false;
            }
            return true;
        });
    }, [rooms, floorFilter, statusFilter]);

    const dirtyCount = rooms.filter((r) => r.status === 'vacant_dirty').length;
    const cleaningCount = rooms.filter((r) => r.status === 'cleaning').length;
    const awaitingInspectionCount = tasks.filter((t) => t.status === 'inspected').length;
    const oooCount = rooms.filter((r) => r.status === 'out_of_order').length;

    const assignableTasks = tasks.filter((t) => t.status !== 'completed');

    const toggleTaskSelect = (id: number) => {
        setSelectedTaskIds((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]
        );
    };

    const toggleSelectAll = () => {
        if (selectedTaskIds.length === assignableTasks.length) {
            setSelectedTaskIds([]);
        } else {
            setSelectedTaskIds(assignableTasks.map((t) => t.id));
        }
    };

    const handleBulkAssign = () => {
        if (selectedTaskIds.length === 0) {
            return;
        }
        router.post(
            '/housekeeping/tasks/bulk-assign',
            {
                task_ids: selectedTaskIds,
                assigned_to_user_id: bulkAssigneeId ? Number(bulkAssigneeId) : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setSelectedTaskIds([]),
            }
        );
    };

    const handleStatusAction = (task: HousekeepingTaskRow, status: HousekeepingTaskRow['status']) => {
        router.patch(
            `/housekeeping/tasks/${task.id}/status`,
            { status },
            { preserveScroll: true }
        );
    };

    const handleGenerateStayovers = () => {
        router.post('/housekeeping/stayovers/generate', {}, { preserveScroll: true });
    };

    const handleCreateTask = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/housekeeping/tasks', {
            preserveScroll: true,
            onSuccess: () => {
                setCreateModalOpen(false);
                createForm.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Housekeeping Supervisor Board" />

            <div className="space-y-6">
                <PageHeader
                    title="Housekeeping Floor Board"
                    subtitle="Coordinate room cleanings, assign attendants by floor, and inspect turnover readiness."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Link href="/maintenance">
                                <Button variant="outline">
                                    <Wrench className="mr-2 h-4 w-4" />
                                    Maintenance ({oooCount} OOO)
                                </Button>
                            </Link>
                            <Button variant="outline" onClick={handleGenerateStayovers}>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Queue Stayovers
                            </Button>
                            <Button onClick={() => setCreateModalOpen(true)}>
                                <Plus className="mr-2 h-4 w-4" />
                                New Task
                            </Button>
                        </div>
                    }
                />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="Vacant Dirty" value={dirtyCount} icon={<BedDouble className="h-5 w-5 text-rose-500" />} />
                    <StatCard title="Cleaning in Progress" value={cleaningCount} icon={<Sparkles className="h-5 w-5 text-sky-500" />} />
                    <StatCard title="Awaiting Inspection" value={awaitingInspectionCount} icon={<ShieldCheck className="h-5 w-5 text-amber-500" />} />
                    <StatCard title="Out of Order (Blocked)" value={oooCount} icon={<AlertTriangle className="h-5 w-5 text-rose-600" />} />
                </div>

                {/* Live Floor Matrix */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Layers className="h-5 w-5 text-primary-600" />
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                                Live Room Status Grid
                            </h2>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <select
                                value={floorFilter}
                                onChange={(e) => setFloorFilter(e.target.value)}
                                className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="all">All Floors</option>
                                {floors.map((f) => (
                                    <option key={f} value={f}>
                                        Floor {f}
                                    </option>
                                ))}
                            </select>

                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="all">All Statuses</option>
                                <option value="vacant_dirty">Vacant Dirty</option>
                                <option value="cleaning">Cleaning</option>
                                <option value="vacant_clean">Vacant Clean</option>
                                <option value="occupied">Occupied</option>
                                <option value="out_of_order">Out of Order</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        {filteredRooms.map((room) => {
                            const cfg = ROOM_STATUS_BADGE[room.status] ?? {
                                label: room.status,
                                variant: 'neutral' as BadgeVariant,
                            };
                            const isBlocked = blockingSet.has(room.id) || room.status === 'out_of_order';

                            return (
                                <div
                                    key={room.id}
                                    className={`rounded-xl border p-3 transition ${
                                        isBlocked
                                            ? 'border-amber-400 bg-amber-50/40 dark:border-amber-700 dark:bg-amber-950/20'
                                            : 'border-slate-200 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-800/40'
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="text-base font-bold text-slate-900 dark:text-white">
                                            {room.room_number}
                                        </span>
                                        <span className="text-[11px] text-slate-500">Fl {room.floor_number ?? '1'}</span>
                                    </div>
                                    <p className="mt-0.5 truncate text-xs text-slate-500">
                                        {room.room_type?.name ?? 'Standard'}
                                    </p>
                                    <div className="mt-2">
                                        <Badge variant={cfg.variant} dot>
                                            {cfg.label}
                                        </Badge>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Task Queue & Bulk Assignment */}
                <div className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <div>
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                                Cleaning Queue & Inspection Sign-Off
                            </h2>
                            <p className="text-xs text-slate-500">
                                Select tasks below to bulk-assign attendants or approve inspected rooms as Vacant Clean.
                            </p>
                        </div>

                        {selectedTaskIds.length > 0 && (
                            <div className="flex flex-wrap items-center gap-2 rounded-lg bg-primary-50 px-3 py-1.5 dark:bg-primary-950/40">
                                <span className="text-xs font-semibold text-primary-800 dark:text-primary-200">
                                    {selectedTaskIds.length} selected
                                </span>
                                <select
                                    value={bulkAssigneeId}
                                    onChange={(e) => setBulkAssigneeId(e.target.value)}
                                    className="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                >
                                    <option value="">Unassign</option>
                                    {staff.map((u) => (
                                        <option key={u.id} value={u.id}>
                                            {u.name}
                                        </option>
                                    ))}
                                </select>
                                <Button size="sm" onClick={handleBulkAssign}>
                                    <UserCheck className="mr-1.5 h-3.5 w-3.5" />
                                    Apply Assignment
                                </Button>
                            </div>
                        )}
                    </div>

                    {tasks.length === 0 ? (
                        <div className="p-6">
                            <EmptyState
                                icon={<Sparkles className="h-8 w-8 text-emerald-500" />}
                                title="No Housekeeping Tasks"
                                description="Tasks created at guest checkout or queued manually will appear here."
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/60">
                                    <tr>
                                        <th className="w-10 px-4 py-3">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    assignableTasks.length > 0 &&
                                                    selectedTaskIds.length === assignableTasks.length
                                                }
                                                onChange={toggleSelectAll}
                                                className="rounded border-slate-300"
                                            />
                                        </th>
                                        <th className="px-4 py-3">Room</th>
                                        <th className="px-4 py-3">Type & Priority</th>
                                        <th className="px-4 py-3">Assignee</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Notes</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {tasks.map((task) => (
                                        <tr key={task.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/30">
                                            <td className="px-4 py-3">
                                                {task.status !== 'completed' && (
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedTaskIds.includes(task.id)}
                                                        onChange={() => toggleTaskSelect(task.id)}
                                                        className="rounded border-slate-300"
                                                    />
                                                )}
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                                Room {task.room?.room_number ?? task.room_id}
                                                <div className="text-xs font-normal text-slate-500">
                                                    Floor {task.room?.floor_number ?? '1'} • {task.room?.room_type?.name ?? 'Standard'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <span className="text-xs font-medium capitalize text-slate-700 dark:text-slate-300">
                                                        {task.task_type.replace('_', ' ')}
                                                    </span>
                                                    <Badge variant={PRIORITY_BADGE[task.priority]}>
                                                        {task.priority}
                                                    </Badge>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                                {task.assigned_to?.name ?? (
                                                    <span className="italic text-slate-400">Unassigned</span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={TASK_STATUS_BADGE[task.status]}>
                                                    {task.status.replace('_', ' ')}
                                                </Badge>
                                            </td>
                                            <td className="max-w-xs truncate px-4 py-3 text-xs text-slate-500">
                                                {task.notes || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1.5">
                                                    {task.status === 'pending' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() => handleStatusAction(task, 'in_progress')}
                                                        >
                                                            Start
                                                        </Button>
                                                    )}
                                                    {task.status === 'in_progress' && (
                                                        <Button
                                                            size="sm"
                                                            onClick={() => handleStatusAction(task, 'completed')}
                                                        >
                                                            <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                                                            Mark Clean
                                                        </Button>
                                                    )}
                                                    {task.status === 'inspected' && (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() => handleStatusAction(task, 'pending')}
                                                            >
                                                                <RotateCcw className="mr-1 h-3.5 w-3.5" />
                                                                Reject
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                onClick={() => handleStatusAction(task, 'completed')}
                                                            >
                                                                <ShieldCheck className="mr-1 h-3.5 w-3.5" />
                                                                Approve Clean
                                                            </Button>
                                                        </>
                                                    )}
                                                    {task.status === 'completed' && (
                                                        <span className="text-xs text-emerald-600">
                                                            Approved {task.inspected_by ? `by ${task.inspected_by.name}` : ''}
                                                        </span>
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

            <Modal
                open={createModalOpen}
                title="Create Housekeeping Task"
                onClose={() => setCreateModalOpen(false)}
            >
                <form onSubmit={handleCreateTask} className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Room
                            </label>
                            <select
                                required
                                value={createForm.data.room_id}
                                onChange={(e) => createForm.setData('room_id', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="">Select room...</option>
                                {rooms.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        Room {r.room_number} ({r.status.replace('_', ' ')})
                                    </option>
                                ))}
                            </select>
                            {createForm.errors.room_id && (
                                <p className="mt-1 text-xs text-rose-500">{createForm.errors.room_id}</p>
                            )}
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Assign Attendant
                            </label>
                            <select
                                value={createForm.data.assigned_to_user_id}
                                onChange={(e) => createForm.setData('assigned_to_user_id', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="">Unassigned Pool</option>
                                {staff.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Task Type
                            </label>
                            <select
                                value={createForm.data.task_type}
                                onChange={(e) => createForm.setData('task_type', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="checkout_clean">Checkout Clean</option>
                                <option value="stayover_clean">Stayover Clean</option>
                                <option value="deep_clean">Deep Clean</option>
                                <option value="turndown">Turndown Service</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Priority
                            </label>
                            <select
                                value={createForm.data.priority}
                                onChange={(e) => createForm.setData('priority', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="low">Low</option>
                                <option value="normal">Normal</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Due Date
                            </label>
                            <input
                                type="date"
                                value={createForm.data.due_date}
                                onChange={(e) => createForm.setData('due_date', e.target.value)}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Supervisor Instructions
                        </label>
                        <textarea
                            rows={2}
                            value={createForm.data.notes}
                            onChange={(e) => createForm.setData('notes', e.target.value)}
                            placeholder="Optional cleaning instructions..."
                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setCreateModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={createForm.processing}>
                            Create Task
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

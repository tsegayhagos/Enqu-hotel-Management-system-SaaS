import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Camera,
    CheckCircle2,
    Clock,
    Play,
    ShieldCheck,
    Sparkles,
    Wrench,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { EmptyState } from '@/Components/ui/EmptyState';
import type { HousekeepingTaskRow } from '@/types/models';

interface StaffMobileViewProps {
    myTasks: HousekeepingTaskRow[];
    completedToday: HousekeepingTaskRow[];
    rooms: { id: number; room_number: string; floor_number: string | number | null; status: string }[];
    canApprove: boolean;
}

const PRIORITY_BADGE: Record<HousekeepingTaskRow['priority'], 'neutral' | 'info' | 'danger'> = {
    low: 'neutral',
    normal: 'info',
    urgent: 'danger',
};

const STATUS_BADGE: Record<HousekeepingTaskRow['status'], 'neutral' | 'info' | 'warning' | 'success'> = {
    pending: 'neutral',
    in_progress: 'info',
    inspected: 'warning',
    completed: 'success',
};

const TASK_TYPE_LABEL: Record<HousekeepingTaskRow['task_type'], string> = {
    checkout_clean: 'Checkout Clean',
    stayover_clean: 'Stayover Clean',
    deep_clean: 'Deep Clean',
    turndown: 'Turndown Service',
};

export default function StaffMobileView({
    myTasks,
    completedToday,
    rooms,
    canApprove,
}: StaffMobileViewProps) {
    const [activeTab, setActiveTab] = useState<'active' | 'completed'>('active');
    const [reportModalOpen, setReportModalOpen] = useState(false);

    const maintenanceForm = useForm<{
        room_id: string;
        location_label: string;
        title: string;
        description: string;
        category: string;
        priority: string;
        blocks_room: boolean;
        photos: File[];
    }>({
        room_id: '',
        location_label: '',
        title: '',
        description: '',
        category: 'other',
        priority: 'medium',
        blocks_room: false,
        photos: [],
    });

    const handleAdvanceStatus = (task: HousekeepingTaskRow, status: HousekeepingTaskRow['status']) => {
        router.patch(
            `/housekeeping/tasks/${task.id}/status`,
            { status },
            { preserveScroll: true }
        );
    };

    const openDamageModal = (roomId?: number, roomNumber?: string) => {
        maintenanceForm.reset();
        maintenanceForm.setData({
            room_id: roomId ? String(roomId) : '',
            location_label: roomNumber ? `Room ${roomNumber}` : '',
            title: '',
            description: '',
            category: 'other',
            priority: 'medium',
            blocks_room: false,
            photos: [],
        });
        setReportModalOpen(true);
    };

    const handleSubmitDamage = (e: React.FormEvent) => {
        e.preventDefault();
        maintenanceForm.post('/maintenance/tickets', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setReportModalOpen(false);
                maintenanceForm.reset();
            },
        });
    };

    const pendingCount = myTasks.filter((t) => t.status === 'pending').length;
    const inProgressCount = myTasks.filter((t) => t.status === 'in_progress').length;
    const inspectedCount = myTasks.filter((t) => t.status === 'inspected').length;

    return (
        <AuthenticatedLayout>
            <Head title="My Cleaning Tasks" />

            <div className="mx-auto max-w-4xl space-y-6">
                <PageHeader
                    title="My Cleaning Tasks"
                    subtitle="Mobile-ready attendant checklist. Tap a room to start cleaning or report maintenance defects."
                    actions={
                        <Button variant="outline" onClick={() => openDamageModal()}>
                            <Wrench className="mr-2 h-4 w-4" />
                            Report Defect
                        </Button>
                    }
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard title="Pending" value={pendingCount} icon={<Clock className="h-5 w-5" />} />
                    <StatCard title="Cleaning Now" value={inProgressCount} icon={<Sparkles className="h-5 w-5" />} />
                    <StatCard title="Awaiting Sign-off" value={inspectedCount} icon={<ShieldCheck className="h-5 w-5" />} />
                    <StatCard title="Done Today" value={completedToday.length} icon={<CheckCircle2 className="h-5 w-5" />} />
                </div>

                <div className="flex gap-2 border-b border-slate-200 pb-2 dark:border-slate-800">
                    <button
                        type="button"
                        onClick={() => setActiveTab('active')}
                        className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${
                            activeTab === 'active'
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        Active Queue ({myTasks.length})
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('completed')}
                        className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${
                            activeTab === 'completed'
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        Completed Today ({completedToday.length})
                    </button>
                </div>

                {activeTab === 'active' ? (
                    myTasks.length === 0 ? (
                        <EmptyState
                            icon={<Sparkles className="h-8 w-8 text-emerald-500" />}
                            title="All Rooms Clean!"
                            description="You have no pending or unassigned cleaning tasks right now."
                        />
                    ) : (
                        <div className="space-y-4">
                            {myTasks.map((task) => {
                                const isUrgent = task.priority === 'urgent';
                                return (
                                    <div
                                        key={task.id}
                                        className={`rounded-xl border bg-white p-4 shadow-sm transition dark:bg-slate-900 ${
                                            isUrgent
                                                ? 'border-rose-400 ring-1 ring-rose-300 dark:border-rose-700'
                                                : 'border-slate-200 dark:border-slate-800'
                                        }`}
                                    >
                                        {isUrgent && (
                                            <div className="mb-3 flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                                                <AlertTriangle className="h-4 w-4 shrink-0" />
                                                Urgent Turnaround — Same-Day Arrival
                                            </div>
                                        )}

                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xl font-bold text-slate-900 dark:text-white">
                                                        Room {task.room?.room_number ?? task.room_id}
                                                    </span>
                                                    <Badge variant={PRIORITY_BADGE[task.priority]}>
                                                        {task.priority.toUpperCase()}
                                                    </Badge>
                                                    <Badge variant={STATUS_BADGE[task.status]}>
                                                        {task.status.replace('_', ' ').toUpperCase()}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                                    {task.room?.room_type?.name ?? 'Standard'}
                                                    {task.room?.floor_number !== null && task.room?.floor_number !== undefined
                                                        ? ` • Floor ${task.room.floor_number}`
                                                        : ''}
                                                    {' • '}
                                                    {TASK_TYPE_LABEL[task.task_type]}
                                                </p>
                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    {task.assigned_to
                                                        ? `Assigned to: ${task.assigned_to.name}`
                                                        : 'Unassigned Pool — Tap Start Cleaning to claim'}
                                                </p>
                                                {task.notes && (
                                                    <p className="mt-2 rounded-md bg-slate-50 p-2 text-xs text-slate-700 dark:bg-slate-800/70 dark:text-slate-300">
                                                        {task.notes}
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                                                {task.status === 'pending' && (
                                                    <Button
                                                        className="min-h-[44px] w-full sm:w-auto"
                                                        onClick={() => handleAdvanceStatus(task, 'in_progress')}
                                                    >
                                                        <Play className="mr-2 h-4 w-4" />
                                                        Start Cleaning
                                                    </Button>
                                                )}

                                                {task.status === 'in_progress' && (
                                                    <>
                                                        <Button
                                                            variant="outline"
                                                            className="min-h-[44px] w-full sm:w-auto"
                                                            onClick={() => handleAdvanceStatus(task, 'inspected')}
                                                        >
                                                            <ShieldCheck className="mr-2 h-4 w-4" />
                                                            Ready for Inspection
                                                        </Button>
                                                        {canApprove && (
                                                            <Button
                                                                className="min-h-[44px] w-full sm:w-auto"
                                                                onClick={() => handleAdvanceStatus(task, 'completed')}
                                                            >
                                                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                                                Mark Clean
                                                            </Button>
                                                        )}
                                                    </>
                                                )}

                                                {task.status === 'inspected' && (
                                                    canApprove ? (
                                                        <Button
                                                            className="min-h-[44px] w-full sm:w-auto"
                                                            onClick={() => handleAdvanceStatus(task, 'completed')}
                                                        >
                                                            <CheckCircle2 className="mr-2 h-4 w-4" />
                                                            Approve & Mark Clean
                                                        </Button>
                                                    ) : (
                                                        <span className="rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                                            Waiting supervisor sign-off
                                                        </span>
                                                    )
                                                )}

                                                <Button
                                                    variant="ghost"
                                                    className="min-h-[44px] w-full sm:w-auto"
                                                    onClick={() =>
                                                        openDamageModal(task.room_id, task.room?.room_number)
                                                    }
                                                >
                                                    <Wrench className="mr-1.5 h-4 w-4 text-rose-500" />
                                                    Report Issue
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )
                ) : completedToday.length === 0 ? (
                    <EmptyState
                        icon={<Clock className="h-8 w-8 text-slate-400" />}
                        title="No Completed Tasks Yet Today"
                        description="Completed cleanings signed off today will appear here."
                    />
                ) : (
                    <div className="space-y-3">
                        {completedToday.map((task) => (
                            <div
                                key={task.id}
                                className="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div>
                                    <p className="font-semibold text-slate-900 dark:text-white">
                                        Room {task.room?.room_number ?? task.room_id} — {TASK_TYPE_LABEL[task.task_type]}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        Completed at {task.completed_at ? new Date(task.completed_at).toLocaleTimeString() : '—'}
                                    </p>
                                </div>
                                <Badge variant="success">CLEAN & APPROVED</Badge>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <Modal
                open={reportModalOpen}
                title="Report Maintenance / Room Damage"
                onClose={() => setReportModalOpen(false)}
            >
                <form onSubmit={handleSubmitDamage} className="space-y-4">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Room (Optional)
                            </label>
                            <select
                                value={maintenanceForm.data.room_id}
                                onChange={(e) => {
                                    const rid = e.target.value;
                                    const selected = rooms.find((r) => String(r.id) === rid);
                                    maintenanceForm.setData({
                                        ...maintenanceForm.data,
                                        room_id: rid,
                                        location_label: selected ? `Room ${selected.room_number}` : '',
                                    });
                                }}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            >
                                <option value="">Public Area / Corridor</option>
                                {rooms.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        Room {r.room_number} ({r.status.replace('_', ' ')})
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Category
                            </label>
                            <select
                                value={maintenanceForm.data.category}
                                onChange={(e) => maintenanceForm.setData('category', e.target.value)}
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
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Issue Summary
                        </label>
                        <input
                            type="text"
                            required
                            placeholder="e.g. Leaking sink pipe or broken AC unit"
                            value={maintenanceForm.data.title}
                            onChange={(e) => maintenanceForm.setData('title', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {maintenanceForm.errors.title && (
                            <p className="mt-1 text-xs text-rose-500">{maintenanceForm.errors.title}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Priority
                            </label>
                            <select
                                value={maintenanceForm.data.priority}
                                onChange={(e) => {
                                    const p = e.target.value;
                                    maintenanceForm.setData({
                                        ...maintenanceForm.data,
                                        priority: p,
                                        blocks_room:
                                            Boolean(maintenanceForm.data.room_id) &&
                                            (p === 'high' || p === 'emergency'),
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

                        <div className="flex items-end pb-2">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    checked={maintenanceForm.data.blocks_room}
                                    disabled={!maintenanceForm.data.room_id}
                                    onChange={(e) => maintenanceForm.setData('blocks_room', e.target.checked)}
                                    className="rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                />
                                Take room Out-of-Order (Blocks check-in)
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
                            placeholder="Describe what needs repair..."
                            value={maintenanceForm.data.description}
                            onChange={(e) => maintenanceForm.setData('description', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        {maintenanceForm.errors.blocks_room && (
                            <p className="mt-1 text-xs text-rose-500">{maintenanceForm.errors.blocks_room}</p>
                        )}
                    </div>

                    <div>
                        <label className="mb-1 flex items-center gap-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                            <Camera className="h-3.5 w-3.5" />
                            Attach Photo Evidence (optional)
                        </label>
                        <input
                            type="file"
                            multiple
                            accept="image/*"
                            onChange={(e) =>
                                maintenanceForm.setData('photos', Array.from(e.target.files ?? []))
                            }
                            className="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:font-medium hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-slate-800"
                        />
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setReportModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={maintenanceForm.processing}>
                            Submit Ticket
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

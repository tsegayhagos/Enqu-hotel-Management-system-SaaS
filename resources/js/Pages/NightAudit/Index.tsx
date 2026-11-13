import { Head, useForm } from '@inertiajs/react';
import { Calendar, Clock, DollarSign, Lock, Moon, Play } from 'lucide-react';
import { useState, type FormEventHandler } from 'react';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Input,
    Modal,
    PageHeader,
    StatCard,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { NightAuditLogRow, PaginatedResponse } from '@/types';

interface Props {
    logs: PaginatedResponse<NightAuditLogRow>;
    stats: {
        last_audit_date: string;
        latest_revenue_posted: string;
        latest_tax_posted: string;
        latest_rooms_audited: number;
    };
    yesterday_date: string;
}

export default function Index({ logs, stats, yesterday_date }: Props) {
    const [auditModalOpen, setAuditModalOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        audit_date: yesterday_date,
    });

    const handleRunAudit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/night-audit/run', {
            onSuccess: () => {
                reset();
                setAuditModalOpen(false);
            },
        });
    };

    const getStatusVariant = (st: string) => {
        switch (st) {
            case 'completed':
                return 'success';
            case 'in_progress':
                return 'warning';
            case 'failed':
                return 'danger';
            default:
                return 'neutral';
        }
    };

    return (
        <AuthenticatedLayout title="Night Audit Engine">
            <Head title="Night Audit Engine" />

            <div className="space-y-6">
                <PageHeader
                    title="Automated Night Audit"
                    subtitle="Daily midnight posting of room revenue, tax balancing, and immutable ledger locking."
                    breadcrumbs={[
                        { label: 'Dashboard', href: '/dashboard' },
                        { label: 'Night Audit' },
                    ]}
                    actions={
                        <Button
                            variant="primary"
                            onClick={() => setAuditModalOpen(true)}
                        >
                            <Play className="mr-2 h-4 w-4" />
                            Run Night Audit Now
                        </Button>
                    }
                />

                {/* Key Metrics */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Last Audit Date"
                        value={stats.last_audit_date}
                        icon={<Calendar className="h-5 w-5" />}
                        subtitle="Latest closed date"
                    />
                    <StatCard
                        title="Latest Room Revenue"
                        value={`$${stats.latest_revenue_posted}`}
                        icon={<DollarSign className="h-5 w-5" />}
                        subtitle="Posted to folios"
                    />
                    <StatCard
                        title="Latest Tax & VAT"
                        value={`$${stats.latest_tax_posted}`}
                        icon={<DollarSign className="h-5 w-5" />}
                        subtitle="Statutory tax balanced"
                    />
                    <StatCard
                        title="Rooms Audited"
                        value={stats.latest_rooms_audited}
                        icon={<Moon className="h-5 w-5" />}
                        subtitle="Occupied guest rooms"
                    />
                </div>

                {/* Scheduled Cron Info Banner */}
                <Card className="flex items-center gap-4 border-l-4 border-l-primary-500 p-4">
                    <Clock className="h-6 w-6 text-primary-600 dark:text-primary-400" />
                    <div>
                        <p className="text-sm font-semibold text-slate-900 dark:text-white">
                            Scheduled Midnight Audit (`hotel:night-audit`)
                        </p>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            The night audit engine is scheduled to execute automatically every night at <strong>00:01 AM</strong>. It is idempotent and safe to run multiple times.
                        </p>
                    </div>
                </Card>

                {/* Night Audit History Table */}
                <Card className="overflow-hidden">
                    <div className="border-b border-slate-200 px-5 py-4 dark:border-enquDark-700">
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                            Audit Execution Log History
                        </h3>
                    </div>

                    {logs.data.length === 0 ? (
                        <EmptyState
                            icon={<Moon className="h-10 w-10 text-slate-400" />}
                            title="No night audit logs recorded yet"
                            description="Click 'Run Night Audit Now' to execute the audit manually for the previous calendar day."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Audit Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-center">Occupied Rooms</TableHead>
                                    <TableHead className="text-right">Room Revenue</TableHead>
                                    <TableHead className="text-right">Tax & VAT</TableHead>
                                    <TableHead>Executed By</TableHead>
                                    <TableHead>Audit Notes</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.map((log) => (
                                    <TableRow key={log.id}>
                                        <TableCell className="font-semibold text-slate-900 dark:text-white">
                                            {log.audit_date}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={getStatusVariant(log.status)}>
                                                {log.status.toUpperCase()}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-center font-medium">
                                            {log.occupied_rooms_count}
                                        </TableCell>
                                        <TableCell className="text-right font-medium text-emerald-600 dark:text-emerald-400">
                                            ${log.total_room_revenue_posted}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            ${log.total_tax_posted}
                                        </TableCell>
                                        <TableCell className="text-xs text-slate-600 dark:text-slate-400">
                                            {log.executed_by ? log.executed_by.name : 'System Scheduler (Cron)'}
                                        </TableCell>
                                        <TableCell className="max-w-xs truncate text-xs text-slate-500">
                                            {log.notes || '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </Card>
            </div>

            {/* Run Night Audit Modal */}
            <Modal
                open={auditModalOpen}
                onClose={() => setAuditModalOpen(false)}
                title="Execute Night Audit"
            >
                <form onSubmit={handleRunAudit} className="space-y-4">
                    <p className="text-sm text-slate-600 dark:text-slate-300">
                        Running the night audit will post daily room charges and statutory taxes for all currently occupied rooms, and permanently lock preceding transactions as immutable.
                    </p>

                    <div className="flex items-center gap-2 rounded-lg bg-amber-50 p-3 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                        <Lock className="h-4 w-4 shrink-0" />
                        <span>This operation is idempotent: running it again for the same date will not create duplicate charges.</span>
                    </div>

                    <Input
                        label="Audit Date"
                        type="date"
                        value={data.audit_date}
                        onChange={(e) => setData('audit_date', e.target.value)}
                        error={errors.audit_date}
                        required
                    />

                    <div className="mt-6 flex justify-end gap-3 pt-4">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setAuditModalOpen(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            isLoading={processing}
                        >
                            Confirm & Execute Audit
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

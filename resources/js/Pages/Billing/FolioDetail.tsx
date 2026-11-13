import { Head, Link, router } from '@inertiajs/react';
import {
    CreditCard,
    DollarSign,
    FileText,
    Lock,
    Printer,
    Split,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    PageHeader,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { usePermissions } from '@/lib/permissions';
import type { FolioRow, FolioSummary, FolioTransactionRow } from '@/types/models';
import PostChargeModal from './PostChargeModal';
import RecordPaymentModal from './RecordPaymentModal';
import SplitFolioModal from './SplitFolioModal';

interface Props {
    folio: FolioRow;
    summary: FolioSummary;
}

export default function FolioDetail({ folio, summary }: Props) {
    const { can } = usePermissions();
    const [chargeModalOpen, setChargeModalOpen] = useState(false);
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const [splitModalOpen, setSplitModalOpen] = useState(false);

    const isClosed = folio.status === 'closed';
    const balanceNum = Number(folio.balance);
    const transactions: FolioTransactionRow[] = folio.transactions ?? [];

    const handleCloseFolio = () => {
        if (confirm(`Are you sure you want to close folio ${folio.folio_number}?`)) {
            router.post(`/folios/${folio.id}/close`);
        }
    };

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'open':
                return 'primary';
            case 'settled':
                return 'success';
            case 'closed':
                return 'neutral';
            case 'void':
                return 'danger';
            default:
                return 'neutral';
        }
    };

    const getTypeVariant = (type: string) => {
        switch (type) {
            case 'room_charge':
                return 'primary';
            case 'tax':
                return 'neutral';
            case 'laundry':
                return 'accent';
            case 'restaurant_pos':
            case 'minibar':
                return 'warning';
            case 'payment':
                return 'success';
            case 'refund':
                return 'danger';
            default:
                return 'neutral';
        }
    };

    return (
        <AuthenticatedLayout title={`Folio ${folio.folio_number}`}>
            <Head title={`Folio ${folio.folio_number}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Folio ${folio.folio_number}`}
                    subtitle={`Guest Folio Ledger for ${folio.guest_name ?? 'Guest'} • Room ${folio.room?.room_number ?? 'N/A'}`}
                    breadcrumbs={[
                        { label: 'Dashboard', href: '/dashboard' },
                        { label: 'Billing & Folios', href: '/folios' },
                        { label: folio.folio_number },
                    ]}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Link
                                href={`/folios/${folio.id}/invoice`}
                                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-enquDark-700 dark:bg-enquDark-800 dark:text-slate-200"
                            >
                                <Printer className="h-4 w-4" />
                                Invoice View
                            </Link>

                            {!isClosed && can('folio.manage') && (
                                <Button
                                    variant="secondary"
                                    onClick={() => setSplitModalOpen(true)}
                                    disabled={transactions.length < 2}
                                >
                                    <Split className="mr-2 h-4 w-4" />
                                    Split Folio
                                </Button>
                            )}

                            {!isClosed && can('folio.manage') && (
                                <Button
                                    variant="secondary"
                                    onClick={() => setChargeModalOpen(true)}
                                >
                                    <DollarSign className="mr-2 h-4 w-4" />
                                    Post Charge
                                </Button>
                            )}

                            {!isClosed && can('payment.process') && (
                                <Button
                                    variant="primary"
                                    onClick={() => setPaymentModalOpen(true)}
                                >
                                    <CreditCard className="mr-2 h-4 w-4" />
                                    Record Payment
                                </Button>
                            )}

                            {!isClosed && can('folio.manage') && (
                                <Button
                                    variant="danger"
                                    onClick={handleCloseFolio}
                                >
                                    <XCircle className="mr-2 h-4 w-4" />
                                    Close Folio
                                </Button>
                            )}
                        </div>
                    }
                />

                {/* Live Balance Banner & Quick Metrics */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="flex flex-col justify-between p-5">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Current Balance
                        </span>
                        <div className="mt-2 flex items-baseline justify-between">
                            <span
                                className={`text-2xl font-bold ${
                                    balanceNum > 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : balanceNum === 0
                                          ? 'text-emerald-600 dark:text-emerald-400'
                                          : 'text-primary-600'
                                }`}
                            >
                                {folio.currency} {folio.balance}
                            </span>
                            <Badge variant={getStatusVariant(folio.status)}>
                                {folio.status.toUpperCase()}
                            </Badge>
                        </div>
                    </Card>

                    <Card className="p-5">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Total Charges
                        </span>
                        <p className="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                            {folio.currency} {folio.total_charges}
                        </p>
                    </Card>

                    <Card className="p-5">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Total Payments
                        </span>
                        <p className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {folio.currency} {folio.total_payments}
                        </p>
                    </Card>

                    <Card className="p-5">
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Room & Guest
                        </span>
                        <div className="mt-2">
                            <p className="font-semibold text-slate-900 dark:text-white">
                                Room {folio.room?.room_number ?? 'N/A'}
                            </p>
                            <p className="text-xs text-slate-500">
                                {folio.guest_name ?? 'Guest'} • {folio.room?.room_type?.name ?? 'Standard Room'}
                            </p>
                        </div>
                    </Card>
                </div>

                {/* Settlement Summary Breakdown */}
                <Card className="p-5">
                    <h3 className="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                        Settlement Breakdown
                    </h3>
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <h4 className="mb-2 text-xs font-semibold text-slate-500">Charges by Category</h4>
                            {Object.keys(summary.charges_by_type).length === 0 ? (
                                <p className="text-xs text-slate-400 italic">No charges posted yet</p>
                            ) : (
                                <ul className="divide-y divide-slate-100 dark:divide-enquDark-700">
                                    {Object.entries(summary.charges_by_type).map(([name, amount]) => (
                                        <li key={name} className="flex justify-between py-1.5 text-xs">
                                            <span className="text-slate-600 dark:text-slate-300">{name}</span>
                                            <span className="font-medium text-slate-900 dark:text-white">
                                                {folio.currency} {amount}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div>
                            <h4 className="mb-2 text-xs font-semibold text-slate-500">Payments by Method</h4>
                            {Object.keys(summary.payments_by_method).length === 0 ? (
                                <p className="text-xs text-slate-400 italic">No payments recorded yet</p>
                            ) : (
                                <ul className="divide-y divide-slate-100 dark:divide-enquDark-700">
                                    {Object.entries(summary.payments_by_method).map(([name, amount]) => (
                                        <li key={name} className="flex justify-between py-1.5 text-xs">
                                            <span className="text-slate-600 dark:text-slate-300">{name}</span>
                                            <span className="font-medium text-emerald-600 dark:text-emerald-400">
                                                {folio.currency} {amount}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </Card>

                {/* Chronological Transaction Table */}
                <Card className="overflow-hidden">
                    <div className="border-b border-slate-200 px-5 py-4 dark:border-enquDark-700">
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-200">
                            Itemized Transaction Ledger ({transactions.length})
                        </h3>
                    </div>

                    {transactions.length === 0 ? (
                        <EmptyState
                            icon={<FileText className="h-10 w-10 text-slate-400" />}
                            title="No transactions recorded"
                            description="Post a charge or record a payment to begin populating this folio."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date & Time</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead className="text-right">Charge</TableHead>
                                    <TableHead className="text-right">Credit / Payment</TableHead>
                                    <TableHead>Staff / Source</TableHead>
                                    <TableHead className="text-center">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.map((tx) => {
                                    const isCharge = Number(tx.amount) > 0 && tx.transaction_type !== 'refund';
                                    return (
                                        <TableRow key={tx.id}>
                                            <TableCell className="whitespace-nowrap text-xs text-slate-500">
                                                {tx.posted_at}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={getTypeVariant(tx.transaction_type)}>
                                                    {tx.transaction_type.replace('_', ' ').toUpperCase()}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="font-medium text-slate-900 dark:text-white">
                                                {tx.description}
                                            </TableCell>
                                            <TableCell className="text-xs text-slate-500">
                                                {tx.reference_id || '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {isCharge ? `${folio.currency} ${tx.amount}` : '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-medium text-emerald-600 dark:text-emerald-400">
                                                {!isCharge ? `${folio.currency} ${Math.abs(Number(tx.amount)).toFixed(2)}` : '—'}
                                            </TableCell>
                                            <TableCell className="text-xs text-slate-600 dark:text-slate-400">
                                                {tx.is_night_audit_post ? (
                                                    <span className="font-semibold text-primary-600">Night Audit Engine</span>
                                                ) : (
                                                    tx.user?.name || 'Staff'
                                                )}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {tx.is_immutable ? (
                                                    <span
                                                        title="Locked & audited by Night Audit"
                                                        className="inline-flex items-center text-xs text-slate-400"
                                                    >
                                                        <Lock className="h-3.5 w-3.5" />
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-slate-400">Pending</span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                </Card>
            </div>

            {/* Modals */}
            <PostChargeModal
                isOpen={chargeModalOpen}
                onClose={() => setChargeModalOpen(false)}
                folioId={folio.id}
                currency={folio.currency}
            />

            <RecordPaymentModal
                isOpen={paymentModalOpen}
                onClose={() => setPaymentModalOpen(false)}
                folioId={folio.id}
                balance={folio.balance}
                currency={folio.currency}
            />

            <SplitFolioModal
                isOpen={splitModalOpen}
                onClose={() => setSplitModalOpen(false)}
                folio={folio}
                transactions={transactions}
            />
        </AuthenticatedLayout>
    );
}

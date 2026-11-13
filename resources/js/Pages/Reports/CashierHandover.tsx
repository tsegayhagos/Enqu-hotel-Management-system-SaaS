import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    CreditCard,
    Download,
    Printer,
    RotateCcw,
    Smartphone,
    Wallet,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { EmptyState } from '@/Components/ui/EmptyState';
import type { CashierHandoverReportData } from '@/types/models';

interface CashierHandoverProps {
    report: CashierHandoverReportData;
    staff: { id: number; name: string; email: string }[];
    canExport: boolean;
    filters: { start_date: string; end_date: string; user_id: number | null };
}

export default function CashierHandover({
    report,
    staff,
    canExport,
    filters,
}: CashierHandoverProps) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [selectedUserId, setSelectedUserId] = useState<string>(
        filters.user_id ? String(filters.user_id) : ''
    );

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/reports/cashier-handover',
            {
                start_date: startDate,
                end_date: endDate,
                user_id: selectedUserId || undefined,
            },
            { preserveState: true }
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Cashier Shift Handover Report" />

            <div className="space-y-6">
                <PageHeader
                    title="Cashier & Shift Handover Report"
                    subtitle={`Drawer reconciliation (${report.start_date} to ${report.end_date})`}
                    actions={
                        <div className="no-print flex flex-wrap items-center gap-2">
                            <Link href="/reports">
                                <Button variant="outline">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back to Reports
                                </Button>
                            </Link>
                            <Button variant="outline" onClick={() => window.print()}>
                                <Printer className="mr-2 h-4 w-4" />
                                Print Sheet
                            </Button>
                            {canExport && (
                                <a
                                    href={`/reports/export?type=cashier_handover&start_date=${startDate}&end_date=${endDate}`}
                                >
                                    <Button>
                                        <Download className="mr-2 h-4 w-4" />
                                        Export CSV
                                    </Button>
                                </a>
                            )}
                        </div>
                    }
                />

                {/* Filter Bar */}
                <form
                    onSubmit={handleFilter}
                    className="no-print flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                            From Date
                        </label>
                        <input
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                            To Date
                        </label>
                        <input
                            type="date"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                            Cashier / Staff Member
                        </label>
                        <select
                            value={selectedUserId}
                            onChange={(e) => setSelectedUserId(e.target.value)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        >
                            <option value="">All Staff Members</option>
                            {staff.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button type="submit">Run Audit</Button>
                </form>

                {/* Method Totals */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <StatCard
                        title="Cash Drawer"
                        value={`${report.currency} ${report.totals.cash}`}
                        icon={<Banknote className="h-5 w-5 text-emerald-600" />}
                    />
                    <StatCard
                        title="Card Terminals"
                        value={`${report.currency} ${report.totals.card}`}
                        icon={<CreditCard className="h-5 w-5 text-indigo-600" />}
                    />
                    <StatCard
                        title="Mobile Money"
                        value={`${report.currency} ${report.totals.mobile_money}`}
                        icon={<Smartphone className="h-5 w-5 text-amber-500" />}
                    />
                    <StatCard
                        title="Refunds Issued"
                        value={`${report.currency} ${report.totals.refunds}`}
                        icon={<RotateCcw className="h-5 w-5 text-rose-500" />}
                    />
                    <StatCard
                        title="Net Reconciled"
                        value={`${report.currency} ${report.totals.net_collected}`}
                        subtitle={`${report.totals.transaction_count} transactions`}
                        icon={<Wallet className="h-5 w-5 text-primary-600" />}
                    />
                </div>

                {/* Per-Cashier Table */}
                <div className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                            Per-Cashier Settlement Summary
                        </h2>
                    </div>

                    {report.by_cashier.length === 0 ? (
                        <div className="p-6">
                            <EmptyState
                                icon={<Wallet className="h-8 w-8 text-slate-400" />}
                                title="No Cashier Activity"
                                description="No payments or refunds were recorded in this period."
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/60">
                                    <tr>
                                        <th className="px-4 py-3">Staff Member</th>
                                        <th className="px-4 py-3 text-right">Cash</th>
                                        <th className="px-4 py-3 text-right">Card</th>
                                        <th className="px-4 py-3 text-right">Mobile</th>
                                        <th className="px-4 py-3 text-right">Transfer</th>
                                        <th className="px-4 py-3 text-right">Refunds</th>
                                        <th className="px-4 py-3 text-right">Net Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {report.by_cashier.map((row, idx) => (
                                        <tr key={row.user_id ?? `sys-${idx}`}>
                                            <td className="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                                {row.cashier_name}
                                                <div className="text-xs font-normal text-slate-500">
                                                    {row.transaction_count} transactions
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-right">{row.cash}</td>
                                            <td className="px-4 py-3 text-right">{row.card}</td>
                                            <td className="px-4 py-3 text-right">{row.mobile_money}</td>
                                            <td className="px-4 py-3 text-right">{row.bank_transfer}</td>
                                            <td className="px-4 py-3 text-right text-rose-600">{row.refunds}</td>
                                            <td className="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">
                                                {report.currency} {row.net_collected}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Transaction Audit Log */}
                {report.transactions.length > 0 && (
                    <div className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                                Itemized Payment & Refund Ledger (Latest 100)
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-slate-200 bg-slate-50 uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/60">
                                    <tr>
                                        <th className="px-4 py-2.5">Timestamp</th>
                                        <th className="px-4 py-2.5">Cashier</th>
                                        <th className="px-4 py-2.5">Folio / Room</th>
                                        <th className="px-4 py-2.5">Method</th>
                                        <th className="px-4 py-2.5">Reference</th>
                                        <th className="px-4 py-2.5 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {report.transactions.map((tx) => (
                                        <tr key={tx.id}>
                                            <td className="px-4 py-2.5 text-slate-500">{tx.posted_at}</td>
                                            <td className="px-4 py-2.5 font-medium text-slate-800 dark:text-slate-200">
                                                {tx.cashier}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                {tx.folio_number} (Rm {tx.room_number}) — {tx.guest_name}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <Badge variant={tx.is_refund ? 'danger' : 'success'}>
                                                    {tx.is_refund ? 'Refund' : tx.payment_method}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-2.5 text-slate-500">{tx.reference_id || '—'}</td>
                                            <td
                                                className={`px-4 py-2.5 text-right font-semibold ${
                                                    tx.is_refund ? 'text-rose-600' : 'text-emerald-600'
                                                }`}
                                            >
                                                {tx.is_refund ? '-' : ''}
                                                {report.currency} {tx.amount}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

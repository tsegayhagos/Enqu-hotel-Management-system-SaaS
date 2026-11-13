import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, CreditCard, DollarSign, FileText, KeyRound } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { Badge } from '@/Components/ui/Badge';
import type { ReservationRow } from '@/types/models';

interface CheckOutModalProps {
    open: boolean;
    onClose: () => void;
    reservation: ReservationRow | null;
}

export function CheckOutModal({
    open,
    onClose,
    reservation,
}: CheckOutModalProps) {
    if (!reservation) return null;

    const folio = reservation.active_folio || reservation.folios?.[0] || null;
    const balance = folio ? parseFloat(folio.balance) : 0;
    const hasOutstandingBalance = balance > 0;

    const { data, setData, post, processing, errors, reset } = useForm({
        settle_balance: hasOutstandingBalance,
        settlement_amount: hasOutstandingBalance ? String(balance) : '0',
        payment_method: 'cash',
        confirm_keycard_returned: true,
        manager_override: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/check-out/${reservation.id}`, {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={`Check-Out & Settlement: Room ${reservation.room?.room_number || ''}`}
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Guest & Room Header */}
                <div className="rounded-lg bg-slate-50 p-4 dark:bg-slate-800/60">
                    <div className="flex items-start justify-between">
                        <div>
                            <h4 className="font-semibold text-slate-900 dark:text-slate-100">
                                {reservation.guest?.first_name} {reservation.guest?.last_name}
                            </h4>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Code: {reservation.reservation_code} • Room {reservation.room?.room_number} ({reservation.room_type?.name})
                            </p>
                        </div>
                        {reservation.guest?.vip_level && reservation.guest.vip_level !== 'standard' && (
                            <Badge variant="warning" className="text-xs uppercase">
                                {reservation.guest.vip_level}
                            </Badge>
                        )}
                    </div>

                    <div className="mt-3 grid grid-cols-2 gap-2 border-t border-slate-200/60 pt-2 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300">
                        <div>
                            <span className="text-slate-400">Check-In:</span> {reservation.check_in_date}
                        </div>
                        <div>
                            <span className="text-slate-400">Check-Out:</span> {reservation.check_out_date}
                        </div>
                        <div>
                            <span className="text-slate-400">Keycard:</span> {reservation.keycard_number || 'N/A'}
                        </div>
                        <div>
                            <span className="text-slate-400">Stays:</span> {reservation.guest?.total_stays_count ?? 0}
                        </div>
                    </div>
                </div>

                {/* Folio Balance Card */}
                {folio ? (
                    <div className="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Folio #{folio.folio_number} Balance
                            </span>
                            <Badge variant={balance > 0 ? 'danger' : 'success'}>
                                {balance > 0 ? 'Pending Balance' : 'Settled'}
                            </Badge>
                        </div>

                        <div className="mt-2 grid grid-cols-3 gap-2 text-center">
                            <div className="rounded bg-slate-50 p-2 dark:bg-slate-800/40">
                                <span className="text-xs text-slate-400">Total Charges</span>
                                <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    {folio.total_charges} {folio.currency}
                                </p>
                            </div>
                            <div className="rounded bg-slate-50 p-2 dark:bg-slate-800/40">
                                <span className="text-xs text-slate-400">Payments</span>
                                <p className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                    {folio.total_payments} {folio.currency}
                                </p>
                            </div>
                            <div className="rounded bg-amber-50 p-2 dark:bg-amber-950/40">
                                <span className="text-xs text-amber-700 dark:text-amber-300">Balance Due</span>
                                <p className={`text-base font-bold ${balance > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'}`}>
                                    {folio.balance} {folio.currency}
                                </p>
                            </div>
                        </div>

                        {/* On-the-spot settlement */}
                        {hasOutstandingBalance && (
                            <div className="mt-3 rounded border border-amber-200 bg-amber-50/50 p-3 dark:border-amber-900/50 dark:bg-amber-950/20">
                                <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        checked={data.settle_balance}
                                        onChange={(e) => setData('settle_balance', e.target.checked)}
                                        className="rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                    />
                                    Collect & Settle Outstanding Balance Now
                                </label>

                                {data.settle_balance && (
                                    <div className="mt-2 grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-medium text-slate-600 dark:text-slate-400">
                                                Settlement Amount
                                            </label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                value={data.settlement_amount}
                                                onChange={(e) => setData('settlement_amount', e.target.value)}
                                                className="mt-1 w-full rounded border border-slate-300 bg-white px-2.5 py-1 text-sm dark:border-slate-700 dark:bg-slate-900"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-slate-600 dark:text-slate-400">
                                                Payment Method
                                            </label>
                                            <select
                                                value={data.payment_method}
                                                onChange={(e) => setData('payment_method', e.target.value)}
                                                className="mt-1 w-full rounded border border-slate-300 bg-white px-2.5 py-1 text-sm dark:border-slate-700 dark:bg-slate-900"
                                            >
                                                <option value="cash">Cash</option>
                                                <option value="card">Credit/Debit Card</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="mobile_money">Mobile Money (Telebirr/CBE)</option>
                                            </select>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                ) : (
                    <p className="text-xs text-slate-500">No active folio linked to this reservation.</p>
                )}

                {/* Keycard Confirmation */}
                <div className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="keycard_returned"
                        checked={data.confirm_keycard_returned}
                        onChange={(e) => setData('confirm_keycard_returned', e.target.checked)}
                        className="rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                    />
                    <label htmlFor="keycard_returned" className="text-xs font-medium text-slate-700 dark:text-slate-300">
                        Confirm room keycard ({reservation.keycard_number || 'standard key'}) returned
                    </label>
                </div>

                {/* Unsettled Balance Override */}
                {hasOutstandingBalance && !data.settle_balance && (
                    <div className="rounded border border-red-200 bg-red-50 p-3 dark:border-red-900/50 dark:bg-red-950/40">
                        <div className="flex items-start gap-2">
                            <AlertTriangle className="h-5 w-5 text-red-600 dark:text-red-400" />
                            <div>
                                <h5 className="text-xs font-bold text-red-800 dark:text-red-200">
                                    OUTSTANDING BALANCE UNPAID
                                </h5>
                                <p className="mt-0.5 text-xs text-red-700 dark:text-red-300">
                                    Checking out without settlement will close the stay with an open debt.
                                </p>
                                <label className="mt-2 flex items-center gap-2 text-xs font-medium text-red-900 dark:text-red-200">
                                    <input
                                        type="checkbox"
                                        checked={data.manager_override}
                                        onChange={(e) => setData('manager_override', e.target.checked)}
                                        className="rounded border-red-300 text-red-600 focus:ring-red-500"
                                    />
                                    Authorize Manager Override to checkout with balance
                                </label>
                            </div>
                        </div>
                    </div>
                )}

                {(errors as any).folio && <p className="text-xs text-red-600">{(errors as any).folio}</p>}
                {(errors as any).status && <p className="text-xs text-red-600">{(errors as any).status}</p>}

                {/* Actions */}
                <div className="flex justify-between border-t border-slate-200 pt-3 dark:border-slate-800">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <div className="flex items-center gap-2">
                        {folio && (
                            <a
                                href={`/folios/${folio.id}/invoice`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                <FileText className="h-3.5 w-3.5" />
                                Print Invoice
                            </a>
                        )}
                        <Button
                            type="submit"
                            isLoading={processing}
                            disabled={hasOutstandingBalance && !data.settle_balance && !data.manager_override}
                        >
                            Finalize Check-Out ✓
                        </Button>
                    </div>
                </div>
            </form>
        </Modal>
    );
}

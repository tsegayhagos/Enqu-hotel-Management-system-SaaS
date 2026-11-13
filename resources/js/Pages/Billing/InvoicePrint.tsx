import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download, Hotel, Printer } from 'lucide-react';
import { Button } from '@/Components/ui';
import type { FolioRow, FolioSummary, HotelRow } from '@/types/models';

interface Props {
    folio: FolioRow;
    hotel: HotelRow & { address?: string; email?: string; phone?: string; tax_rate?: string };
    summary: FolioSummary;
}

export default function InvoicePrint({ folio, hotel, summary }: Props) {
    const handlePrint = () => {
        window.print();
    };

    return (
        <div className="min-h-screen bg-slate-100 p-4 sm:p-8 dark:bg-enquDark-950">
            <Head title={`Invoice - ${folio.folio_number}`} />

            {/* Print action toolbar (hidden on print) */}
            <div className="no-print mx-auto mb-6 flex max-w-4xl items-center justify-between rounded-lg bg-white p-4 shadow-xs dark:bg-enquDark-800">
                <Link
                    href={`/folios/${folio.id}`}
                    className="inline-flex items-center text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                >
                    <ArrowLeft className="mr-2 h-4 w-4" />
                    Back to Folio
                </Link>

                <div className="flex items-center gap-3">
                    <a
                        href={`/folios/${folio.id}/invoice?format=pdf`}
                        className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-xs hover:bg-slate-50 dark:border-enquDark-700 dark:bg-enquDark-800 dark:text-slate-200"
                    >
                        <Download className="h-4 w-4" />
                        Download PDF
                    </a>
                    <Button variant="primary" onClick={handlePrint}>
                        <Printer className="mr-2 h-4 w-4" />
                        Print Invoice
                    </Button>
                </div>
            </div>

            {/* Invoice Sheet */}
            <div className="mx-auto max-w-4xl rounded-lg bg-white p-8 shadow-md sm:p-12 dark:bg-enquDark-900 dark:text-white">
                {/* Header */}
                <div className="flex flex-col justify-between border-b border-primary-800/20 pb-6 sm:flex-row dark:border-enquDark-700">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-800 text-white">
                                <Hotel className="h-6 w-6" />
                            </span>
                            <h1 className="text-2xl font-bold tracking-tight text-primary-800 dark:text-primary-400">
                                {hotel.name}
                            </h1>
                        </div>
                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {hotel.address || 'Luxury Hospitality & Suites'}<br />
                            {hotel.city}, {hotel.country}<br />
                            Email: {hotel.email || 'reservations@enku.test'} • Tel: {hotel.phone || '+251-11-000-0000'}
                        </p>
                    </div>

                    <div className="mt-4 text-left sm:mt-0 sm:text-right">
                        <span className="text-xl font-bold uppercase tracking-wider text-primary-800 dark:text-primary-400">
                            TAX INVOICE
                        </span>
                        <p className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            Invoice #: {folio.folio_number}
                        </p>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Date: {new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}
                        </p>
                        <span className="mt-2 inline-block rounded bg-primary-50 px-2.5 py-0.5 text-xs font-semibold uppercase text-primary-800 dark:bg-primary-900/30 dark:text-primary-300">
                            Status: {folio.status}
                        </span>
                    </div>
                </div>

                {/* Metadata Box */}
                <div className="mt-6 grid grid-cols-1 gap-6 rounded-lg bg-slate-50 p-4 sm:grid-cols-2 dark:bg-enquDark-800">
                    <div>
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Guest Details
                        </span>
                        <p className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {folio.guest_name || 'Guest'}
                        </p>
                        <p className="text-xs text-slate-500">Currency: {folio.currency}</p>
                    </div>

                    <div>
                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Stay Information
                        </span>
                        <p className="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            Room {folio.room?.room_number || 'N/A'} ({folio.room?.room_type?.name || 'Standard'})
                        </p>
                        <p className="text-xs text-slate-500">Opened: {folio.created_at}</p>
                    </div>
                </div>

                {/* Line Items Table */}
                <div className="mt-8 overflow-hidden rounded-lg border border-slate-200 dark:border-enquDark-700">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-primary-800 text-xs uppercase text-white dark:bg-enquDark-800">
                            <tr>
                                <th className="px-4 py-3">Date</th>
                                <th className="px-4 py-3">Type</th>
                                <th className="px-4 py-3">Description</th>
                                <th className="px-4 py-3 text-right">Charges</th>
                                <th className="px-4 py-3 text-right">Credits / Payments</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 dark:divide-enquDark-700">
                            {folio.transactions?.map((tx) => {
                                const isCharge = Number(tx.amount) > 0 && tx.transaction_type !== 'refund';
                                return (
                                    <tr key={tx.id} className="text-xs">
                                        <td className="px-4 py-3 text-slate-500">{tx.posted_at}</td>
                                        <td className="px-4 py-3 font-medium uppercase text-slate-700 dark:text-slate-300">
                                            {tx.transaction_type.replace('_', ' ')}
                                        </td>
                                        <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                            {tx.description}
                                            {tx.reference_id && (
                                                <span className="ml-1 text-slate-400">[Ref: {tx.reference_id}]</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium">
                                            {isCharge ? `${folio.currency} ${tx.amount}` : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">
                                            {!isCharge ? `${folio.currency} ${Math.abs(Number(tx.amount)).toFixed(2)}` : '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                {/* Totals Section */}
                <div className="mt-6 flex justify-end">
                    <div className="w-full max-w-xs space-y-2 text-sm">
                        <div className="flex justify-between py-1 text-slate-600 dark:text-slate-300">
                            <span>Total Charges:</span>
                            <span className="font-semibold text-slate-900 dark:text-white">
                                {folio.currency} {folio.total_charges}
                            </span>
                        </div>
                        <div className="flex justify-between py-1 text-slate-600 dark:text-slate-300">
                            <span>Total Payments:</span>
                            <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                                - {folio.currency} {folio.total_payments}
                            </span>
                        </div>
                        <div className="flex justify-between border-t-2 border-primary-800 pt-2 text-base font-bold text-primary-800 dark:border-primary-400 dark:text-primary-400">
                            <span>Balance Due:</span>
                            <span>
                                {folio.currency} {folio.balance}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Footer Notes */}
                <div className="mt-12 border-t border-slate-200 pt-6 text-center text-xs text-slate-400 dark:border-enquDark-700">
                    <p>Thank you for staying at {hotel.name}. All rates include statutory taxes.</p>
                    <p className="mt-1">Generated by Enku Hotel Management SaaS.</p>
                </div>
            </div>
        </div>
    );
}

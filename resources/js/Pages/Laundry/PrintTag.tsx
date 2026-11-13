import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import type { LaundryOrderRow } from '@/types/models';

interface PrintTagProps {
    order: LaundryOrderRow;
    hotel: {
        name: string;
        phone: string | null;
        currency: string;
    };
}

function BarcodeSvg({ code }: { code: string }) {
    // Simple deterministic visual barcode pattern derived from character codes
    const bars: number[] = [];
    for (let i = 0; i < code.length; i++) {
        const charCode = code.charCodeAt(i);
        bars.push((charCode % 3) + 1, 1, ((charCode >> 2) % 3) + 1, 1);
    }

    let x = 8;
    return (
        <svg viewBox="0 0 220 48" className="mx-auto h-12 w-56">
            {bars.map((w, idx) => {
                const currentX = x;
                x += w * 2;
                const fill = idx % 2 === 0 ? '#0f172a' : 'transparent';
                return <rect key={idx} x={currentX} y={4} width={w * 2} height={36} fill={fill} />;
            })}
        </svg>
    );
}

export default function PrintTag({ order, hotel }: PrintTagProps) {
    return (
        <div className="min-h-screen bg-slate-100 p-6 print:bg-white print:p-0">
            <Head title={`Garment Tag ${order.order_number}`} />

            {/* Print toolbar hidden on print */}
            <div className="mx-auto mb-6 flex max-w-sm items-center justify-between print:hidden">
                <Link href="/laundry">
                    <Button variant="outline" size="sm">
                        <ArrowLeft className="mr-1.5 h-4 w-4" /> Back
                    </Button>
                </Link>
                <Button size="sm" onClick={() => window.print()}>
                    <Printer className="mr-1.5 h-4 w-4" /> Print Ticket
                </Button>
            </div>

            {/* Thermal Garment Ticket (80mm width style) */}
            <div className="mx-auto max-w-sm rounded-lg border border-slate-300 bg-white p-5 font-mono text-xs text-slate-900 shadow-sm print:max-w-full print:border-0 print:shadow-none">
                <div className="border-b border-dashed border-slate-400 pb-3 text-center">
                    <h1 className="text-sm font-bold uppercase tracking-wide">{hotel.name}</h1>
                    <p className="text-[11px] text-slate-500">LAUNDRY & VALET GARMENT TAG</p>
                    {hotel.phone && <p className="text-[10px] text-slate-400">Tel: {hotel.phone}</p>}
                </div>

                <div className="my-3 text-center">
                    <BarcodeSvg code={order.order_number} />
                    <p className="mt-1 text-sm font-bold tracking-widest">{order.order_number}</p>
                    {order.is_express && (
                        <div className="mt-1 inline-block border-2 border-slate-900 px-2 py-0.5 text-[11px] font-extrabold uppercase">
                            *** 2-HOUR EXPRESS ***
                        </div>
                    )}
                </div>

                <div className="space-y-1 border-y border-dashed border-slate-400 py-2">
                    <div className="flex justify-between">
                        <span>Room:</span>
                        <span className="font-bold">
                            {order.room ? `Room ${order.room.room_number}` : order.order_type.replace('_', ' ')}
                        </span>
                    </div>
                    {order.guest && (
                        <div className="flex justify-between">
                            <span>Guest:</span>
                            <span className="font-bold">
                                {order.guest.first_name} {order.guest.last_name}
                            </span>
                        </div>
                    )}
                    <div className="flex justify-between">
                        <span>Received:</span>
                        <span>{new Date(order.received_at).toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between">
                        <span>Billing:</span>
                        <span className="uppercase">{order.payment_method.replace(/_/g, ' ')}</span>
                    </div>
                </div>

                <div className="py-3">
                    <p className="mb-1.5 font-bold uppercase underline">Items List:</p>
                    <table className="w-full text-left">
                        <thead>
                            <tr className="border-b border-slate-300 text-[10px]">
                                <th className="py-1">Qty</th>
                                <th className="py-1">Garment / Service</th>
                                <th className="py-1 text-right">Amt</th>
                            </tr>
                        </thead>
                        <tbody>
                            {order.items?.map((item) => (
                                <tr key={item.id} className="border-b border-slate-100">
                                    <td className="py-1 align-top font-bold">{item.quantity}x</td>
                                    <td className="py-1">
                                        <div>{item.service?.item_name}</div>
                                        {item.notes && (
                                            <div className="text-[10px] italic text-slate-500">
                                                ({item.notes})
                                            </div>
                                        )}
                                    </td>
                                    <td className="py-1 text-right align-top">{item.subtotal}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {order.special_instructions && (
                    <div className="mb-3 border border-slate-400 p-2 text-[11px]">
                        <strong>Instructions:</strong> {order.special_instructions}
                    </div>
                )}

                <div className="flex justify-between border-t border-dashed border-slate-400 pt-2 text-sm font-bold">
                    <span>TOTAL:</span>
                    <span>
                        {order.total_amount} {hotel.currency}
                    </span>
                </div>

                <div className="mt-4 border-t border-slate-200 pt-2 text-center text-[10px] text-slate-500">
                    Processed by: {order.processed_by?.name || 'Valet Staff'}
                    <br />
                    Attach securely to garment hanger / valet bag
                </div>
            </div>
        </div>
    );
}

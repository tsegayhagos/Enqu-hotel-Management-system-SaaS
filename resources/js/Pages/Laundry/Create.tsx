import React, { useMemo, useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Minus, Plus, Shirt, ShoppingBag, Zap } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import type { LaundryServiceRow, OccupiedStayOption } from '@/types/models';

interface LaundryCreateProps {
    services: LaundryServiceRow[];
    occupiedStays: OccupiedStayOption[];
    currency: string;
}

export default function Create({
    services,
    occupiedStays,
    currency,
}: LaundryCreateProps) {
    const [activeCategory, setActiveCategory] = useState<string>('all');
    const [quantities, setQuantities] = useState<Record<number, number>>({});
    const [itemNotes, setItemNotes] = useState<Record<number, string>>({});

    const { data, setData, post, processing, errors, transform } = useForm({
        room_id: occupiedStays[0]?.room_id ? String(occupiedStays[0].room_id) : '',
        order_type: 'guest_valet' as 'guest_valet' | 'in_house_linen' | 'walk_in',
        is_express: false,
        payment_method: 'charge_to_room' as 'charge_to_room' | 'cash' | 'card' | 'unpaid',
        special_instructions: '',
        items: [] as { laundry_service_id: number; quantity: number; notes?: string }[],
    });

    const selectedStay = occupiedStays.find(
        (s) => String(s.room_id) === String(data.room_id)
    );

    const updateQty = (serviceId: number, delta: number) => {
        setQuantities((prev) => {
            const current = prev[serviceId] || 0;
            const next = Math.max(0, current + delta);
            return { ...prev, [serviceId]: next };
        });
    };

    const selectedLines = useMemo(() => {
        return services
            .filter((s) => (quantities[s.id] || 0) > 0)
            .map((s) => {
                const qty = quantities[s.id];
                const unitPrice = parseFloat(data.is_express ? s.express_price : s.standard_price);
                return {
                    service: s,
                    quantity: qty,
                    unitPrice,
                    subtotal: unitPrice * qty,
                    notes: itemNotes[s.id] || '',
                };
            });
    }, [services, quantities, data.is_express, itemNotes]);

    const grandTotal = useMemo(() => {
        return selectedLines.reduce((sum, line) => sum + line.subtotal, 0);
    }, [selectedLines]);

    const filteredServices = services.filter((s) => {
        if (activeCategory === 'all') return true;
        return s.category === activeCategory;
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((currentData) => ({
            ...currentData,
            items: selectedLines.map((line) => ({
                laundry_service_id: line.service.id,
                quantity: line.quantity,
                notes: line.notes || null,
            })),
        }));

        post('/laundry/orders');
    };

    return (
        <AuthenticatedLayout>
            <Head title="New Laundry Order" />

            <div className="space-y-6">
                <PageHeader
                    title="New Laundry & Valet Order"
                    subtitle="Select room, choose garments from the catalog, and optionally flag Express 2-hour turnaround."
                    actions={
                        <Link href="/laundry">
                            <Button variant="outline">
                                <ArrowLeft className="mr-2 h-4 w-4" /> Back to Board
                            </Button>
                        </Link>
                    }
                />

                <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Left 2 Columns: Catalog Picker */}
                    <div className="space-y-4 lg:col-span-2">
                        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                                <div className="flex flex-wrap gap-2">
                                    {['all', 'apparel', 'delicate', 'linen', 'uniform'].map((cat) => (
                                        <button
                                            key={cat}
                                            type="button"
                                            onClick={() => setActiveCategory(cat)}
                                            className={`rounded-full px-3 py-1 text-xs font-semibold capitalize transition-colors ${
                                                activeCategory === cat
                                                    ? 'bg-amber-600 text-white'
                                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'
                                            }`}
                                        >
                                            {cat}
                                        </button>
                                    ))}
                                </div>

                                {/* Express Switch */}
                                <label className="flex cursor-pointer items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
                                    <input
                                        type="checkbox"
                                        checked={data.is_express}
                                        onChange={(e) => setData('is_express', e.target.checked)}
                                        className="rounded border-red-300 text-red-600 focus:ring-red-500"
                                    />
                                    <Zap className="h-3.5 w-3.5" />
                                    2-Hour Express (+Surcharge)
                                </label>
                            </div>

                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {filteredServices.map((service) => {
                                    const qty = quantities[service.id] || 0;
                                    const activePrice = data.is_express
                                        ? service.express_price
                                        : service.standard_price;
                                    return (
                                        <div
                                            key={service.id}
                                            className={`flex flex-col justify-between rounded-lg border p-3 transition-colors ${
                                                qty > 0
                                                    ? 'border-amber-400 bg-amber-50/40 dark:border-amber-700 dark:bg-amber-950/20'
                                                    : 'border-slate-200 dark:border-slate-800'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <h4 className="font-semibold text-slate-900 dark:text-slate-100">
                                                        {service.item_name}
                                                    </h4>
                                                    <p className="text-xs capitalize text-slate-500">
                                                        {service.service_type.replace(/_/g, ' ')} • {service.category}
                                                    </p>
                                                </div>
                                                <span className="font-mono text-sm font-bold text-slate-900 dark:text-slate-100">
                                                    {activePrice} {currency}
                                                </span>
                                            </div>

                                            <div className="mt-3 flex items-center justify-between gap-2">
                                                {qty > 0 ? (
                                                    <input
                                                        type="text"
                                                        placeholder="Garment note (e.g. collar stain)..."
                                                        value={itemNotes[service.id] || ''}
                                                        onChange={(e) =>
                                                            setItemNotes((prev) => ({
                                                                ...prev,
                                                                [service.id]: e.target.value,
                                                            }))
                                                        }
                                                        className="w-full rounded border border-slate-200 bg-white px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-900"
                                                    />
                                                ) : (
                                                    <span className="text-xs text-slate-400">
                                                        Standard: {service.standard_price} / Express: {service.express_price}
                                                    </span>
                                                )}

                                                <div className="flex items-center gap-1.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => updateQty(service.id, -1)}
                                                        className="flex h-7 w-7 items-center justify-center rounded border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                                    >
                                                        <Minus className="h-3.5 w-3.5" />
                                                    </button>
                                                    <span className="w-6 text-center text-sm font-semibold">
                                                        {qty}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onClick={() => updateQty(service.id, 1)}
                                                        className="flex h-7 w-7 items-center justify-center rounded border border-amber-500 bg-amber-600 text-white hover:bg-amber-700"
                                                    >
                                                        <Plus className="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                            {errors.items && <p className="mt-2 text-xs text-red-600">{errors.items}</p>}
                        </div>
                    </div>

                    {/* Right Column: Order Details & Summary */}
                    <div className="space-y-4">
                        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">
                                Order Assignment & Billing
                            </h3>

                            <div className="mt-4 space-y-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Order Type
                                    </label>
                                    <select
                                        value={data.order_type}
                                        onChange={(e) => {
                                            const type = e.target.value as any;
                                            setData((prev) => ({
                                                ...prev,
                                                order_type: type,
                                                payment_method:
                                                    type === 'in_house_linen' ? 'unpaid' : prev.payment_method,
                                            }));
                                        }}
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    >
                                        <option value="guest_valet">Guest Valet (In-House)</option>
                                        <option value="in_house_linen">In-House Hotel Linen</option>
                                        <option value="walk_in">Walk-In Customer</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Payment / Billing Method
                                    </label>
                                    <select
                                        value={data.payment_method}
                                        onChange={(e) => setData('payment_method', e.target.value as any)}
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    >
                                        <option value="charge_to_room">Charge to Room Folio</option>
                                        <option value="cash">Cash Payment</option>
                                        <option value="card">Card Payment</option>
                                        <option value="unpaid">Unpaid / Internal</option>
                                    </select>
                                    {errors.payment_method && (
                                        <p className="mt-1 text-xs text-red-600">{errors.payment_method}</p>
                                    )}
                                </div>

                                {data.payment_method === 'charge_to_room' && (
                                    <div>
                                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                            Occupied Room (Checked-In) *
                                        </label>
                                        <select
                                            required
                                            value={data.room_id}
                                            onChange={(e) => setData('room_id', e.target.value)}
                                            className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                        >
                                            <option value="">-- Select Occupied Room --</option>
                                            {occupiedStays.map((stay) => (
                                                <option key={stay.room_id} value={stay.room_id}>
                                                    Room {stay.room_number} — {stay.guest_name} ({stay.reservation_code})
                                                </option>
                                            ))}
                                        </select>
                                        {selectedStay && (
                                            <p className="mt-1 text-xs text-emerald-600">
                                                Folio linked to: {selectedStay.guest_name}
                                            </p>
                                        )}
                                        {errors.room_id && (
                                            <p className="mt-1 text-xs text-red-600">{errors.room_id}</p>
                                        )}
                                    </div>
                                )}

                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Special Washing Instructions
                                    </label>
                                    <textarea
                                        rows={2}
                                        value={data.special_instructions}
                                        onChange={(e) => setData('special_instructions', e.target.value)}
                                        placeholder="e.g. No starch, cold water only, hang on wooden hangers..."
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                            </div>

                            {/* Selected Items Summary */}
                            <div className="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800">
                                <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    Ticket Summary ({selectedLines.length} item types)
                                </h4>
                                <div className="mt-2 space-y-1.5 text-sm">
                                    {selectedLines.length === 0 ? (
                                        <p className="text-xs text-slate-400">
                                            Tap + on items from the catalog to build this order.
                                        </p>
                                    ) : (
                                        selectedLines.map((line) => (
                                            <div key={line.service.id} className="flex justify-between">
                                                <span>
                                                    {line.quantity}x {line.service.item_name}
                                                </span>
                                                <span className="font-mono font-medium">
                                                    {line.subtotal.toFixed(2)}
                                                </span>
                                            </div>
                                        ))
                                    )}
                                </div>

                                <div className="mt-4 flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-800">
                                    <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                                        Total Charge
                                    </span>
                                    <span className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                        {grandTotal.toFixed(2)} {currency}
                                    </span>
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-4 w-full"
                                    isLoading={processing}
                                    disabled={selectedLines.length === 0}
                                >
                                    Submit Order & {data.payment_method === 'charge_to_room' ? 'Post to Folio' : 'Confirm'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

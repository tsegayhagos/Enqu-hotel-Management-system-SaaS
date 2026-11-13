import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Clock,
    Plus,
    Printer,
    Shirt,
    Sparkles,
    Tag,
    Zap,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { StatCard } from '@/Components/ui/StatCard';
import type { LaundryOrderRow } from '@/types/models';

interface LaundryIndexProps {
    orders: LaundryOrderRow[];
    currency: string;
}

const PIPELINE_COLUMNS: {
    key: LaundryOrderRow['status'];
    title: string;
    nextStatus?: LaundryOrderRow['status'];
    nextLabel?: string;
}[] = [
    { key: 'received', title: 'Received', nextStatus: 'in_wash', nextLabel: 'Start Wash' },
    { key: 'in_wash', title: 'In Wash', nextStatus: 'ironing', nextLabel: 'Move to Ironing' },
    { key: 'ironing', title: 'Ironing / Press', nextStatus: 'ready', nextLabel: 'Mark Ready' },
    { key: 'ready', title: 'Ready for Delivery', nextStatus: 'delivered', nextLabel: 'Deliver' },
    { key: 'delivered', title: 'Delivered' },
];

export default function Index({ orders, currency }: LaundryIndexProps) {
    const handleAdvance = (order: LaundryOrderRow, nextStatus: LaundryOrderRow['status']) => {
        router.patch(
            `/laundry/orders/${order.id}/status`,
            { status: nextStatus },
            { preserveScroll: true }
        );
    };

    const isExpressUrgent = (order: LaundryOrderRow): boolean => {
        if (!order.is_express || order.status === 'delivered' || order.status === 'cancelled') {
            return false;
        }
        const elapsedHours = (Date.now() - new Date(order.received_at).getTime()) / (1000 * 60 * 60);
        return elapsedHours < 2 || elapsedHours >= 2;
    };

    const activeOrdersCount = orders.filter(
        (o) => o.status !== 'delivered' && o.status !== 'cancelled'
    ).length;
    const expressCount = orders.filter(
        (o) => o.is_express && o.status !== 'delivered' && o.status !== 'cancelled'
    ).length;
    const readyCount = orders.filter((o) => o.status === 'ready').length;
    const deliveredCount = orders.filter((o) => o.status === 'delivered').length;

    return (
        <AuthenticatedLayout>
            <Head title="Laundry & Valet Pipeline" />

            <div className="space-y-6">
                <PageHeader
                    title="Laundry & Valet Pipeline"
                    subtitle="Track guest garments and hotel linen across wash, pressing, and delivery stages."
                    actions={
                        <div className="flex items-center gap-2">
                            <Link href="/laundry/services">
                                <Button variant="outline">
                                    <Tag className="mr-2 h-4 w-4" />
                                    Service Catalog
                                </Button>
                            </Link>
                            <Link href="/laundry/create">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    New Laundry Order
                                </Button>
                            </Link>
                        </div>
                    }
                />

                {/* KPIs */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Active Orders"
                        value={activeOrdersCount}
                        icon={<Shirt className="h-5 w-5 text-amber-600" />}
                        subtitle="Currently in processing"
                    />
                    <StatCard
                        title="Express Priority"
                        value={expressCount}
                        icon={<Zap className="h-5 w-5 text-red-600" />}
                        subtitle="2-hour express queue"
                    />
                    <StatCard
                        title="Ready for Delivery"
                        value={readyCount}
                        icon={<Sparkles className="h-5 w-5 text-sky-600" />}
                        subtitle="Awaiting room drop"
                    />
                    <StatCard
                        title="Delivered Orders"
                        value={deliveredCount}
                        icon={<CheckCircle2 className="h-5 w-5 text-emerald-600" />}
                        subtitle="Completed recently"
                    />
                </div>

                {/* Kanban Board */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3 xl:grid-cols-5">
                    {PIPELINE_COLUMNS.map((col) => {
                        const colOrders = orders.filter((o) => o.status === col.key);
                        return (
                            <div
                                key={col.key}
                                className="flex flex-col rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-900/50"
                            >
                                <div className="mb-3 flex items-center justify-between px-1">
                                    <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                        {col.title}
                                    </h3>
                                    <span className="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-600 shadow-sm dark:bg-slate-800 dark:text-slate-300">
                                        {colOrders.length}
                                    </span>
                                </div>

                                <div className="space-y-3">
                                    {colOrders.length === 0 ? (
                                        <div className="rounded-lg border border-dashed border-slate-200 py-8 text-center text-xs text-slate-400 dark:border-slate-800">
                                            No orders
                                        </div>
                                    ) : (
                                        colOrders.map((order) => (
                                            <div
                                                key={order.id}
                                                className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition-shadow hover:shadow dark:border-slate-800 dark:bg-slate-900"
                                            >
                                                <div className="flex items-start justify-between gap-2">
                                                    <div>
                                                        <span className="font-mono text-xs font-bold text-slate-900 dark:text-slate-100">
                                                            {order.order_number}
                                                        </span>
                                                        <p className="text-xs font-medium text-slate-700 dark:text-slate-300">
                                                            {order.room
                                                                ? `Room ${order.room.room_number}`
                                                                : order.order_type.replace('_', ' ')}
                                                            {order.guest
                                                                ? ` • ${order.guest.first_name} ${order.guest.last_name}`
                                                                : ''}
                                                        </p>
                                                    </div>

                                                    {order.is_express && (
                                                        <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-950 dark:text-red-300">
                                                            {isExpressUrgent(order) && (
                                                                <span className="h-1.5 w-1.5 animate-ping rounded-full bg-red-600" />
                                                            )}
                                                            EXPRESS
                                                        </span>
                                                    )}
                                                </div>

                                                <div className="mt-2 space-y-1 border-t border-slate-100 pt-2 text-xs text-slate-500 dark:border-slate-800">
                                                    {order.items?.slice(0, 3).map((item) => (
                                                        <div key={item.id} className="flex justify-between">
                                                            <span>
                                                                {item.quantity}x {item.service?.item_name}
                                                            </span>
                                                            <span className="font-mono">{item.subtotal}</span>
                                                        </div>
                                                    ))}
                                                    {(order.items?.length || 0) > 3 && (
                                                        <div className="text-[11px] italic text-slate-400">
                                                            +{(order.items?.length || 0) - 3} more items
                                                        </div>
                                                    )}
                                                </div>

                                                {order.special_instructions && (
                                                    <p className="mt-2 rounded bg-amber-50 px-2 py-1 text-[11px] text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
                                                        Note: {order.special_instructions}
                                                    </p>
                                                )}

                                                <div className="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 dark:border-slate-800">
                                                    <span className="text-xs font-bold text-slate-900 dark:text-slate-100">
                                                        {order.total_amount} {currency}
                                                    </span>

                                                    <div className="flex items-center gap-1.5">
                                                        <Link
                                                            href={`/laundry/orders/${order.id}/print-tag`}
                                                            className="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                                            title="Print Garment Tag"
                                                        >
                                                            <Printer className="h-3.5 w-3.5" />
                                                        </Link>
                                                        {col.nextStatus && (
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleAdvance(order, col.nextStatus!)
                                                                }
                                                            >
                                                                {col.nextLabel}
                                                                <ArrowRight className="ml-1 h-3 w-3" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

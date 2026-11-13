import React, { useMemo, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    BedDouble,
    Building2,
    CalendarDays,
    Coins,
    Download,
    PieChart,
    Receipt,
    TrendingUp,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Button } from '@/Components/ui/Button';
import type { DailyTrendPoint, KpiSummaryData } from '@/types/models';

interface PlatformHotelMetric {
    hotel_id: number;
    hotel_name: string;
    city: string | null;
    currency: string;
    total_rooms: number;
    out_of_order_rooms: number;
    occupancy_rate: number;
    adr: string;
    revpar: string;
    total_gross_revenue: string;
    payments_collected: string;
}

interface ReportsIndexProps {
    kpi: KpiSummaryData;
    trend: DailyTrendPoint[];
    platformSummary: PlatformHotelMetric[];
    canExport: boolean;
    filters: { start_date: string; end_date: string };
}

export default function Index({
    kpi,
    trend,
    platformSummary,
    canExport,
    filters,
}: ReportsIndexProps) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [hoveredIndex, setHoveredIndex] = useState<number | null>(null);

    const applyRange = (s: string, e: string) => {
        setStartDate(s);
        setEndDate(e);
        router.get('/reports', { start_date: s, end_date: e }, { preserveState: true });
    };

    const setPresetDays = (days: number) => {
        const end = new Date();
        const start = new Date();
        start.setDate(end.getDate() - (days - 1));
        applyRange(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
    };

    // SVG Revenue Line/Area Geometry
    const chartStats = useMemo(() => {
        const width = 680;
        const height = 220;
        const padX = 36;
        const padY = 20;
        const maxVal = Math.max(100, ...trend.map((d) => d.gross_revenue));
        const count = Math.max(1, trend.length);

        const coords = trend.map((point, idx) => {
            const x =
                count === 1
                    ? width / 2
                    : padX + (idx / (count - 1)) * (width - padX * 2);
            const y =
                height - padY - (point.gross_revenue / maxVal) * (height - padY * 2);
            return { x, y, point };
        });

        const polyline = coords.map((c) => `${c.x},${c.y}`).join(' ');
        const areaPath =
            coords.length > 0
                ? `M ${coords[0].x},${height - padY} ` +
                  coords.map((c) => `L ${c.x},${c.y}`).join(' ') +
                  ` L ${coords[coords.length - 1].x},${height - padY} Z`
                : '';

        return { width, height, padX, padY, maxVal, coords, polyline, areaPath };
    }, [trend]);

    // SVG Donut Segments for Departmental Breakdown
    const donutSegments = useMemo(() => {
        const total = kpi.departmental_breakdown.reduce((acc, d) => acc + d.amount_numeric, 0);
        const radius = 58;
        const circumference = 2 * Math.PI * radius;
        let cumulative = 0;

        return kpi.departmental_breakdown.map((dept) => {
            const fraction = total > 0 ? dept.amount_numeric / total : 0;
            const dash = fraction * circumference;
            const offset = -cumulative * circumference;
            cumulative += fraction;
            return { ...dept, radius, circumference, dash, offset };
        });
    }, [kpi.departmental_breakdown]);

    const hoveredPoint = hoveredIndex !== null ? chartStats.coords[hoveredIndex]?.point : null;

    return (
        <AuthenticatedLayout>
            <Head title="Executive Reports & Analytics" />

            <div className="space-y-6">
                <PageHeader
                    title="Executive Reports & Analytics"
                    subtitle={`Occupancy, ADR, RevPAR & revenue intelligence (${kpi.start_date} to ${kpi.end_date})`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Link href="/reports/cashier-handover">
                                <Button variant="outline">
                                    <Receipt className="mr-2 h-4 w-4" />
                                    Cashier Handover
                                </Button>
                            </Link>
                            <Link href="/reports/occupancy-calendar">
                                <Button variant="outline">
                                    <CalendarDays className="mr-2 h-4 w-4" />
                                    Occupancy Calendar
                                </Button>
                            </Link>
                            {canExport && (
                                <a
                                    href={`/reports/export?type=daily_summary&start_date=${startDate}&end_date=${endDate}`}
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

                {/* Date Filter Toolbar */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" onClick={() => setPresetDays(1)}>
                            Today
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => setPresetDays(7)}>
                            Last 7 Days
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => setPresetDays(30)}>
                            Last 30 Days
                        </Button>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            type="date"
                            value={startDate}
                            onChange={(e) => setStartDate(e.target.value)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        <span className="text-xs text-slate-500">to</span>
                        <input
                            type="date"
                            value={endDate}
                            onChange={(e) => setEndDate(e.target.value)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        <Button size="sm" onClick={() => applyRange(startDate, endDate)}>
                            Apply Range
                        </Button>
                    </div>
                </div>

                {/* Executive KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Occupancy Rate"
                        value={`${kpi.occupancy_rate.toFixed(1)}%`}
                        subtitle={`${kpi.occupied_room_nights} / ${kpi.available_room_nights} sellable room-nights (${kpi.out_of_order_rooms} OOO excluded)`}
                        icon={<BedDouble className="h-5 w-5 text-indigo-600" />}
                    />
                    <StatCard
                        title="ADR (Avg Daily Rate)"
                        value={`${kpi.currency} ${kpi.adr}`}
                        subtitle="Room revenue / occupied room-nights"
                        icon={<TrendingUp className="h-5 w-5 text-sky-600" />}
                    />
                    <StatCard
                        title="RevPAR"
                        value={`${kpi.currency} ${kpi.revpar}`}
                        subtitle="Room revenue / sellable room-nights"
                        icon={<BarChartIcon />}
                    />
                    <StatCard
                        title="Gross Revenue"
                        value={`${kpi.currency} ${kpi.total_gross_revenue}`}
                        subtitle={`Collected: ${kpi.currency} ${kpi.payments_collected}`}
                        icon={<Coins className="h-5 w-5 text-emerald-600" />}
                    />
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Revenue Trend SVG Line/Area Chart */}
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                                    Daily Revenue Trend
                                </h2>
                                <p className="text-xs text-slate-500">
                                    Gross posted charges across room, POS, laundry, and taxes.
                                </p>
                            </div>
                            {hoveredPoint && (
                                <div className="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-800 dark:bg-slate-800 dark:text-slate-200">
                                    <span className="font-bold">{hoveredPoint.date}:</span> {kpi.currency}{' '}
                                    {hoveredPoint.gross_revenue.toFixed(2)} gross • Rooms{' '}
                                    {hoveredPoint.room_revenue.toFixed(2)} • POS {hoveredPoint.pos_revenue.toFixed(2)}
                                </div>
                            )}
                        </div>

                        <div className="w-full overflow-x-auto">
                            <svg
                                viewBox={`0 0 ${chartStats.width} ${chartStats.height}`}
                                className="h-56 w-full min-w-[460px]"
                            >
                                <defs>
                                    <linearGradient id="revAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stopColor="#4f46e5" stopOpacity="0.28" />
                                        <stop offset="100%" stopColor="#4f46e5" stopOpacity="0.02" />
                                    </linearGradient>
                                </defs>

                                {[0.25, 0.5, 0.75, 1].map((step) => {
                                    const y =
                                        chartStats.height -
                                        chartStats.padY -
                                        step * (chartStats.height - chartStats.padY * 2);
                                    return (
                                        <g key={step}>
                                            <line
                                                x1={chartStats.padX}
                                                x2={chartStats.width - chartStats.padX}
                                                y1={y}
                                                y2={y}
                                                stroke="currentColor"
                                                className="text-slate-200 dark:text-slate-800"
                                                strokeDasharray="3 3"
                                            />
                                            <text
                                                x={4}
                                                y={y + 4}
                                                fontSize="10"
                                                fill="currentColor"
                                                className="text-slate-400"
                                            >
                                                {Math.round(chartStats.maxVal * step)}
                                            </text>
                                        </g>
                                    );
                                })}

                                {chartStats.areaPath && (
                                    <path d={chartStats.areaPath} fill="url(#revAreaGrad)" />
                                )}

                                {chartStats.polyline && (
                                    <polyline
                                        fill="none"
                                        stroke="#4f46e5"
                                        strokeWidth="2.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        points={chartStats.polyline}
                                    />
                                )}

                                {chartStats.coords.map((c, idx) => (
                                    <g
                                        key={c.point.date}
                                        onMouseEnter={() => setHoveredIndex(idx)}
                                        onMouseLeave={() => setHoveredIndex(null)}
                                        className="cursor-pointer"
                                    >
                                        <circle
                                            cx={c.x}
                                            cy={c.y}
                                            r={hoveredIndex === idx ? 5 : 3}
                                            fill="#4f46e5"
                                            stroke="#ffffff"
                                            strokeWidth="1.5"
                                        />
                                        {(trend.length <= 12 || idx % Math.ceil(trend.length / 8) === 0) && (
                                            <text
                                                x={c.x}
                                                y={chartStats.height - 4}
                                                textAnchor="middle"
                                                fontSize="10"
                                                fill="currentColor"
                                                className="text-slate-400"
                                            >
                                                {c.point.label}
                                            </text>
                                        )}
                                    </g>
                                ))}
                            </svg>
                        </div>
                    </div>

                    {/* Departmental Breakdown Donut */}
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="mb-3 flex items-center justify-between">
                            <div>
                                <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                                    Departmental Share
                                </h2>
                                <p className="text-xs text-slate-500">Revenue mix across centers</p>
                            </div>
                            {canExport && (
                                <a
                                    href={`/reports/export?type=departmental_revenue&start_date=${startDate}&end_date=${endDate}`}
                                    className="text-xs font-medium text-primary-600 hover:underline"
                                >
                                    CSV
                                </a>
                            )}
                        </div>

                        <div className="flex flex-col items-center gap-4">
                            <svg width="150" height="150" viewBox="0 0 150 150">
                                <circle
                                    cx="75"
                                    cy="75"
                                    r="58"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="18"
                                    className="text-slate-100 dark:text-slate-800"
                                />
                                <g transform="rotate(-90 75 75)">
                                    {donutSegments.map((seg) => (
                                        <circle
                                            key={seg.name}
                                            cx="75"
                                            cy="75"
                                            r={seg.radius}
                                            fill="none"
                                            stroke={seg.color}
                                            strokeWidth="18"
                                            strokeDasharray={`${seg.dash} ${seg.circumference}`}
                                            strokeDashoffset={seg.offset}
                                        />
                                    ))}
                                </g>
                                <text
                                    x="75"
                                    y="72"
                                    textAnchor="middle"
                                    fontSize="11"
                                    fill="currentColor"
                                    className="text-slate-500"
                                >
                                    Total
                                </text>
                                <text
                                    x="75"
                                    y="88"
                                    textAnchor="middle"
                                    fontSize="12"
                                    fontWeight="bold"
                                    fill="currentColor"
                                    className="text-slate-900 dark:text-white"
                                >
                                    {kpi.total_gross_revenue}
                                </text>
                            </svg>

                            <div className="w-full space-y-2">
                                {kpi.departmental_breakdown.map((dept) => (
                                    <div key={dept.name} className="flex items-center justify-between text-xs">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="h-2.5 w-2.5 rounded-full"
                                                style={{ backgroundColor: dept.color }}
                                            />
                                            <span className="text-slate-700 dark:text-slate-300">{dept.name}</span>
                                        </div>
                                        <span className="font-semibold text-slate-900 dark:text-white">
                                            {kpi.currency} {dept.amount} ({dept.percentage}%)
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Booking Sources */}
                <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 className="mb-3 text-base font-semibold text-slate-900 dark:text-white">
                        Bookings by Source Channel
                    </h2>
                    {kpi.source_breakdown.length === 0 ? (
                        <p className="text-sm text-slate-500">No bookings recorded in this date window.</p>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {kpi.source_breakdown.map((src) => (
                                <div
                                    key={src.source}
                                    className="rounded-lg border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40"
                                >
                                    <p className="text-xs font-medium text-slate-500">{src.label}</p>
                                    <p className="mt-1 text-lg font-bold text-slate-900 dark:text-white">
                                        {src.count} stays
                                    </p>
                                    <p className="text-xs text-slate-600 dark:text-slate-400">
                                        {kpi.currency} {src.revenue}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Super Admin Cross-Property Rollup */}
                {platformSummary.length > 0 && (
                    <div className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center gap-2 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                            <Building2 className="h-5 w-5 text-primary-600" />
                            <h2 className="text-base font-semibold text-slate-900 dark:text-white">
                                SaaS Multi-Property Performance Comparison
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/60">
                                    <tr>
                                        <th className="px-4 py-3">Property</th>
                                        <th className="px-4 py-3">Rooms (OOO)</th>
                                        <th className="px-4 py-3">Occupancy</th>
                                        <th className="px-4 py-3">ADR</th>
                                        <th className="px-4 py-3">RevPAR</th>
                                        <th className="px-4 py-3 text-right">Gross Revenue</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {platformSummary.map((h) => (
                                        <tr key={h.hotel_id}>
                                            <td className="px-4 py-3 font-semibold text-slate-900 dark:text-white">
                                                {h.hotel_name}
                                                <div className="text-xs font-normal text-slate-500">
                                                    {h.city ?? '—'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                                {h.total_rooms} ({h.out_of_order_rooms} OOO)
                                            </td>
                                            <td className="px-4 py-3 font-medium">
                                                {h.occupancy_rate.toFixed(1)}%
                                            </td>
                                            <td className="px-4 py-3">
                                                {h.currency} {h.adr}
                                            </td>
                                            <td className="px-4 py-3">
                                                {h.currency} {h.revpar}
                                            </td>
                                            <td className="px-4 py-3 text-right font-bold text-slate-900 dark:text-white">
                                                {h.currency} {h.total_gross_revenue}
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

function BarChartIcon() {
    return <PieChart className="h-5 w-5 text-amber-500" />;
}

import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BedDouble, ChevronLeft, ChevronRight, ShieldAlert } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Button } from '@/Components/ui/Button';
import type { OccupancyCalendarDay } from '@/types/models';

interface OccupancyCalendarProps {
    calendar: {
        month: string;
        month_label: string;
        total_rooms: number;
        out_of_order_rooms: number;
        available_rooms: number;
        days: OccupancyCalendarDay[];
    };
    prevMonth: string;
    nextMonth: string;
}

export default function OccupancyCalendar({
    calendar,
    prevMonth,
    nextMonth,
}: OccupancyCalendarProps) {
    const goToMonth = (month: string) => {
        router.get('/reports/occupancy-calendar', { month }, { preserveState: true });
    };

    const getHeatClass = (pct: number): string => {
        if (pct >= 85) {
            return 'border-emerald-300 bg-emerald-50/70 dark:border-emerald-800 dark:bg-emerald-950/30';
        }
        if (pct >= 50) {
            return 'border-sky-300 bg-sky-50/60 dark:border-sky-800 dark:bg-sky-950/30';
        }
        if (pct > 0) {
            return 'border-amber-200 bg-amber-50/50 dark:border-amber-900 dark:bg-amber-950/20';
        }
        return 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900';
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Occupancy Calendar — ${calendar.month_label}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Occupancy Calendar — ${calendar.month_label}`}
                    subtitle="Daily forecast showing sellable room occupancy, arrivals, and departures."
                    actions={
                        <div className="flex items-center gap-2">
                            <Link href="/reports">
                                <Button variant="outline">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Executive Reports
                                </Button>
                            </Link>
                            <Button variant="outline" onClick={() => goToMonth(prevMonth)}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button variant="outline" onClick={() => goToMonth(nextMonth)}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard
                        title="Total Property Inventory"
                        value={calendar.total_rooms}
                        icon={<BedDouble className="h-5 w-5 text-slate-500" />}
                    />
                    <StatCard
                        title="Sellable Rooms (Excl. OOO)"
                        value={calendar.available_rooms}
                        icon={<BedDouble className="h-5 w-5 text-emerald-500" />}
                    />
                    <StatCard
                        title="Out of Order (Blocked)"
                        value={calendar.out_of_order_rooms}
                        icon={<ShieldAlert className="h-5 w-5 text-rose-500" />}
                    />
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-7">
                    {calendar.days.map((day) => (
                        <div
                            key={day.date}
                            className={`rounded-xl border p-3 transition ${getHeatClass(day.occupancy_rate)}`}
                        >
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-bold text-slate-900 dark:text-white">
                                    {day.day}
                                </span>
                                <span className="text-[11px] font-medium text-slate-500">{day.weekday}</span>
                            </div>

                            <div className="mt-2">
                                <div className="flex items-baseline justify-between">
                                    <span className="text-lg font-extrabold text-slate-900 dark:text-white">
                                        {day.occupancy_rate}%
                                    </span>
                                    <span className="text-[11px] text-slate-500">
                                        {day.occupied}/{day.available}
                                    </span>
                                </div>
                                <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                    <div
                                        className="h-full rounded-full bg-indigo-600"
                                        style={{ width: `${Math.min(100, day.occupancy_rate)}%` }}
                                    />
                                </div>
                            </div>

                            <div className="mt-2 flex justify-between text-[11px] text-slate-600 dark:text-slate-400">
                                <span>In: {day.arrivals}</span>
                                <span>Out: {day.departures}</span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

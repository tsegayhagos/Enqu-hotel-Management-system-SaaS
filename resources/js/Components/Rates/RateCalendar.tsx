import { cn } from '@/lib/cn';
import type { CalendarDay } from '@/types/models';

interface Props {
    month: string; // YYYY-MM
    days: CalendarDay[];
}

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/** Month grid showing the effective price of every day (overrides highlighted). */
export function RateCalendar({ month, days }: Props) {
    const [year, monthIndex] = month.split('-').map(Number);
    // Monday-first offset of the 1st of the month.
    const offset = (new Date(Date.UTC(year, monthIndex - 1, 1)).getUTCDay() + 6) % 7;

    return (
        <div>
            <div className="grid grid-cols-7 gap-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {weekdays.map((day) => (
                    <div key={day} className="py-1">
                        {day}
                    </div>
                ))}
            </div>
            <div className="mt-1 grid grid-cols-7 gap-1">
                {Array.from({ length: offset }).map((_, index) => (
                    <div key={`blank-${index}`} aria-hidden />
                ))}
                {days.map((day) => (
                    <div
                        key={day.date}
                        title={day.rate_name ?? 'Base price'}
                        className={cn(
                            'flex h-16 flex-col justify-between rounded-md border p-1.5 text-left',
                            day.is_override
                                ? 'border-accent-500 bg-accent-50 dark:border-accent-500/60 dark:bg-accent-500/10'
                                : 'border-slate-200 bg-white dark:border-enquDark-700 dark:bg-enquDark-800',
                        )}
                    >
                        <span className="text-xs text-slate-500 dark:text-slate-400">{Number(day.date.slice(8, 10))}</span>
                        <span className="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {Number(day.price).toLocaleString(undefined, { maximumFractionDigits: 2 })}
                        </span>
                    </div>
                ))}
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-slate-300 bg-white dark:border-enquDark-600 dark:bg-enquDark-800" /> Base price
                </span>
                <span className="inline-flex items-center gap-1.5">
                    <span className="h-3 w-3 rounded border border-accent-500 bg-accent-50 dark:bg-accent-500/10" /> Rate override
                </span>
            </div>
        </div>
    );
}

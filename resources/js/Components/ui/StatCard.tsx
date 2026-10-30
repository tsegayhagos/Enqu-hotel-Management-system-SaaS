import React from 'react';
import { TrendingUp, TrendingDown } from 'lucide-react';
import { Card } from './Card';

export interface StatCardProps {
    title: string;
    value: string | number;
    subtitle?: string;
    change?: number; // e.g. +12.5 or -4.2
    icon?: React.ReactNode;
    trendLabel?: string;
}

export const StatCard: React.FC<StatCardProps> = ({
    title,
    value,
    subtitle,
    change,
    icon,
    trendLabel = 'vs last period',
}) => {
    const isPositive = typeof change === 'number' && change > 0;
    const isNegative = typeof change === 'number' && change < 0;

    return (
        <Card className="p-5 hover:border-primary-300 dark:hover:border-primary-700 transition-colors">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {title}
                    </p>
                    <p className="mt-2 text-2xl font-bold text-slate-900 dark:text-slate-100">{value}</p>
                </div>
                {icon && (
                    <div className="p-2.5 rounded-xl bg-primary-50 dark:bg-primary-950/50 text-primary-800 dark:text-primary-300">
                        {icon}
                    </div>
                )}
            </div>

            {(typeof change === 'number' || subtitle) && (
                <div className="mt-3 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                    {typeof change === 'number' && (
                        <span
                            className={`inline-flex items-center gap-0.5 font-semibold ${
                                isPositive
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : isNegative
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : 'text-slate-600'
                            }`}
                        >
                            {isPositive && <TrendingUp className="w-3.5 h-3.5" />}
                            {isNegative && <TrendingDown className="w-3.5 h-3.5" />}
                            {change > 0 ? `+${change}%` : `${change}%`}
                        </span>
                    )}
                    <span>{subtitle || trendLabel}</span>
                </div>
            )}
        </Card>
    );
};

import React from 'react';

export type BadgeVariant = 'success' | 'warning' | 'danger' | 'info' | 'primary' | 'accent' | 'neutral';
export type BadgeSize = 'sm' | 'md';

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
    variant?: BadgeVariant;
    size?: BadgeSize;
    dot?: boolean;
}

const variantStyles: Record<BadgeVariant, { badge: string; dot: string }> = {
    success: {
        badge: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-500/30',
        dot: 'bg-emerald-500',
    },
    warning: {
        badge: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-500/30',
        dot: 'bg-amber-500',
    },
    danger: {
        badge: 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-500/30',
        dot: 'bg-rose-500',
    },
    info: {
        badge: 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-500/30',
        dot: 'bg-sky-500',
    },
    primary: {
        badge: 'bg-primary-50 text-primary-800 ring-primary-700/20 dark:bg-primary-950/50 dark:text-primary-200 dark:ring-primary-500/30',
        dot: 'bg-primary-600',
    },
    accent: {
        badge: 'bg-amber-100 text-amber-900 ring-amber-500/30 dark:bg-amber-950/60 dark:text-amber-200 dark:ring-amber-400/30',
        dot: 'bg-amber-500',
    },
    neutral: {
        badge: 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700/30',
        dot: 'bg-slate-400',
    },
};

const sizeStyles: Record<BadgeSize, string> = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-xs',
};

export const Badge: React.FC<BadgeProps> = ({
    variant = 'neutral',
    size = 'sm',
    dot = false,
    className = '',
    children,
    ...props
}) => {
    const styles = variantStyles[variant];

    return (
        <span
            className={`inline-flex items-center gap-1.5 font-medium rounded-full ring-1 ring-inset ${styles.badge} ${sizeStyles[size]} ${className}`}
            {...props}
        >
            {dot && <span className={`w-1.5 h-1.5 rounded-full shrink-0 ${styles.dot}`} />}
            {children}
        </span>
    );
};

import React, { ButtonHTMLAttributes } from 'react';
import { Loader2 } from 'lucide-react';

export type ButtonVariant = 'primary' | 'accent' | 'secondary' | 'outline' | 'danger' | 'ghost';
export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg';

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    isLoading?: boolean;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
}

const variantStyles: Record<ButtonVariant, string> = {
    primary:
        'bg-primary-800 hover:bg-primary-700 active:bg-primary-900 text-white shadow-sm focus-visible:ring-primary-500',
    accent:
        'bg-accent-500 hover:bg-accent-600 active:bg-accent-700 text-slate-950 font-semibold shadow-sm focus-visible:ring-accent-400',
    secondary:
        'bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 focus-visible:ring-slate-400',
    outline:
        'border border-primary-700 text-primary-800 hover:bg-primary-50 active:bg-primary-100 dark:border-primary-400 dark:text-primary-300 dark:hover:bg-primary-950/40 focus-visible:ring-primary-500',
    danger:
        'bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white shadow-sm focus-visible:ring-rose-500',
    ghost:
        'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100 focus-visible:ring-slate-400',
};

const sizeStyles: Record<ButtonSize, string> = {
    xs: 'px-2.5 py-1 text-xs gap-1 rounded',
    sm: 'px-3 py-1.5 text-xs gap-1.5 rounded-md',
    md: 'px-4 py-2 text-sm gap-2 rounded-lg',
    lg: 'px-5 py-2.5 text-base gap-2.5 rounded-lg',
};

export const Button: React.FC<ButtonProps> = ({
    variant = 'primary',
    size = 'md',
    isLoading = false,
    leftIcon,
    rightIcon,
    className = '',
    disabled,
    children,
    ...props
}) => {
    return (
        <button
            disabled={disabled || isLoading}
            className={`inline-flex items-center justify-center font-medium transition-colors duration-150 select-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none ${variantStyles[variant]} ${sizeStyles[size]} ${className}`}
            {...props}
        >
            {isLoading ? (
                <Loader2 className="w-4 h-4 animate-spin shrink-0" />
            ) : (
                leftIcon && <span className="shrink-0">{leftIcon}</span>
            )}
            <span>{children}</span>
            {!isLoading && rightIcon && <span className="shrink-0">{rightIcon}</span>}
        </button>
    );
};

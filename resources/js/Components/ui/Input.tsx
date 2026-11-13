import React, { InputHTMLAttributes, forwardRef } from 'react';

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    helperText?: string;
    hint?: string;
    leftIcon?: React.ReactNode;
    rightIcon?: React.ReactNode;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
    ({ label, error, helperText, hint, leftIcon, rightIcon, className = '', id, required, ...props }, ref) => {
        const inputId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);
        const helpMessage = hint ?? helperText;

        return (
            <div className="w-full">
                {label && (
                    <label htmlFor={inputId} className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        {label} {required && <span className="text-rose-500">*</span>}
                    </label>
                )}
                <div className="relative rounded-lg shadow-sm">
                    {leftIcon && (
                        <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            {leftIcon}
                        </div>
                    )}
                    <input
                        ref={ref}
                        id={inputId}
                        required={required}
                        className={`block w-full rounded-lg border py-2 text-sm transition-colors placeholder:text-slate-400 dark:bg-slate-900 dark:text-slate-100 ${
                            leftIcon ? 'pl-9' : 'pl-3'
                        } ${rightIcon ? 'pr-9' : 'pr-3'} ${
                            error
                                ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500/20 text-rose-900 dark:text-rose-200'
                                : 'border-slate-300 dark:border-slate-700 focus:border-primary-700 focus:ring-primary-700/20'
                        } focus:outline-none focus:ring-2 ${className}`}
                        {...props}
                    />
                    {rightIcon && (
                        <div className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                            {rightIcon}
                        </div>
                    )}
                </div>
                {error && <p className="mt-1 text-xs text-rose-600 dark:text-rose-400">{error}</p>}
                {!error && helpMessage && <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{helpMessage}</p>}
            </div>
        );
    }
);

Input.displayName = 'Input';

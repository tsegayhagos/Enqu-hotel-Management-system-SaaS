import React, { SelectHTMLAttributes, forwardRef } from 'react';

export interface SelectOption {
    value: string | number;
    label: string;
    disabled?: boolean;
}

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    error?: string;
    helperText?: string;
    options?: SelectOption[];
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
    ({ label, error, helperText, options, className = '', id, required, children, ...props }, ref) => {
        const selectId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : undefined);

        return (
            <div className="w-full">
                {label && (
                    <label htmlFor={selectId} className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        {label} {required && <span className="text-rose-500">*</span>}
                    </label>
                )}
                <select
                    ref={ref}
                    id={selectId}
                    required={required}
                    className={`block w-full rounded-lg border py-2 px-3 text-sm transition-colors dark:bg-slate-900 dark:text-slate-100 ${
                        error
                            ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-500/20 text-rose-900 dark:text-rose-200'
                            : 'border-slate-300 dark:border-slate-700 focus:border-primary-700 focus:ring-primary-700/20'
                    } focus:outline-none focus:ring-2 ${className}`}
                    {...props}
                >
                    {options
                        ? options.map((opt) => (
                              <option key={opt.value} value={opt.value} disabled={opt.disabled}>
                                  {opt.label}
                              </option>
                          ))
                        : children}
                </select>
                {error && <p className="mt-1 text-xs text-rose-600 dark:text-rose-400">{error}</p>}
                {!error && helperText && <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{helperText}</p>}
            </div>
        );
    }
);

Select.displayName = 'Select';

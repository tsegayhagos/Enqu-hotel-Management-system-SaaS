import { forwardRef, useId, type SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';
import type { SelectOption } from '@/types';

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
    label?: string;
    error?: string;
    options: SelectOption[];
    placeholder?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
    { label, error, options, placeholder, required, className, id, ...props },
    ref,
) {
    const generated = useId();
    const selectId = id ?? generated;

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={selectId} className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                    {label}
                    {required && <span className="ml-0.5 text-red-500">*</span>}
                </label>
            )}
            <select
                ref={ref}
                id={selectId}
                required={required}
                aria-invalid={error ? true : undefined}
                className={cn(
                    'block h-10 w-full rounded-lg border bg-white px-3 text-sm text-slate-900',
                    'focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30',
                    'dark:bg-enquDark-900 dark:text-slate-100',
                    error ? 'border-red-500' : 'border-slate-300 dark:border-enquDark-600',
                    className,
                )}
                {...props}
            >
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
            {error && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
});

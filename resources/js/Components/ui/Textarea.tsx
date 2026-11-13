import { forwardRef, useId, type TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

export interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
    label?: string;
    error?: string;
    hint?: string;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
    { label, error, hint, required, className, id, rows = 3, ...props },
    ref,
) {
    const generated = useId();
    const fieldId = id ?? generated;

    return (
        <div className="w-full">
            {label && (
                <label htmlFor={fieldId} className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                    {label}
                    {required && <span className="ml-0.5 text-red-500">*</span>}
                </label>
            )}
            <textarea
                ref={ref}
                id={fieldId}
                rows={rows}
                required={required}
                aria-invalid={error ? true : undefined}
                className={cn(
                    'block w-full rounded-lg border bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400',
                    'focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30',
                    'dark:bg-enquDark-900 dark:text-slate-100 dark:placeholder:text-slate-500',
                    error ? 'border-red-500' : 'border-slate-300 dark:border-enquDark-600',
                    className,
                )}
                {...props}
            />
            {error ? (
                <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>
            ) : (
                hint && <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
            )}
        </div>
    );
});

import React from 'react';
import { Inbox } from 'lucide-react';
import { Button, ButtonProps } from './Button';

export interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: React.ReactNode;
    action?: {
        label: string;
        onClick?: () => void;
        href?: string;
        variant?: ButtonProps['variant'];
    };
}

export const EmptyState: React.FC<EmptyStateProps> = ({
    title,
    description,
    icon = <Inbox className="w-10 h-10 text-slate-300 dark:text-slate-600" />,
    action,
}) => {
    return (
        <div className="flex min-h-[260px] flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 p-8 text-center bg-slate-50/50 dark:bg-slate-900/30">
            <div className="mb-3 flex items-center justify-center rounded-full bg-white dark:bg-slate-800 p-3 shadow-xs">
                {icon}
            </div>
            <h3 className="text-base font-semibold text-slate-900 dark:text-slate-100">{title}</h3>
            {description && (
                <p className="mt-1.5 max-w-sm text-sm text-slate-500 dark:text-slate-400">{description}</p>
            )}
            {action && (
                <div className="mt-5">
                    {action.href ? (
                        <a href={action.href}>
                            <Button variant={action.variant || 'primary'} size="sm">
                                {action.label}
                            </Button>
                        </a>
                    ) : (
                        <Button variant={action.variant || 'primary'} size="sm" onClick={action.onClick}>
                            {action.label}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
};

import React from 'react';

export interface PageHeaderProps {
    title: string;
    subtitle?: string;
    actions?: React.ReactNode;
    breadcrumbs?: Array<{ label: string; href?: string }>;
}

export const PageHeader: React.FC<PageHeaderProps> = ({ title, subtitle, actions, breadcrumbs }) => {
    return (
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <nav className="mb-1.5 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                        {breadcrumbs.map((crumb, idx) => (
                            <React.Fragment key={idx}>
                                {idx > 0 && <span>/</span>}
                                {crumb.href ? (
                                    <a href={crumb.href} className="hover:text-primary-700 transition-colors">
                                        {crumb.label}
                                    </a>
                                ) : (
                                    <span className="font-medium text-slate-800 dark:text-slate-200">{crumb.label}</span>
                                )}
                            </React.Fragment>
                        ))}
                    </nav>
                )}
                <h1 className="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    {title}
                </h1>
                {subtitle && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{subtitle}</p>}
            </div>

            {actions && <div className="flex flex-wrap items-center gap-2.5">{actions}</div>}
        </div>
    );
};

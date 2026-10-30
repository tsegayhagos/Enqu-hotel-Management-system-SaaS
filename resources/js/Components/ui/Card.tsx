import React, { HTMLAttributes } from 'react';

export const Card: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className = '', children, ...props }) => {
    return (
        <div
            className={`bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-hidden ${className}`}
            {...props}
        >
            {children}
        </div>
    );
};

export const CardHeader: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className = '', children, ...props }) => {
    return (
        <div className={`p-5 sm:p-6 border-b border-slate-100 dark:border-slate-800 ${className}`} {...props}>
            {children}
        </div>
    );
};

export const CardTitle: React.FC<HTMLAttributes<HTMLHeadingElement>> = ({ className = '', children, ...props }) => {
    return (
        <h3 className={`text-base sm:text-lg font-semibold text-slate-900 dark:text-slate-100 ${className}`} {...props}>
            {children}
        </h3>
    );
};

export const CardDescription: React.FC<HTMLAttributes<HTMLParagraphElement>> = ({
    className = '',
    children,
    ...props
}) => {
    return (
        <p className={`mt-1 text-sm text-slate-500 dark:text-slate-400 ${className}`} {...props}>
            {children}
        </p>
    );
};

export const CardContent: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className = '', children, ...props }) => {
    return (
        <div className={`p-5 sm:p-6 ${className}`} {...props}>
            {children}
        </div>
    );
};

export const CardFooter: React.FC<HTMLAttributes<HTMLDivElement>> = ({ className = '', children, ...props }) => {
    return (
        <div
            className={`p-4 sm:p-6 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between ${className}`}
            {...props}
        >
            {children}
        </div>
    );
};

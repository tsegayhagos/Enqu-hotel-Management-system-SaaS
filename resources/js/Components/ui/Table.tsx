import React, { HTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from 'react';

export const Table: React.FC<HTMLAttributes<HTMLTableElement>> = ({ className = '', children, ...props }) => {
    return (
        <div className="w-full overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
            <table className={`w-full text-left text-sm text-slate-600 dark:text-slate-300 ${className}`} {...props}>
                {children}
            </table>
        </div>
    );
};

export const TableHeader: React.FC<HTMLAttributes<HTMLTableSectionElement>> = ({ className = '', children, ...props }) => {
    return (
        <thead
            className={`bg-slate-50 dark:bg-slate-900/80 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800 ${className}`}
            {...props}
        >
            {children}
        </thead>
    );
};

export const TableBody: React.FC<HTMLAttributes<HTMLTableSectionElement>> = ({ className = '', children, ...props }) => {
    return (
        <tbody className={`divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900 ${className}`} {...props}>
            {children}
        </tbody>
    );
};

export const TableRow: React.FC<HTMLAttributes<HTMLTableRowElement>> = ({ className = '', children, ...props }) => {
    return (
        <tr
            className={`hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors ${className}`}
            {...props}
        >
            {children}
        </tr>
    );
};

export const TableHead: React.FC<ThHTMLAttributes<HTMLTableCellElement>> = ({ className = '', children, ...props }) => {
    return (
        <th className={`px-4 py-3 sm:px-6 ${className}`} {...props}>
            {children}
        </th>
    );
};

export const TableCell: React.FC<TdHTMLAttributes<HTMLTableCellElement>> = ({ className = '', children, ...props }) => {
    return (
        <td className={`px-4 py-3.5 sm:px-6 align-middle ${className}`} {...props}>
            {children}
        </td>
    );
};

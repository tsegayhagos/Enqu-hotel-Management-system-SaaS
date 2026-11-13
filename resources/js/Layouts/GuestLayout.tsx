import { Hotel } from 'lucide-react';
import type { ReactNode } from 'react';

export default function GuestLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-10 dark:bg-enquDark-900">
            <div className="mb-6 flex items-center gap-3">
                <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-800 text-white dark:bg-primary-500">
                    <Hotel className="h-6 w-6" aria-hidden />
                </span>
                <div>
                    <p className="text-lg font-semibold text-slate-900 dark:text-slate-50">Enku PMS</p>
                    <p className="text-xs text-slate-500 dark:text-slate-400">Hotel Management Suite</p>
                </div>
            </div>
            <div className="w-full max-w-md">{children}</div>
        </div>
    );
}

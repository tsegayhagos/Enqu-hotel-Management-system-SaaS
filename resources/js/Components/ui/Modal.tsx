import { X } from 'lucide-react';
import { useEffect, type ReactNode } from 'react';

export interface ModalProps {
    open: boolean;
    title: string;
    onClose: () => void;
    children: ReactNode;
}

/** Accessible dialog used for quick actions (e.g. staff invite, modals). */
export function Modal({ open, title, onClose, children }: ModalProps) {
    useEffect(() => {
        if (!open) {
            return undefined;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-label={title}>
            <div className="absolute inset-0 bg-enquDark-900/60 backdrop-blur-sm" onClick={onClose} aria-hidden />
            <div className="relative w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-xl dark:border-enquDark-700 dark:bg-enquDark-800">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-enquDark-700">
                    <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md p-1 text-slate-500 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-enquDark-700"
                        aria-label="Close"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <div className="p-5">{children}</div>
            </div>
        </div>
    );
}

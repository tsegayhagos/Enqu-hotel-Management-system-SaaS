import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { Button, Input, Modal } from '@/Components/ui';
import type { FolioRow, FolioTransactionRow } from '@/types/models';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    folio: FolioRow;
    transactions: FolioTransactionRow[];
}

export default function SplitFolioModal({ isOpen, onClose, folio, transactions }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        transaction_ids: [] as number[],
        new_guest_name: '',
    });

    const toggleTransaction = (id: number) => {
        setData(
            'transaction_ids',
            data.transaction_ids.includes(id)
                ? data.transaction_ids.filter((item) => item !== id)
                : [...data.transaction_ids, id],
        );
    };

    const toggleAll = () => {
        if (data.transaction_ids.length === transactions.length) {
            setData('transaction_ids', []);
        } else {
            setData(
                'transaction_ids',
                transactions.map((t) => t.id),
            );
        }
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/folios/${folio.id}/split`, {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal open={isOpen} onClose={onClose} title="Split Folio Charges">
            <form onSubmit={handleSubmit} className="space-y-4">
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    Select the transactions you want to transfer into a separate split folio (e.g. Company Master
                    Account vs Personal Extras).
                </p>

                <Input
                    label="New Folio Name / Entity"
                    placeholder="e.g. Acme Corp (Company Folio) or Guest Personal"
                    value={data.new_guest_name}
                    onChange={(e) => setData('new_guest_name', e.target.value)}
                    error={errors.new_guest_name}
                />

                <div className="space-y-2">
                    <div className="flex items-center justify-between border-b pb-2 text-xs font-semibold uppercase text-slate-500">
                        <span>Select Line Items</span>
                        <button
                            type="button"
                            onClick={toggleAll}
                            className="text-primary-600 hover:underline dark:text-primary-400"
                        >
                            {data.transaction_ids.length === transactions.length ? 'Deselect All' : 'Select All'}
                        </button>
                    </div>

                    <div className="max-h-60 space-y-2 overflow-y-auto pr-1">
                        {transactions.map((tx) => (
                            <label
                                key={tx.id}
                                className="flex cursor-pointer items-center justify-between rounded-md border border-slate-200 p-2.5 text-sm hover:bg-slate-50 dark:border-enquDark-700 dark:hover:bg-enquDark-800"
                            >
                                <div className="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        checked={data.transaction_ids.includes(tx.id)}
                                        onChange={() => toggleTransaction(tx.id)}
                                        className="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500"
                                    />
                                    <div>
                                        <p className="font-medium text-slate-900 dark:text-white">{tx.description}</p>
                                        <p className="text-xs text-slate-500">{tx.posted_at}</p>
                                    </div>
                                </div>
                                <span className="font-semibold text-slate-900 dark:text-white">
                                    {folio.currency} {tx.amount}
                                </span>
                            </label>
                        ))}
                    </div>
                </div>

                {errors.transaction_ids && (
                    <p className="text-xs text-rose-500">{errors.transaction_ids}</p>
                )}

                <div className="mt-6 flex justify-end gap-3 pt-4">
                    <Button type="button" variant="secondary" onClick={onClose} disabled={processing}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        isLoading={processing}
                        disabled={data.transaction_ids.length === 0}
                    >
                        Create Split Folio ({data.transaction_ids.length})
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

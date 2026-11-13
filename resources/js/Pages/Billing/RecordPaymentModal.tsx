import { useForm } from '@inertiajs/react';
import { useEffect, type FormEventHandler } from 'react';
import { Button, Input, Modal, Select } from '@/Components/ui';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    folioId: number;
    balance: string;
    currency: string;
}

export default function RecordPaymentModal({ isOpen, onClose, folioId, balance, currency }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        payment_method: 'credit_card',
        amount: '',
        reference_id: '',
        description: '',
    });

    useEffect(() => {
        if (isOpen && Number(balance) > 0) {
            setData((prev) => ({
                ...prev,
                amount: balance,
            }));
        }
    }, [isOpen, balance]);

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/folios/${folioId}/payments`, {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal open={isOpen} onClose={onClose} title="Record Payment / Settlement">
            <form onSubmit={handleSubmit} className="space-y-4">
                <div className="rounded-lg bg-slate-50 p-3 text-sm dark:bg-enquDark-800">
                    <span className="text-slate-500 dark:text-slate-400">Current Outstanding Balance: </span>
                    <strong className="font-semibold text-primary-800 dark:text-primary-400">
                        {currency} {balance}
                    </strong>
                </div>

                <Select
                    label="Payment Method"
                    value={data.payment_method}
                    onChange={(e) => setData('payment_method', e.target.value)}
                    error={errors.payment_method}
                    options={[
                        { value: 'cash', label: 'Cash' },
                        { value: 'credit_card', label: 'Credit Card (Visa / Mastercard / Amex)' },
                        { value: 'debit_card', label: 'Debit Card' },
                        { value: 'mobile_money', label: 'Mobile Money (Telebirr / M-Pesa)' },
                        { value: 'bank_transfer', label: 'Bank Transfer' },
                        { value: 'complimentary', label: 'Complimentary / Hotel Courtesy' },
                    ]}
                />

                <Input
                    label={`Payment Amount (${currency})`}
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="0.00"
                    value={data.amount}
                    onChange={(e) => setData('amount', e.target.value)}
                    error={errors.amount}
                    required
                />

                <Input
                    label="Reference / Authorization Code"
                    placeholder="e.g. TXN-89421 or Approval Code"
                    value={data.reference_id}
                    onChange={(e) => setData('reference_id', e.target.value)}
                    error={errors.reference_id}
                />

                <Input
                    label="Notes / Description (Optional)"
                    placeholder="e.g. Paid in full at front desk checkout"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    error={errors.description}
                />

                <div className="mt-6 flex justify-end gap-3 pt-4">
                    <Button type="button" variant="secondary" onClick={onClose} disabled={processing}>
                        Cancel
                    </Button>
                    <Button type="submit" variant="primary" isLoading={processing}>
                        Record Payment
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { Button, Input, Modal, Select } from '@/Components/ui';

interface Props {
    isOpen: boolean;
    onClose: () => void;
    folioId: number;
    currency: string;
}

export default function PostChargeModal({ isOpen, onClose, folioId, currency }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        transaction_type: 'restaurant_pos',
        amount: '',
        description: '',
        reference_id: '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/folios/${folioId}/charges`, {
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal open={isOpen} onClose={onClose} title="Post Charge to Guest Folio">
            <form onSubmit={handleSubmit} className="space-y-4">
                <Select
                    label="Charge Type"
                    value={data.transaction_type}
                    onChange={(e) => setData('transaction_type', e.target.value)}
                    error={errors.transaction_type}
                    options={[
                        { value: 'restaurant_pos', label: 'Restaurant / Bar POS' },
                        { value: 'minibar', label: 'Minibar' },
                        { value: 'laundry', label: 'Laundry & Valet' },
                        { value: 'room_charge', label: 'Manual Room Charge' },
                        { value: 'tax', label: 'Tax & VAT' },
                        { value: 'fee', label: 'Extra Fee / Amenity' },
                    ]}
                />

                <Input
                    label={`Amount (${currency})`}
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
                    label="Description"
                    placeholder="e.g. Dinner Buffet - 2 Guests"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    error={errors.description}
                    required
                />

                <Input
                    label="Reference / Order #"
                    placeholder="e.g. POS-9842"
                    value={data.reference_id}
                    onChange={(e) => setData('reference_id', e.target.value)}
                    error={errors.reference_id}
                />

                <div className="mt-6 flex justify-end gap-3 pt-4">
                    <Button type="button" variant="secondary" onClick={onClose} disabled={processing}>
                        Cancel
                    </Button>
                    <Button type="submit" variant="primary" isLoading={processing}>
                        Post Charge
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

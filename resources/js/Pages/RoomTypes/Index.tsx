import { router, useForm, usePage } from '@inertiajs/react';
import { Layers, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Input,
    Modal,
    PageHeader,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    Textarea,
} from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { SharedProps } from '@/types';
import type { RoomTypeRow } from '@/types/models';

interface Props {
    roomTypes: RoomTypeRow[];
}

interface TypeForm {
    name: string;
    description: string;
    base_occupancy: string;
    max_occupancy: string;
    base_price: string;
    extra_person_fee: string;
    amenities: string; // comma separated in the UI, sent as an array
}

const emptyForm: TypeForm = {
    name: '',
    description: '',
    base_occupancy: '2',
    max_occupancy: '4',
    base_price: '',
    extra_person_fee: '0.00',
    amenities: '',
};

export default function RoomTypesIndex({ roomTypes }: Props) {
    const { hotel } = usePage<SharedProps>().props;
    const [editing, setEditing] = useState<RoomTypeRow | null>(null);
    const [open, setOpen] = useState(false);

    const form = useForm<TypeForm>({ ...emptyForm });

    const money = (value: string) => `${hotel?.currency ?? ''} ${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2 })}`;

    const openCreate = () => {
        setEditing(null);
        form.setData({ ...emptyForm });
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (type: RoomTypeRow) => {
        setEditing(type);
        form.setData({
            name: type.name,
            description: type.description ?? '',
            base_occupancy: String(type.base_occupancy),
            max_occupancy: String(type.max_occupancy),
            base_price: type.base_price,
            extra_person_fee: type.extra_person_fee,
            amenities: type.amenities.join(', '),
        });
        form.clearErrors();
        setOpen(true);
    };

    const close = () => {
        setOpen(false);
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            amenities: data.amenities
                .split(',')
                .map((item) => item.trim())
                .filter((item) => item !== ''),
        }));

        const options = { preserveScroll: true, onSuccess: () => close() };

        if (editing) {
            form.put(`/room-types/${editing.id}`, options);
        } else {
            form.post('/room-types', options);
        }
    };

    const remove = (type: RoomTypeRow) => {
        if (window.confirm(`Delete the room type "${type.name}"?`)) {
            router.delete(`/room-types/${type.id}`, {
                preserveScroll: true,
                onError: (errors) => window.alert(Object.values(errors)[0] ?? 'Could not delete this room type.'),
            });
        }
    };

    return (
        <AuthenticatedLayout title="Room types">
            <PageHeader
                title="Room types"
                subtitle="Categories, base rates and amenities"
                breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Room types' }]}
                actions={
                    <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>
                        New room type
                    </Button>
                }
            />

            <Card>
                {roomTypes.length === 0 ? (
                    <EmptyState
                        icon={<Layers className="h-6 w-6" />}
                        title="No room types yet"
                        description="Create categories such as Standard, Deluxe or Suite, then add rooms to them."
                        action={
                            <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>
                                New room type
                            </Button>
                        }
                    />
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Occupancy</TableHead>
                                <TableHead>Base price / night</TableHead>
                                <TableHead>Extra person</TableHead>
                                <TableHead>Amenities</TableHead>
                                <TableHead>Rooms</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roomTypes.map((type) => (
                                <TableRow key={type.id}>
                                    <TableCell>
                                        <p className="font-medium text-slate-900 dark:text-slate-100">{type.name}</p>
                                        {type.description && <p className="max-w-xs truncate text-xs text-slate-500">{type.description}</p>}
                                    </TableCell>
                                    <TableCell>
                                        {type.base_occupancy} - {type.max_occupancy}
                                    </TableCell>
                                    <TableCell>{money(type.base_price)}</TableCell>
                                    <TableCell>{money(type.extra_person_fee)}</TableCell>
                                    <TableCell>
                                        <div className="flex max-w-xs flex-wrap gap-1">
                                            {type.amenities.map((amenity) => (
                                                <Badge key={amenity} variant="neutral">
                                                    {amenity}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell>{type.rooms_count}</TableCell>
                                    <TableCell className="text-right">
                                        <div className="inline-flex gap-1">
                                            <Button size="xs" variant="outline" leftIcon={<Pencil className="h-3 w-3" />} onClick={() => openEdit(type)}>
                                                Edit
                                            </Button>
                                            <Button size="xs" variant="danger" leftIcon={<Trash2 className="h-3 w-3" />} onClick={() => remove(type)}>
                                                Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </Card>

            <Modal open={open} title={editing ? `Edit ${editing.name}` : 'New room type'} onClose={close}>
                <form onSubmit={submit} className="space-y-4" noValidate>
                    <Input label="Name" required value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                    <Textarea label="Description" value={form.data.description} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => form.setData('description', e.target.value)} error={form.errors.description} />
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="Base occupancy" type="number" required value={form.data.base_occupancy} onChange={(e) => form.setData('base_occupancy', e.target.value)} error={form.errors.base_occupancy} />
                        <Input label="Max occupancy" type="number" required value={form.data.max_occupancy} onChange={(e) => form.setData('max_occupancy', e.target.value)} error={form.errors.max_occupancy} />
                        <Input label="Base price / night" required inputMode="decimal" value={form.data.base_price} onChange={(e) => form.setData('base_price', e.target.value)} error={form.errors.base_price} />
                        <Input label="Extra person fee" required inputMode="decimal" value={form.data.extra_person_fee} onChange={(e) => form.setData('extra_person_fee', e.target.value)} error={form.errors.extra_person_fee} />
                    </div>
                    <Input
                        label="Amenities"
                        placeholder="Wi-Fi, Mini Bar, Sea View"
                        hint="Separate tags with commas."
                        value={form.data.amenities}
                        onChange={(e) => form.setData('amenities', e.target.value)}
                        error={form.errors.amenities}
                    />
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={close}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={form.processing}>
                            {editing ? 'Save changes' : 'Create room type'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

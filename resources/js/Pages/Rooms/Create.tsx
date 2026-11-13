import { Link, useForm } from '@inertiajs/react';
import { Layers, Save } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button, Card, CardContent, CardFooter, CardHeader, CardTitle, EmptyState, Input, PageHeader, Select, Textarea } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { SelectOption } from '@/types';

interface Props {
    roomTypes: SelectOption[];
}

type Mode = 'single' | 'batch';

export default function RoomsCreate({ roomTypes }: Props) {
    const [mode, setMode] = useState<Mode>('single');
    const defaultType = roomTypes[0] ? String(roomTypes[0].value) : '';

    const single = useForm({ room_type_id: defaultType, room_number: '', floor_number: '1', notes: '' });
    const batch = useForm({ room_type_id: defaultType, floor_number: '1', prefix: '', start_number: '101', end_number: '120' });

    const submitSingle = (event: FormEvent) => {
        event.preventDefault();
        single.post('/rooms');
    };

    const submitBatch = (event: FormEvent) => {
        event.preventDefault();
        batch.post('/rooms/bulk');
    };

    const start = Number.parseInt(batch.data.start_number, 10);
    const end = Number.parseInt(batch.data.end_number, 10);
    const count = Number.isFinite(start) && Number.isFinite(end) && end >= start ? end - start + 1 : 0;

    return (
        <AuthenticatedLayout title="Add rooms">
            <PageHeader
                title="Add rooms"
                subtitle="Create a single room or generate a whole floor at once"
                breadcrumbs={[
                    { label: 'Dashboard', href: '/dashboard' },
                    { label: 'Rooms', href: '/rooms' },
                    { label: 'Add' },
                ]}
            />

            {roomTypes.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<Layers className="h-6 w-6" />}
                        title="Create a room type first"
                        description="Every room belongs to a room type (Standard, Deluxe, Suite...)."
                        action={
                            <Link href="/room-types">
                                <Button>Go to room types</Button>
                            </Link>
                        }
                    />
                </Card>
            ) : (
                <>
                    <div className="mb-4 inline-flex rounded-lg border border-slate-200 p-1 dark:border-enquDark-700" role="tablist">
                        <Button size="sm" variant={mode === 'single' ? 'primary' : 'ghost'} role="tab" aria-selected={mode === 'single'} onClick={() => setMode('single')}>
                            Single room
                        </Button>
                        <Button size="sm" variant={mode === 'batch' ? 'primary' : 'ghost'} role="tab" aria-selected={mode === 'batch'} onClick={() => setMode('batch')}>
                            Batch generator
                        </Button>
                    </div>

                    {mode === 'single' ? (
                        <form onSubmit={submitSingle} noValidate>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Single room</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <Input label="Room number" required placeholder="e.g. 101" value={single.data.room_number} onChange={(e) => single.setData('room_number', e.target.value)} error={single.errors.room_number} />
                                    <Input label="Floor" type="number" required value={single.data.floor_number} onChange={(e) => single.setData('floor_number', e.target.value)} error={single.errors.floor_number} />
                                    <Select label="Room type" required options={roomTypes} value={single.data.room_type_id} onChange={(e) => single.setData('room_type_id', e.target.value)} error={single.errors.room_type_id} />
                                    <div className="sm:col-span-2">
                                        <Textarea label="Notes" value={single.data.notes} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => single.setData('notes', e.target.value)} error={single.errors.notes} />
                                    </div>
                                </CardContent>
                                <CardFooter>
                                    <Link href="/rooms">
                                        <Button variant="ghost">Cancel</Button>
                                    </Link>
                                    <Button type="submit" isLoading={single.processing} leftIcon={<Save className="h-4 w-4" />}>
                                        Create room
                                    </Button>
                                </CardFooter>
                            </Card>
                        </form>
                    ) : (
                        <form onSubmit={submitBatch} noValidate>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Batch generator</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <Select label="Room type" required options={roomTypes} value={batch.data.room_type_id} onChange={(e) => batch.setData('room_type_id', e.target.value)} error={batch.errors.room_type_id} />
                                    <Input label="Floor" type="number" required value={batch.data.floor_number} onChange={(e) => batch.setData('floor_number', e.target.value)} error={batch.errors.floor_number} />
                                    <Input label="Prefix (optional)" placeholder="e.g. A-" value={batch.data.prefix} onChange={(e) => batch.setData('prefix', e.target.value)} error={batch.errors.prefix} />
                                    <span className="hidden sm:block" />
                                    <Input label="First number" type="number" required value={batch.data.start_number} onChange={(e) => batch.setData('start_number', e.target.value)} error={batch.errors.start_number} />
                                    <Input label="Last number" type="number" required value={batch.data.end_number} onChange={(e) => batch.setData('end_number', e.target.value)} error={batch.errors.end_number} />
                                    <p className="text-sm text-slate-600 dark:text-slate-300 sm:col-span-2" aria-live="polite">
                                        {count > 0
                                            ? `Will create ${count} room${count === 1 ? '' : 's'}: ${batch.data.prefix}${start} to ${batch.data.prefix}${end} on floor ${batch.data.floor_number}.`
                                            : 'Enter a valid number range.'}
                                    </p>
                                </CardContent>
                                <CardFooter>
                                    <Link href="/rooms">
                                        <Button variant="ghost">Cancel</Button>
                                    </Link>
                                    <Button type="submit" disabled={count === 0} isLoading={batch.processing} leftIcon={<Save className="h-4 w-4" />}>
                                        Generate rooms
                                    </Button>
                                </CardFooter>
                            </Card>
                        </form>
                    )}
                </>
            )}
        </AuthenticatedLayout>
    );
}

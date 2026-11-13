import { router, useForm, usePage } from '@inertiajs/react';
import { CalendarDays, ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { RateCalendar } from '@/Components/Rates/RateCalendar';
import {
    Badge,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    EmptyState,
    Input,
    Modal,
    PageHeader,
    Select,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { SharedProps } from '@/types';
import type { CalendarDay, RateRoomType, RateRow } from '@/types/models';

interface Props {
    roomTypes: RateRoomType[];
    selectedTypeId: number | null;
    month: string;
    calendar: CalendarDay[];
    rates: RateRow[];
}

interface RateForm {
    room_type_id: string;
    rate_name: string;
    start_date: string;
    end_date: string;
    price_per_night: string;
    days_of_week: number[];
    is_active: boolean;
}

const weekdayLabels = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

function shiftMonth(month: string, delta: number): string {
    const [year, index] = month.split('-').map(Number);
    const date = new Date(Date.UTC(year, index - 1 + delta, 1));

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
}

function monthTitle(month: string): string {
    const [year, index] = month.split('-').map(Number);

    return new Date(Date.UTC(year, index - 1, 1)).toLocaleDateString(undefined, { month: 'long', year: 'numeric', timeZone: 'UTC' });
}

export default function RatesIndex({ roomTypes, selectedTypeId, month, calendar, rates }: Props) {
    const { hotel } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<RateRow | null>(null);

    const blank = (): RateForm => ({
        room_type_id: selectedTypeId ? String(selectedTypeId) : '',
        rate_name: '',
        start_date: `${month}-01`,
        end_date: `${month}-28`,
        price_per_night: '',
        days_of_week: [],
        is_active: true,
    });

    const form = useForm<RateForm>(blank());
    const selectedType = roomTypes.find((type) => type.value === selectedTypeId);

    const go = (next: { room_type_id?: number | null; month?: string }) => {
        router.get(
            '/rates',
            { room_type_id: next.room_type_id ?? selectedTypeId ?? '', month: next.month ?? month },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const openCreate = () => {
        setEditing(null);
        form.setData(blank());
        form.clearErrors();
        setOpen(true);
    };

    const openEdit = (rate: RateRow) => {
        setEditing(rate);
        form.setData({
            room_type_id: String(rate.room_type_id),
            rate_name: rate.rate_name,
            start_date: rate.start_date,
            end_date: rate.end_date,
            price_per_night: rate.price_per_night,
            days_of_week: rate.days_of_week,
            is_active: rate.is_active,
        });
        form.clearErrors();
        setOpen(true);
    };

    const close = () => {
        setOpen(false);
        form.clearErrors();
    };

    const toggleDay = (day: number) => {
        const current = form.data.days_of_week;
        form.setData('days_of_week', current.includes(day) ? current.filter((d) => d !== day) : [...current, day]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // No weekday selected means "every day".
        form.transform((data) => ({ ...data, days_of_week: data.days_of_week.length > 0 ? data.days_of_week : null }));

        const options = { preserveScroll: true, onSuccess: () => close() };

        if (editing) {
            form.put(`/rates/${editing.id}`, options);
        } else {
            form.post('/rates', options);
        }
    };

    const remove = (rate: RateRow) => {
        if (window.confirm(`Delete the rate "${rate.rate_name}"?`)) {
            router.delete(`/rates/${rate.id}`, { preserveScroll: true });
        }
    };

    const describeDays = (days: number[]) =>
        days.length === 0 ? 'Every day' : weekdayLabels.filter((d) => days.includes(d.value)).map((d) => d.label).join(', ');

    return (
        <AuthenticatedLayout title="Rates">
            <PageHeader
                title="Rates"
                subtitle="Seasonal, weekend and holiday price overrides. The narrowest matching rate wins; otherwise the base price applies."
                breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Rates' }]}
                actions={
                    selectedTypeId ? (
                        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={openCreate}>
                            New rate
                        </Button>
                    ) : undefined
                }
            />

            {roomTypes.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<CalendarDays className="h-6 w-6" />}
                        title="No room types yet"
                        description="Create a room type first; rates are set per room type."
                    />
                </Card>
            ) : (
                <div className="grid gap-6 xl:grid-cols-5">
                    <Card className="xl:col-span-3">
                        <CardHeader className="flex-wrap">
                            <div className="w-56">
                                <Select
                                    aria-label="Room type"
                                    options={roomTypes.map((type) => ({ value: type.value, label: type.label }))}
                                    value={selectedTypeId ?? ''}
                                    onChange={(event) => go({ room_type_id: Number(event.target.value) })}
                                />
                            </div>
                            <div className="flex items-center gap-1">
                                <Button size="sm" variant="ghost" aria-label="Previous month" onClick={() => go({ month: shiftMonth(month, -1) })}>
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <span className="min-w-36 text-center text-sm font-medium text-slate-800 dark:text-slate-100">{monthTitle(month)}</span>
                                <Button size="sm" variant="ghost" aria-label="Next month" onClick={() => go({ month: shiftMonth(month, 1) })}>
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {selectedType && (
                                <p className="mb-3 text-sm text-slate-500 dark:text-slate-400">
                                    Base price: {hotel?.currency} {Number(selectedType.base_price).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                                </p>
                            )}
                            <RateCalendar month={month} days={calendar} />
                        </CardContent>
                    </Card>

                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle>Overrides</CardTitle>
                        </CardHeader>
                        {rates.length === 0 ? (
                            <EmptyState title="No overrides" description="This room type always uses its base price." />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Rate</TableHead>
                                        <TableHead>Price</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rates.map((rate) => (
                                        <TableRow key={rate.id}>
                                            <TableCell>
                                                <p className="font-medium text-slate-900 dark:text-slate-100">
                                                    {rate.rate_name} {!rate.is_active && <Badge variant="neutral">Off</Badge>}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {rate.start_date} to {rate.end_date} · {describeDays(rate.days_of_week)}
                                                </p>
                                            </TableCell>
                                            <TableCell>{Number(rate.price_per_night).toLocaleString(undefined, { minimumFractionDigits: 2 })}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="inline-flex gap-1">
                                                    <Button size="xs" variant="outline" aria-label={`Edit ${rate.rate_name}`} onClick={() => openEdit(rate)}>
                                                        <Pencil className="h-3 w-3" />
                                                    </Button>
                                                    <Button size="xs" variant="danger" aria-label={`Delete ${rate.rate_name}`} onClick={() => remove(rate)}>
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </Card>
                </div>
            )}

            <Modal open={open} title={editing ? 'Edit rate' : 'New rate'} onClose={close}>
                <form onSubmit={submit} className="space-y-4" noValidate>
                    <Select
                        label="Room type"
                        required
                        options={roomTypes.map((type) => ({ value: type.value, label: type.label }))}
                        value={form.data.room_type_id}
                        onChange={(e) => form.setData('room_type_id', e.target.value)}
                        error={form.errors.room_type_id}
                    />
                    <Input label="Rate name" required placeholder="Weekend Surcharge, Peak Season..." value={form.data.rate_name} onChange={(e) => form.setData('rate_name', e.target.value)} error={form.errors.rate_name} />
                    <div className="grid grid-cols-2 gap-4">
                        <Input label="From" type="date" required value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} error={form.errors.start_date} />
                        <Input label="To (inclusive)" type="date" required value={form.data.end_date} onChange={(e) => form.setData('end_date', e.target.value)} error={form.errors.end_date} />
                    </div>
                    <Input label="Price per night" required inputMode="decimal" value={form.data.price_per_night} onChange={(e) => form.setData('price_per_night', e.target.value)} error={form.errors.price_per_night} />

                    <div>
                        <p className="mb-1 text-sm font-medium text-slate-700 dark:text-slate-200">Applies on</p>
                        <div className="flex flex-wrap gap-1" role="group" aria-label="Weekdays">
                            {weekdayLabels.map((day) => (
                                <Button
                                    key={day.value}
                                    size="xs"
                                    variant={form.data.days_of_week.includes(day.value) ? 'primary' : 'outline'}
                                    aria-pressed={form.data.days_of_week.includes(day.value)}
                                    onClick={() => toggleDay(day.value)}
                                >
                                    {day.label}
                                </Button>
                            ))}
                        </div>
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave all unselected to apply every day (e.g. choose Fri + Sat for a weekend rate).</p>
                        {form.errors.days_of_week && <p className="mt-1 text-xs text-red-600">{form.errors.days_of_week}</p>}
                    </div>

                    <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="h-4 w-4 rounded border-slate-300 text-primary-800 focus:ring-primary-500"
                        />
                        Active
                    </label>

                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={close}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={form.processing}>
                            {editing ? 'Save changes' : 'Create rate'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

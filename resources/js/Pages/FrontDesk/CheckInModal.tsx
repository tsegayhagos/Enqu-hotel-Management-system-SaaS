import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { AlertCircle, BedDouble, Check, CreditCard, Key, ShieldAlert, User } from 'lucide-react';
import { Button } from '@/Components/ui/Button';
import { Modal } from '@/Components/ui/Modal';
import { Badge } from '@/Components/ui/Badge';
import { GuestAutocomplete } from '@/Components/GuestAutocomplete';
import type { GuestRow, ReservationRow } from '@/types/models';

interface CleanRoomOption {
    id: number;
    room_number: string;
    floor_number: number;
    room_type_id: number;
    room_type?: { id: number; name: string };
}

interface RoomTypeOption {
    id: number;
    name: string;
    rates?: { base_rate: string }[];
}

interface CheckInModalProps {
    open: boolean;
    onClose: () => void;
    reservation?: ReservationRow | null;
    cleanRooms: CleanRoomOption[];
    roomTypes: RoomTypeOption[];
}

export function CheckInModal({
    open,
    onClose,
    reservation,
    cleanRooms,
    roomTypes,
}: CheckInModalProps) {
    const [step, setStep] = useState<1 | 2 | 3>(1);
    const [selectedGuest, setSelectedGuest] = useState<GuestRow | null>(
        reservation?.guest || null
    );

    const isExistingReservation = Boolean(reservation);

    const { data, setData, post, processing, errors, reset } = useForm({
        // Existing reservation fields
        reservation_id: reservation?.id || '',
        // Walk-in fields
        guest_id: reservation?.guest_id || '',
        first_name: reservation?.guest?.first_name || '',
        last_name: reservation?.guest?.last_name || '',
        email: reservation?.guest?.email || '',
        phone: reservation?.guest?.phone || '',
        id_type: reservation?.guest?.id_type || 'passport',
        id_number: reservation?.guest?.id_number || '',
        nationality: reservation?.guest?.nationality || 'Ethiopian',
        car_plate_number: reservation?.guest?.car_plate_number || '',
        vip_level: reservation?.guest?.vip_level || 'standard',
        special_preferences: reservation?.guest?.special_preferences || '',

        // Stay details
        room_type_id: reservation?.room_type_id || (roomTypes[0]?.id ? String(roomTypes[0].id) : ''),
        room_id: reservation?.room_id || '',
        check_in_date: reservation?.check_in_date || new Date().toISOString().split('T')[0],
        check_out_date: reservation?.check_out_date || new Date(Date.now() + 86400000).toISOString().split('T')[0],
        adults_count: reservation?.adults_count || 1,
        children_count: reservation?.children_count || 0,
        daily_rate: reservation?.daily_rate || '',
        total_amount: reservation?.total_amount || '',
        keycard_number: reservation?.keycard_number || '',
        deposit_amount: '',
        payment_method: 'cash',
        special_requests: reservation?.special_requests || '',
        manager_override: false,
    });

    // When guest is chosen via autocomplete
    const handleGuestSelected = (guest: GuestRow | null) => {
        setSelectedGuest(guest);
        if (guest) {
            setData((prev) => ({
                ...prev,
                guest_id: guest.id,
                first_name: guest.first_name,
                last_name: guest.last_name,
                email: guest.email || '',
                phone: guest.phone,
                id_type: guest.id_type,
                id_number: guest.id_number,
                nationality: guest.nationality || 'Ethiopian',
                car_plate_number: guest.car_plate_number || '',
                vip_level: guest.vip_level,
                special_preferences: guest.special_preferences || '',
            }));
        } else {
            setData((prev) => ({
                ...prev,
                guest_id: '',
            }));
        }
    };

    // Filter clean rooms by chosen room type
    const availableRooms = cleanRooms.filter((room) => {
        if (!data.room_type_id) return true;
        return String(room.room_type_id) === String(data.room_type_id);
    });

    const isGuestBlacklisted = selectedGuest?.is_blacklisted || false;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (isExistingReservation) {
            post('/check-in', {
                onSuccess: () => {
                    reset();
                    onClose();
                },
            });
        } else {
            post('/check-in/walk-in', {
                onSuccess: () => {
                    reset();
                    onClose();
                },
            });
        }
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isExistingReservation ? `Check-In: ${reservation?.reservation_code}` : 'Fast Walk-In Registration & Check-In'}
        >
            <div className="mb-6 flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
                <div className="flex items-center gap-2">
                    <span
                        className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${
                            step === 1
                                ? 'bg-amber-600 text-white'
                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        1
                    </span>
                    <span className="text-sm font-medium text-slate-700 dark:text-slate-300">Guest Profile</span>
                </div>
                <div className="h-0.5 w-8 bg-slate-200 dark:bg-slate-700" />
                <div className="flex items-center gap-2">
                    <span
                        className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${
                            step === 2
                                ? 'bg-amber-600 text-white'
                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        2
                    </span>
                    <span className="text-sm font-medium text-slate-700 dark:text-slate-300">Room Allocation</span>
                </div>
                <div className="h-0.5 w-8 bg-slate-200 dark:bg-slate-700" />
                <div className="flex items-center gap-2">
                    <span
                        className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold ${
                            step === 3
                                ? 'bg-amber-600 text-white'
                                : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        }`}
                    >
                        3
                    </span>
                    <span className="text-sm font-medium text-slate-700 dark:text-slate-300">Deposit & Keycard</span>
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* STEP 1: GUEST INFO */}
                {step === 1 && (
                    <div className="space-y-4">
                        {!isExistingReservation && (
                            <div>
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                                    Search Returning Guest
                                </label>
                                <GuestAutocomplete
                                    selectedGuest={selectedGuest}
                                    onSelectGuest={handleGuestSelected}
                                />
                            </div>
                        )}

                        {isGuestBlacklisted && (
                            <div className="rounded-md border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/40">
                                <div className="flex items-start gap-3">
                                    <ShieldAlert className="h-5 w-5 text-red-600 dark:text-red-400" />
                                    <div>
                                        <h4 className="text-sm font-semibold text-red-800 dark:text-red-200">
                                            BLACKLISTED GUEST DETECTED
                                        </h4>
                                        <p className="mt-1 text-xs text-red-700 dark:text-red-300">
                                            Reason: {selectedGuest?.blacklist_reason || 'Flagged by hotel security/management.'}
                                        </p>
                                        <label className="mt-2 flex items-center gap-2 text-xs font-medium text-red-900 dark:text-red-200">
                                            <input
                                                type="checkbox"
                                                checked={data.manager_override}
                                                onChange={(e) => setData('manager_override', e.target.checked)}
                                                className="rounded border-red-300 text-red-600 focus:ring-red-500"
                                            />
                                            Authorize Manager Override to proceed with check-in
                                        </label>
                                    </div>
                                </div>
                            </div>
                        )}

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    First Name *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.first_name}
                                    onChange={(e) => setData('first_name', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                />
                                {errors.first_name && <p className="mt-1 text-xs text-red-600">{errors.first_name}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Last Name *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.last_name}
                                    onChange={(e) => setData('last_name', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                />
                                {errors.last_name && <p className="mt-1 text-xs text-red-600">{errors.last_name}</p>}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Phone Number *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                />
                                {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Email Address
                                </label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    ID Type *
                                </label>
                                <select
                                    value={data.id_type}
                                    onChange={(e) => setData('id_type', e.target.value as any)}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <option value="passport">Passport</option>
                                    <option value="national_id">National ID</option>
                                    <option value="drivers_license">Driver's License</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    ID Number *
                                </label>
                                <input
                                    type="text"
                                    required
                                    value={data.id_number}
                                    onChange={(e) => setData('id_number', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                />
                                {errors.id_number && <p className="mt-1 text-xs text-red-600">{errors.id_number}</p>}
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Nationality
                                </label>
                                <input
                                    type="text"
                                    value={data.nationality}
                                    onChange={(e) => setData('nationality', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                />
                            </div>
                        </div>

                        <div className="flex justify-end pt-2">
                            <Button
                                type="button"
                                onClick={() => setStep(2)}
                                disabled={!data.first_name || !data.last_name || !data.phone || (isGuestBlacklisted && !data.manager_override)}
                            >
                                Continue to Room Allocation →
                            </Button>
                        </div>
                    </div>
                )}

                {/* STEP 2: ROOM ALLOCATION */}
                {step === 2 && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Room Type *
                                </label>
                                <select
                                    value={data.room_type_id}
                                    onChange={(e) => {
                                        const typeId = e.target.value;
                                        const foundType = roomTypes.find((t) => String(t.id) === typeId);
                                        const rate = foundType?.rates?.[0]?.base_rate || '';
                                        setData((prev) => ({
                                            ...prev,
                                            room_type_id: typeId,
                                            room_id: '',
                                            daily_rate: prev.daily_rate || rate,
                                        }));
                                    }}
                                    disabled={isExistingReservation}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                >
                                    {roomTypes.map((t) => (
                                        <option key={t.id} value={t.id}>
                                            {t.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Select Clean Room *
                                </label>
                                <select
                                    value={data.room_id}
                                    onChange={(e) => setData('room_id', e.target.value)}
                                    required
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                >
                                    <option value="">-- Choose a Vacant Clean Room --</option>
                                    {availableRooms.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            Room {r.room_number} (Floor {r.floor_number}) - {r.room_type?.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.room_id && <p className="mt-1 text-xs text-red-600">{errors.room_id}</p>}
                                {availableRooms.length === 0 && (
                                    <p className="mt-1 text-xs text-amber-600">
                                        No clean rooms currently available for this type. Rooms must be Vacant Clean.
                                    </p>
                                )}
                            </div>
                        </div>

                        {!isExistingReservation && (
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Check-In Date
                                    </label>
                                    <input
                                        type="date"
                                        value={data.check_in_date}
                                        onChange={(e) => setData('check_in_date', e.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Expected Check-Out Date *
                                    </label>
                                    <input
                                        type="date"
                                        required
                                        value={data.check_out_date}
                                        onChange={(e) => setData('check_out_date', e.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                            </div>
                        )}

                        <div className="flex justify-between pt-2">
                            <Button type="button" variant="outline" onClick={() => setStep(1)}>
                                ← Back to Guest Info
                            </Button>
                            <Button type="button" onClick={() => setStep(3)} disabled={!data.room_id}>
                                Continue to Deposit & Keycard →
                            </Button>
                        </div>
                    </div>
                )}

                {/* STEP 3: DEPOSIT & KEYCARD */}
                {step === 3 && (
                    <div className="space-y-4">
                        <div className="rounded-lg bg-slate-50 p-4 dark:bg-slate-800/60">
                            <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Stay Summary
                            </h4>
                            <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span className="text-slate-500">Guest:</span>{' '}
                                    <span className="font-semibold text-slate-800 dark:text-slate-200">
                                        {data.first_name} {data.last_name}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-slate-500">Room:</span>{' '}
                                    <span className="font-semibold text-slate-800 dark:text-slate-200">
                                        Room {cleanRooms.find((r) => String(r.id) === String(data.room_id))?.room_number || data.room_id}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-slate-500">Check-In:</span> {data.check_in_date}
                                </div>
                                <div>
                                    <span className="text-slate-500">Check-Out:</span> {data.check_out_date}
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    Keycard Number / RFID Tag
                                </label>
                                <input
                                    type="text"
                                    placeholder="e.g. CARD-8492"
                                    value={data.keycard_number}
                                    onChange={(e) => setData('keycard_number', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                />
                            </div>

                            {!isExistingReservation && (
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Daily Rate *
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        required
                                        value={data.daily_rate}
                                        onChange={(e) => setData('daily_rate', e.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                            )}
                        </div>

                        <div className="border-t border-slate-200 pt-3 dark:border-slate-800">
                            <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Advance Deposit (Optional)
                            </h4>
                            <div className="mt-2 grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Deposit Amount
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        value={data.deposit_amount}
                                        onChange={(e) => setData('deposit_amount', e.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                        Payment Method
                                    </label>
                                    <select
                                        value={data.payment_method}
                                        onChange={(e) => setData('payment_method', e.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="card">Credit/Debit Card</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="mobile_money">Mobile Money (Telebirr/CBE)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {errors.guest_id && <p className="text-xs text-red-600">{errors.guest_id}</p>}
                        {(errors as any).status && <p className="text-xs text-red-600">{(errors as any).status}</p>}

                        <div className="flex justify-between pt-3">
                            <Button type="button" variant="outline" onClick={() => setStep(2)}>
                                ← Back to Room
                            </Button>
                            <Button type="submit" isLoading={processing}>
                                Complete Check-In & Issue Keycard ✓
                            </Button>
                        </div>
                    </div>
                )}
            </form>
        </Modal>
    );
}

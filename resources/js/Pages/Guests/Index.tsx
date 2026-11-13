import React, { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Edit2,
    Filter,
    Plus,
    Search,
    ShieldAlert,
    User,
    UserCheck,
    Users,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Modal } from '@/Components/ui/Modal';
import type { GuestRow } from '@/types/models';

interface GuestsIndexProps {
    guests: {
        data: GuestRow[];
        links: any[];
        total: number;
    };
    filters: {
        search: string;
        vip: string;
        blacklisted: string;
    };
}

export default function Index({ guests, filters }: GuestsIndexProps) {
    const [search, setSearch] = useState(filters.search || '');
    const [vipFilter, setVipFilter] = useState(filters.vip || 'all');
    const [blacklistedFilter, setBlacklistedFilter] = useState(filters.blacklisted || 'all');

    const [modalOpen, setModalOpen] = useState(false);
    const [editingGuest, setEditingGuest] = useState<GuestRow | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        id_type: 'passport' as 'passport' | 'national_id' | 'drivers_license' | 'other',
        id_number: '',
        nationality: 'Ethiopian',
        car_plate_number: '',
        vip_level: 'standard' as 'standard' | 'silver' | 'gold' | 'vip',
        is_blacklisted: false,
        blacklist_reason: '',
        special_preferences: '',
    });

    const openCreateModal = () => {
        setEditingGuest(null);
        reset();
        setModalOpen(true);
    };

    const openEditModal = (guest: GuestRow) => {
        setEditingGuest(guest);
        setData({
            first_name: guest.first_name,
            last_name: guest.last_name,
            email: guest.email || '',
            phone: guest.phone,
            id_type: guest.id_type,
            id_number: guest.id_number,
            nationality: guest.nationality || 'Ethiopian',
            car_plate_number: guest.car_plate_number || '',
            vip_level: guest.vip_level,
            is_blacklisted: guest.is_blacklisted,
            blacklist_reason: guest.blacklist_reason || '',
            special_preferences: guest.special_preferences || '',
        });
        setModalOpen(true);
    };

    const handleFilterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/guests',
            { search, vip: vipFilter, blacklisted: blacklistedFilter },
            { preserveState: true }
        );
    };

    const handleFormSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingGuest) {
            put(`/guests/${editingGuest.id}`, {
                onSuccess: () => {
                    setModalOpen(false);
                    reset();
                },
            });
        } else {
            post('/guests', {
                onSuccess: () => {
                    setModalOpen(false);
                    reset();
                },
            });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Guest CRM" />

            <div className="space-y-6">
                <PageHeader
                    title="Guest CRM Directory"
                    subtitle="Guest profiles, identification records, VIP loyalty tracking, and security blacklist."
                    actions={
                        <Button onClick={openCreateModal}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Guest Profile
                        </Button>
                    }
                />

                {/* Filters */}
                <form
                    onSubmit={handleFilterSubmit}
                    className="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div className="relative min-w-[240px] flex-1">
                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search by guest name, phone, email, or ID number..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-md border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-900"
                        />
                    </div>

                    <div className="flex items-center gap-2">
                        <select
                            value={vipFilter}
                            onChange={(e) => setVipFilter(e.target.value)}
                            className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <option value="all">All VIP Tiers</option>
                            <option value="standard">Standard</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                            <option value="vip">VIP</option>
                        </select>

                        <select
                            value={blacklistedFilter}
                            onChange={(e) => setBlacklistedFilter(e.target.value)}
                            className="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                        >
                            <option value="all">All Guests</option>
                            <option value="false">Active Only</option>
                            <option value="true">Blacklisted Only</option>
                        </select>

                        <Button type="submit" variant="secondary">
                            <Filter className="mr-1.5 h-4 w-4" /> Filter
                        </Button>
                    </div>
                </form>

                {/* Guests Table */}
                <div className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    {guests.data.length === 0 ? (
                        <div className="p-8">
                            <EmptyState
                                icon={<Users className="h-10 w-10 text-slate-400" />}
                                title="No Guests Found"
                                description="No guest profile matches the current search or filters."
                                action={
                                    <Button onClick={openCreateModal}>
                                        <Plus className="mr-2 h-4 w-4" /> Add First Guest
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead className="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                    <tr>
                                        <th className="px-4 py-3">Guest Profile</th>
                                        <th className="px-4 py-3">Contact</th>
                                        <th className="px-4 py-3">Identification</th>
                                        <th className="px-4 py-3">VIP Tier</th>
                                        <th className="px-4 py-3">Stays</th>
                                        <th className="px-4 py-3">Security Status</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {guests.data.map((guest) => (
                                        <tr key={guest.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                            <td className="px-4 py-3">
                                                <div className="font-semibold text-slate-900 dark:text-slate-100">
                                                    {guest.first_name} {guest.last_name}
                                                </div>
                                                <div className="text-xs text-slate-400">
                                                    Nationality: {guest.nationality}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div>{guest.phone}</div>
                                                <div className="text-xs text-slate-400">{guest.email || 'No email'}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-mono text-xs font-medium uppercase">
                                                    {guest.id_type.replace('_', ' ')}: {guest.id_number}
                                                </div>
                                                {guest.car_plate_number && (
                                                    <div className="text-xs text-slate-400">
                                                        Car Plate: {guest.car_plate_number}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge
                                                    variant={
                                                        guest.vip_level === 'vip'
                                                            ? 'danger'
                                                            : guest.vip_level === 'gold'
                                                            ? 'warning'
                                                            : guest.vip_level === 'silver'
                                                            ? 'info'
                                                            : 'neutral'
                                                    }
                                                    className="uppercase text-xs"
                                                >
                                                    {guest.vip_level}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 font-semibold text-slate-800 dark:text-slate-200">
                                                {guest.total_stays_count}
                                            </td>
                                            <td className="px-4 py-3">
                                                {guest.is_blacklisted ? (
                                                    <div className="flex items-center gap-1.5 text-xs text-red-600">
                                                        <ShieldAlert className="h-4 w-4" />
                                                        <span className="font-medium">Blacklisted</span>
                                                    </div>
                                                ) : (
                                                    <div className="flex items-center gap-1.5 text-xs text-emerald-600">
                                                        <CheckCircle2 className="h-4 w-4" />
                                                        <span>Clear</span>
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button size="sm" variant="ghost" onClick={() => openEditModal(guest)}>
                                                    <Edit2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Guest Modal */}
            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editingGuest ? `Edit Profile: ${editingGuest.first_name} ${editingGuest.last_name}` : 'New Guest Profile'}
            >
                <form onSubmit={handleFormSubmit} className="space-y-4">
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

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                VIP Loyalty Tier
                            </label>
                            <select
                                value={data.vip_level}
                                onChange={(e) => setData('vip_level', e.target.value as any)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="standard">Standard</option>
                                <option value="silver">Silver</option>
                                <option value="gold">Gold</option>
                                <option value="vip">VIP</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Car Plate Number
                            </label>
                            <input
                                type="text"
                                value={data.car_plate_number}
                                onChange={(e) => setData('car_plate_number', e.target.value)}
                                placeholder="e.g. AA 3-94812"
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Special Preferences & Notes
                        </label>
                        <textarea
                            rows={2}
                            value={data.special_preferences}
                            onChange={(e) => setData('special_preferences', e.target.value)}
                            placeholder="e.g. Extra pillow, non-smoking, high floor..."
                            className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                        />
                    </div>

                    {/* Blacklist Control */}
                    <div className="rounded-lg border border-red-200 bg-red-50/50 p-3 dark:border-red-900/50 dark:bg-red-950/20">
                        <label className="flex items-center gap-2 text-xs font-semibold text-red-800 dark:text-red-300">
                            <input
                                type="checkbox"
                                checked={data.is_blacklisted}
                                onChange={(e) => setData('is_blacklisted', e.target.checked)}
                                className="rounded border-red-300 text-red-600 focus:ring-red-500"
                            />
                            Mark as Blacklisted (Restricts Check-In)
                        </label>
                        {data.is_blacklisted && (
                            <div className="mt-2">
                                <label className="block text-xs font-medium text-red-800 dark:text-red-300">
                                    Blacklist Reason *
                                </label>
                                <textarea
                                    rows={2}
                                    required
                                    value={data.blacklist_reason}
                                    onChange={(e) => setData('blacklist_reason', e.target.value)}
                                    placeholder="Explain why this guest is blacklisted..."
                                    className="mt-1 w-full rounded border border-red-300 bg-white px-2.5 py-1 text-sm dark:border-red-900 dark:bg-slate-900"
                                />
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={processing}>
                            {editingGuest ? 'Save Changes' : 'Create Guest Profile'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

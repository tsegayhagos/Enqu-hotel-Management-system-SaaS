import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Edit2, Plus, Tag, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Badge } from '@/Components/ui/Badge';
import { Modal } from '@/Components/ui/Modal';
import { EmptyState } from '@/Components/ui/EmptyState';
import type { LaundryServiceRow } from '@/types/models';

interface CatalogIndexProps {
    services: LaundryServiceRow[];
    currency: string;
}

export default function Index({ services, currency }: CatalogIndexProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingService, setEditingService] = useState<LaundryServiceRow | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        item_name: '',
        category: 'apparel' as 'apparel' | 'delicate' | 'linen' | 'uniform',
        service_type: 'wash_and_iron' as 'wash_and_iron' | 'iron_only' | 'dry_clean',
        standard_price: '10.00',
        express_price: '15.00',
        is_active: true,
    });

    const openCreateModal = () => {
        setEditingService(null);
        reset();
        setModalOpen(true);
    };

    const openEditModal = (service: LaundryServiceRow) => {
        setEditingService(service);
        setData({
            item_name: service.item_name,
            category: service.category,
            service_type: service.service_type,
            standard_price: service.standard_price,
            express_price: service.express_price,
            is_active: service.is_active,
        });
        setModalOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingService) {
            put(`/laundry/services/${editingService.id}`, {
                onSuccess: () => {
                    setModalOpen(false);
                    reset();
                },
            });
        } else {
            post('/laundry/services', {
                onSuccess: () => {
                    setModalOpen(false);
                    reset();
                },
            });
        }
    };

    const handleDelete = (service: LaundryServiceRow) => {
        if (confirm(`Remove or deactivate "${service.item_name}"?`)) {
            router.delete(`/laundry/services/${service.id}`);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Laundry Service Catalog" />

            <div className="space-y-6">
                <PageHeader
                    title="Laundry Service Catalog"
                    subtitle="Configure garment items, dry cleaning rates, and 2-hour express surcharges."
                    actions={
                        <div className="flex items-center gap-2">
                            <Link href="/laundry">
                                <Button variant="outline">
                                    <ArrowLeft className="mr-2 h-4 w-4" /> Back to Pipeline
                                </Button>
                            </Link>
                            <Button onClick={openCreateModal}>
                                <Plus className="mr-2 h-4 w-4" /> Add Service Item
                            </Button>
                        </div>
                    }
                />

                <div className="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    {services.length === 0 ? (
                        <div className="p-8">
                            <EmptyState
                                icon={<Tag className="h-10 w-10 text-slate-400" />}
                                title="No Laundry Items Defined"
                                description="Add your first laundry catalog item with standard and express pricing."
                                action={
                                    <Button onClick={openCreateModal}>
                                        <Plus className="mr-2 h-4 w-4" /> Add Service Item
                                    </Button>
                                }
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                                <thead className="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                    <tr>
                                        <th className="px-4 py-3">Item Name</th>
                                        <th className="px-4 py-3">Category</th>
                                        <th className="px-4 py-3">Service Type</th>
                                        <th className="px-4 py-3">Standard Rate</th>
                                        <th className="px-4 py-3">Express Rate</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                    {services.map((s) => (
                                        <tr key={s.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                                            <td className="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                                {s.item_name}
                                            </td>
                                            <td className="px-4 py-3 capitalize">{s.category}</td>
                                            <td className="px-4 py-3 capitalize">
                                                {s.service_type.replace(/_/g, ' ')}
                                            </td>
                                            <td className="px-4 py-3 font-mono font-medium">
                                                {s.standard_price} {currency}
                                            </td>
                                            <td className="px-4 py-3 font-mono font-semibold text-red-600 dark:text-red-400">
                                                {s.express_price} {currency}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={s.is_active ? 'success' : 'neutral'}>
                                                    {s.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => openEditModal(s)}
                                                    >
                                                        <Edit2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => handleDelete(s)}
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5 text-red-500" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title={editingService ? `Edit ${editingService.item_name}` : 'Add Laundry Catalog Item'}
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            Item Name *
                        </label>
                        <input
                            type="text"
                            required
                            placeholder="e.g. Men's Suit (2-Piece)"
                            value={data.item_name}
                            onChange={(e) => setData('item_name', e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                        />
                        {errors.item_name && <p className="mt-1 text-xs text-red-600">{errors.item_name}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Category *
                            </label>
                            <select
                                value={data.category}
                                onChange={(e) => setData('category', e.target.value as any)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="apparel">Apparel</option>
                                <option value="delicate">Delicate</option>
                                <option value="linen">Linen</option>
                                <option value="uniform">Uniform</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Service Type *
                            </label>
                            <select
                                value={data.service_type}
                                onChange={(e) => setData('service_type', e.target.value as any)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            >
                                <option value="wash_and_iron">Wash & Iron</option>
                                <option value="iron_only">Iron Only</option>
                                <option value="dry_clean">Dry Clean</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Standard Price ({currency}) *
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                required
                                value={data.standard_price}
                                onChange={(e) => {
                                    const std = parseFloat(e.target.value) || 0;
                                    setData((prev) => ({
                                        ...prev,
                                        standard_price: e.target.value,
                                        express_price: (std * 1.5).toFixed(2),
                                    }));
                                }}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            {errors.standard_price && (
                                <p className="mt-1 text-xs text-red-600">{errors.standard_price}</p>
                            )}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                Express Price (+50% default) *
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                required
                                value={data.express_price}
                                onChange={(e) => setData('express_price', e.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            {errors.express_price && (
                                <p className="mt-1 text-xs text-red-600">{errors.express_price}</p>
                            )}
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                            className="rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                        />
                        Active in intake form
                    </label>

                    <div className="flex justify-end gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                        <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" isLoading={processing}>
                            {editingService ? 'Update Item' : 'Create Item'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

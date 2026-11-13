import { Head, Link, router } from '@inertiajs/react';
import { Eye, Receipt } from 'lucide-react';
import { useState } from 'react';
import {
    Badge,
    Card,
    EmptyState,
    Input,
    PageHeader,
    Select,
    StatCard,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import type { FolioRow, PaginatedResponse } from '@/types';

interface Props {
    folios: PaginatedResponse<FolioRow>;
    filters: {
        search?: string;
        status?: string;
    };
    stats: {
        total_open: number;
        total_settled: number;
        total_closed: number;
        outstanding_balance: string;
    };
}

export default function Index({ folios, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');

    const handleFilter = (newStatus?: string, newSearch?: string) => {
        router.get(
            '/folios',
            {
                search: newSearch !== undefined ? newSearch : search,
                status: newStatus !== undefined ? newStatus : status,
            },
            { preserveState: true, replace: true },
        );
    };

    const getStatusVariant = (st: string) => {
        switch (st) {
            case 'open':
                return 'primary';
            case 'settled':
                return 'success';
            case 'closed':
                return 'neutral';
            case 'void':
                return 'danger';
            default:
                return 'neutral';
        }
    };

    return (
        <AuthenticatedLayout title="Guest Folios & Billing">
            <Head title="Guest Folios & Billing" />

            <div className="space-y-6">
                <PageHeader
                    title="Guest Folios & Billing"
                    subtitle="Unified Guest Folio ledger, charge tracking, and settlement management."
                    breadcrumbs={[
                        { label: 'Dashboard', href: '/dashboard' },
                        { label: 'Guest Folios' },
                    ]}
                />

                {/* Key Metrics */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Open Folios"
                        value={stats.total_open}
                        icon={<Receipt className="h-5 w-5" />}
                        subtitle="Active stays"
                    />
                    <StatCard
                        title="Settled Folios"
                        value={stats.total_settled}
                        icon={<Receipt className="h-5 w-5" />}
                        subtitle="Ready to close"
                    />
                    <StatCard
                        title="Closed Folios"
                        value={stats.total_closed}
                        icon={<Receipt className="h-5 w-5" />}
                        subtitle="Archived history"
                    />
                    <StatCard
                        title="Outstanding Balance"
                        value={`$${stats.outstanding_balance}`}
                        icon={<Receipt className="h-5 w-5" />}
                        subtitle="Uncollected charges"
                    />
                </div>

                {/* Filters & Search */}
                <Card className="p-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <Input
                                placeholder="Search by folio number or guest name..."
                                value={search}
                                onChange={(e) => {
                                    setSearch(e.target.value);
                                    handleFilter(status, e.target.value);
                                }}
                            />
                        </div>

                        <Select
                            value={status}
                            onChange={(e) => {
                                setStatus(e.target.value);
                                handleFilter(e.target.value, search);
                            }}
                            options={[
                                { value: '', label: 'All Statuses' },
                                { value: 'open', label: 'Open' },
                                { value: 'settled', label: 'Settled (Zero Balance)' },
                                { value: 'closed', label: 'Closed' },
                            ]}
                        />
                    </div>
                </Card>

                {/* Folios Table */}
                <Card className="overflow-hidden">
                    {folios.data.length === 0 ? (
                        <EmptyState
                            icon={<Receipt className="h-10 w-10 text-slate-400" />}
                            title="No guest folios found"
                            description="Folios are created automatically when guests check in or when room charges are generated."
                        />
                    ) : (
                        <div>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Folio #</TableHead>
                                        <TableHead>Guest Name</TableHead>
                                        <TableHead>Room</TableHead>
                                        <TableHead className="text-right">Total Charges</TableHead>
                                        <TableHead className="text-right">Total Payments</TableHead>
                                        <TableHead className="text-right">Balance</TableHead>
                                        <TableHead className="text-center">Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {folios.data.map((folio) => {
                                        const balNum = Number(folio.balance);
                                        return (
                                            <TableRow key={folio.id}>
                                                <TableCell className="font-semibold text-primary-600 dark:text-primary-400">
                                                    <Link href={`/folios/${folio.id}`} className="hover:underline">
                                                        {folio.folio_number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell className="font-medium text-slate-900 dark:text-white">
                                                    {folio.guest_name ?? 'Guest'}
                                                </TableCell>
                                                <TableCell>
                                                    {folio.room ? (
                                                        <span>
                                                            Room {folio.room.room_number}{' '}
                                                            <span className="text-xs text-slate-400">
                                                                ({folio.room.room_type?.name ?? 'Standard'})
                                                            </span>
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {folio.currency} {folio.total_charges}
                                                </TableCell>
                                                <TableCell className="text-right font-medium text-emerald-600 dark:text-emerald-400">
                                                    {folio.currency} {folio.total_payments}
                                                </TableCell>
                                                <TableCell
                                                    className={`text-right font-bold ${
                                                        balNum > 0
                                                            ? 'text-rose-600 dark:text-rose-400'
                                                            : 'text-slate-900 dark:text-white'
                                                    }`}
                                                >
                                                    {folio.currency} {folio.balance}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge variant={getStatusVariant(folio.status)}>
                                                        {folio.status.toUpperCase()}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Link
                                                        href={`/folios/${folio.id}`}
                                                        className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 shadow-xs hover:bg-slate-50 dark:border-enquDark-700 dark:bg-enquDark-800 dark:text-slate-200"
                                                    >
                                                        <Eye className="h-3.5 w-3.5" />
                                                        View
                                                    </Link>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>

                            {/* Pagination controls */}
                            {folios.links && folios.links.length > 3 && (
                                <div className="flex items-center justify-between border-t border-slate-200 px-4 py-3 dark:border-enquDark-700">
                                    <div className="text-xs text-slate-500">
                                        Showing {folios.from} to {folios.to} of {folios.total} results
                                    </div>
                                    <div className="flex gap-1">
                                        {folios.links.map((link, idx) => (
                                            <Link
                                                key={idx}
                                                href={link.url || '#'}
                                                className={`rounded px-2.5 py-1 text-xs font-medium ${
                                                    link.active
                                                        ? 'bg-primary-800 text-white'
                                                        : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-enquDark-700 dark:bg-enquDark-800 dark:text-slate-200'
                                                } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

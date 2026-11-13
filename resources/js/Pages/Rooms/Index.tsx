import { Link, router } from '@inertiajs/react';
import { BedDouble, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { RoomGrid } from '@/Components/Rooms/RoomGrid';
import { RoomQuickModal } from '@/Components/Rooms/RoomQuickModal';
import { Button, Card, EmptyState, Input, PageHeader, Select, StatCard } from '@/Components/ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { usePermissions } from '@/lib/permissions';
import type { SelectOption } from '@/types';
import type { RoomCounts, RoomFilters, RoomRow, RoomStatusOption } from '@/types/models';

interface Props {
    rooms: RoomRow[];
    filters: RoomFilters;
    floors: number[];
    roomTypes: SelectOption[];
    statuses: RoomStatusOption[];
    counts: RoomCounts;
}

export default function RoomsIndex({ rooms, filters, floors, roomTypes, statuses, counts }: Props) {
    const { can } = usePermissions();
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search);
    const firstRender = useRef(true);

    // Look the selected room up from fresh props so the modal reflects updates after a status change.
    const selected = rooms.find((room) => room.id === selectedId) ?? null;

    const apply = (next: Partial<RoomFilters>) => {
        const merged = { ...filters, search, ...next };
        const query = Object.fromEntries(Object.entries(merged).filter(([, value]) => value !== ''));

        router.get('/rooms', query, { preserveState: true, preserveScroll: true, replace: true });
    };

    // Debounced search.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return undefined;
        }

        const timer = window.setTimeout(() => apply({ search }), 300);

        return () => window.clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasFilters = Object.values(filters).some((value) => value !== '');

    const reset = () => {
        setSearch('');
        router.get('/rooms', {}, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout title="Rooms">
            <PageHeader
                title="Rooms"
                subtitle="Live floor-by-floor room inventory"
                breadcrumbs={[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Rooms' }]}
                actions={
                    can('room.manage') ? (
                        <Link href="/rooms/create">
                            <Button leftIcon={<Plus className="h-4 w-4" />}>Add rooms</Button>
                        </Link>
                    ) : undefined
                }
            />

            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                <StatCard title="Total rooms" value={counts.total} icon={<BedDouble className="h-5 w-5" />} />
                <StatCard title="Vacant clean" value={counts.vacant_clean} />
                <StatCard title="Vacant dirty" value={counts.vacant_dirty} />
                <StatCard title="Cleaning" value={counts.cleaning} />
                <StatCard title="Occupied" value={counts.occupied} />
                <StatCard title="Out of order" value={counts.out_of_order} />
            </div>

            <Card className="mb-6 p-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <Input
                        aria-label="Search room number"
                        placeholder="Search room #"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <Select
                        aria-label="Floor"
                        placeholder="All floors"
                        options={floors.map((floor) => ({ value: floor, label: `Floor ${floor}` }))}
                        value={filters.floor}
                        onChange={(event) => apply({ floor: event.target.value })}
                    />
                    <Select
                        aria-label="Room type"
                        placeholder="All room types"
                        options={roomTypes}
                        value={filters.room_type_id}
                        onChange={(event) => apply({ room_type_id: event.target.value })}
                    />
                    <Select
                        aria-label="Status"
                        placeholder="All statuses"
                        options={statuses.map((status) => ({ value: status.value, label: status.label }))}
                        value={filters.status}
                        onChange={(event) => apply({ status: event.target.value })}
                    />
                    <Button variant="outline" disabled={!hasFilters && search === ''} onClick={reset}>
                        Clear filters
                    </Button>
                </div>
            </Card>

            {rooms.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<BedDouble className="h-6 w-6" />}
                        title={hasFilters ? 'No rooms match your filters' : 'No rooms yet'}
                        description={
                            hasFilters
                                ? 'Try clearing a filter.'
                                : 'Create room types first, then add rooms one by one or in batches.'
                        }
                        action={
                            !hasFilters && can('room.manage') ? (
                                <Link href="/rooms/create">
                                    <Button leftIcon={<Plus className="h-4 w-4" />}>Add rooms</Button>
                                </Link>
                            ) : undefined
                        }
                    />
                </Card>
            ) : (
                <RoomGrid rooms={rooms} onSelect={(room) => setSelectedId(room.id)} />
            )}

            <RoomQuickModal room={selected} statuses={statuses} canManage={can('room.manage')} onClose={() => setSelectedId(null)} />
        </AuthenticatedLayout>
    );
}

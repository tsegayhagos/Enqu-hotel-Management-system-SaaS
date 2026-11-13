import { memo, useMemo } from 'react';
import { Badge } from '@/Components/ui';
import { cn } from '@/lib/cn';
import type { RoomRow } from '@/types/models';

interface RoomGridProps {
    rooms: RoomRow[];
    onSelect: (room: RoomRow) => void;
}

const accent: Record<string, string> = {
    success: 'border-l-emerald-500',
    accent: 'border-l-accent-500',
    primary: 'border-l-primary-500',
    warning: 'border-l-amber-500',
    danger: 'border-l-red-500',
    info: 'border-l-sky-500',
    neutral: 'border-l-slate-400',
};

interface TileProps {
    room: RoomRow;
    onSelect: (room: RoomRow) => void;
}

/** Fixed-size tile so the grid never shifts layout, even with 200+ rooms. */
const RoomTile = memo(function RoomTile({ room, onSelect }: TileProps) {
    return (
        <button
            type="button"
            onClick={() => onSelect(room)}
            className={cn(
                'flex h-24 w-full flex-col justify-between rounded-lg border border-l-4 border-slate-200 bg-white p-3 text-left shadow-sm transition',
                'hover:-translate-y-0.5 hover:shadow-md dark:border-enquDark-700 dark:bg-enquDark-800',
                accent[room.status.variant] ?? accent.neutral,
                !room.is_active && 'opacity-60',
            )}
            aria-label={`Room ${room.room_number}, ${room.status.label}`}
        >
            <div className="flex items-start justify-between gap-2">
                <span className="text-lg font-semibold leading-none text-slate-900 dark:text-slate-50">{room.room_number}</span>
                {!room.is_active && <span className="text-[10px] font-medium uppercase text-slate-500">Inactive</span>}
            </div>
            <div className="min-w-0 space-y-1">
                <p className="truncate text-xs text-slate-500 dark:text-slate-400">{room.room_type.name}</p>
                <Badge variant={room.status.variant} dot className="max-w-full truncate">
                    {room.status.label}
                </Badge>
            </div>
        </button>
    );
});

export function RoomGrid({ rooms, onSelect }: RoomGridProps) {
    const floors = useMemo(() => {
        const grouped = new Map<number, RoomRow[]>();

        for (const room of rooms) {
            const list = grouped.get(room.floor_number);

            if (list) {
                list.push(room);
            } else {
                grouped.set(room.floor_number, [room]);
            }
        }

        return Array.from(grouped.entries()).sort((a, b) => a[0] - b[0]);
    }, [rooms]);

    return (
        <div className="space-y-8">
            {floors.map(([floor, floorRooms]) => (
                <section key={floor} aria-labelledby={`floor-${floor}`}>
                    <h2 id={`floor-${floor}`} className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Floor {floor} <span className="font-normal normal-case">({floorRooms.length} rooms)</span>
                    </h2>
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] gap-3">
                        {floorRooms.map((room) => (
                            <RoomTile key={room.id} room={room} onSelect={onSelect} />
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}

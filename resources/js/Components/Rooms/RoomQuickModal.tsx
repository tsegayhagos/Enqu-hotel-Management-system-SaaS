import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Badge, Button, Modal, Textarea } from '@/Components/ui';
import type { RoomRow, RoomStatusOption } from '@/types/models';

interface Props {
    room: RoomRow | null;
    statuses: RoomStatusOption[];
    canManage: boolean;
    onClose: () => void;
}

/** Quick actions for a room: details, status change and notes. */
export function RoomQuickModal({ room, statuses, canManage, onClose }: Props) {
    const [notes, setNotes] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setNotes(room?.notes ?? '');
        setError(null);
    }, [room]);

    if (!room) {
        return null;
    }

    const changeStatus = (status: string) => {
        setBusy(true);
        setError(null);

        router.patch(
            `/rooms/${room.id}/status`,
            { status },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errors) => setError(errors.status ?? 'Could not change the status.'),
                onFinish: () => setBusy(false),
            },
        );
    };

    const saveNotes = () => {
        setBusy(true);

        router.put(
            `/rooms/${room.id}`,
            { notes },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errors) => setError(errors.notes ?? 'Could not save the notes.'),
                onFinish: () => setBusy(false),
            },
        );
    };

    const toggleActive = () => {
        setBusy(true);

        router.put(
            `/rooms/${room.id}`,
            { is_active: !room.is_active },
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errors) => setError(errors.is_active ?? 'Could not update the room.'),
                onFinish: () => setBusy(false),
            },
        );
    };

    const options = statuses.filter((status) => room.allowed_statuses.includes(status.value));

    return (
        <Modal open title={`Room ${room.room_number}`} onClose={onClose}>
            <div className="space-y-5">
                <dl className="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt className="text-slate-500 dark:text-slate-400">Type</dt>
                        <dd className="font-medium text-slate-900 dark:text-slate-100">{room.room_type.name}</dd>
                    </div>
                    <div>
                        <dt className="text-slate-500 dark:text-slate-400">Floor</dt>
                        <dd className="font-medium text-slate-900 dark:text-slate-100">{room.floor_number}</dd>
                    </div>
                    <div>
                        <dt className="text-slate-500 dark:text-slate-400">Status</dt>
                        <dd>
                            <Badge variant={room.status.variant} dot>
                                {room.status.label}
                            </Badge>
                        </dd>
                    </div>
                    <div>
                        <dt className="text-slate-500 dark:text-slate-400">Availability</dt>
                        <dd className="font-medium text-slate-900 dark:text-slate-100">{room.is_active ? 'In service' : 'Inactive'}</dd>
                    </div>
                </dl>

                {error && (
                    <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
                        {error}
                    </p>
                )}

                {canManage ? (
                    <>
                        <div>
                            <p className="mb-2 text-sm font-medium text-slate-700 dark:text-slate-200">Change status</p>
                            {options.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {options.map((status) => (
                                        <Button key={status.value} size="sm" variant="outline" disabled={busy} onClick={() => changeStatus(status.value)}>
                                            {status.label}
                                        </Button>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    This room is occupied. Its status changes when the guest checks out.
                                </p>
                            )}
                        </div>

                        <Textarea label="Notes" value={notes} onChange={(event: React.ChangeEvent<HTMLTextAreaElement>) => setNotes(event.target.value)} />

                        <div className="flex flex-wrap justify-between gap-2">
                            <Button variant="ghost" size="sm" disabled={busy} onClick={toggleActive}>
                                {room.is_active ? 'Deactivate room' : 'Activate room'}
                            </Button>
                            <Button size="sm" isLoading={busy} onClick={saveNotes}>
                                Save notes
                            </Button>
                        </div>
                    </>
                ) : (
                    room.notes && <p className="text-sm text-slate-600 dark:text-slate-300">{room.notes}</p>
                )}
            </div>
        </Modal>
    );
}

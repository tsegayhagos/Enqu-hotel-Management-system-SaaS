<?php

namespace App\Http\Controllers\Rooms;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\BulkRoomCreateRequest;
use App\Http\Requests\Rooms\StoreRoomRequest;
use App\Http\Requests\Rooms\UpdateRoomRequest;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\BulkRoomCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function __construct(private readonly BulkRoomCreator $bulk) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'floor' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(RoomStatus::class)],
            'room_type_id' => ['nullable', 'integer'],
        ]);

        $rooms = Room::query()
            ->with('roomType:id,name')
            ->when($filters['search'] ?? null, fn ($q, string $s) => $q->where('room_number', 'like', '%'.$s.'%'))
            ->when(isset($filters['floor']), fn ($q) => $q->where('floor_number', (int) $filters['floor']))
            ->when($filters['status'] ?? null, fn ($q, string $s) => $q->where('status', $s))
            ->when($filters['room_type_id'] ?? null, fn ($q, $id) => $q->where('room_type_id', (int) $id))
            ->ordered()
            ->get()
            ->map(fn (Room $room): array => $this->present($room));

        $counts = Room::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Rooms/Index', [
            'rooms' => $rooms,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'floor' => isset($filters['floor']) ? (string) $filters['floor'] : '',
                'status' => $filters['status'] ?? '',
                'room_type_id' => isset($filters['room_type_id']) ? (string) $filters['room_type_id'] : '',
            ],
            'floors' => Room::query()->distinct()->orderBy('floor_number')->pluck('floor_number')->values(),
            'roomTypes' => $this->typeOptions(),
            'statuses' => $this->statusOptions(),
            'counts' => [
                'total' => (int) $counts->sum(),
                ...collect(RoomStatus::cases())->mapWithKeys(
                    fn (RoomStatus $s): array => [$s->value => (int) ($counts[$s->value] ?? 0)]
                )->all(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Rooms/Create', ['roomTypes' => $this->typeOptions()]);
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        Room::create($request->validated());

        return redirect()->route('rooms.index')->with('success', 'Room created.');
    }

    public function bulkStore(BulkRoomCreateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $type = RoomType::query()->findOrFail($data['room_type_id']);

        $created = $this->bulk->create(
            $type,
            (int) $data['floor_number'],
            (int) $data['start_number'],
            (int) $data['end_number'],
            (string) ($data['prefix'] ?? ''),
        );

        return redirect()->route('rooms.index')->with('success', $created->count().' rooms created.');
    }

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $data = $request->validated();

        $deactivating = array_key_exists('is_active', $data) && ! filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);

        if ($deactivating && $room->status === RoomStatus::Occupied) {
            throw ValidationException::withMessages(['is_active' => 'An occupied room cannot be deactivated.']);
        }

        $room->update($data);

        return back()->with('success', 'Room updated.');
    }

    /** @return array<string, mixed> */
    private function present(Room $room): array
    {
        $allowed = array_values(array_filter(
            $room->status->allowedTransitions(),
            fn (RoomStatus $s): bool => in_array($s, RoomStatus::manuallySettable(), true)
        ));

        return [
            'id' => $room->id,
            'room_number' => $room->room_number,
            'floor_number' => $room->floor_number,
            'is_active' => $room->is_active,
            'notes' => $room->notes,
            'status' => [
                'value' => $room->status->value,
                'label' => $room->status->label(),
                'variant' => $room->status->badgeVariant(),
            ],
            'room_type' => ['id' => $room->room_type_id, 'name' => $room->roomType?->name],
            // Manual transitions only; a room leaves "Occupied" through check-out.
            'allowed_statuses' => $room->status === RoomStatus::Occupied ? [] : array_map(fn (RoomStatus $s): string => $s->value, $allowed),
        ];
    }

    /** @return list<array{value: int, label: string}> */
    private function typeOptions(): array
    {
        return RoomType::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (RoomType $t): array => ['value' => $t->id, 'label' => $t->name])
            ->all();
    }

    /** @return list<array{value: string, label: string, variant: string}> */
    private function statusOptions(): array
    {
        return array_map(
            fn (RoomStatus $s): array => ['value' => $s->value, 'label' => $s->label(), 'variant' => $s->badgeVariant()],
            RoomStatus::cases(),
        );
    }
}

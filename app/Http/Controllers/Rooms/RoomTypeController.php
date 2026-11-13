<?php

namespace App\Http\Controllers\Rooms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\RoomTypeRequest;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoomTypeController extends Controller
{
    public function index(): Response
    {
        $types = RoomType::query()
            ->withCount('rooms')
            ->orderBy('name')
            ->get()
            ->map(fn (RoomType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'base_occupancy' => $type->base_occupancy,
                'max_occupancy' => $type->max_occupancy,
                'base_price' => $type->base_price,
                'extra_person_fee' => $type->extra_person_fee,
                'amenities' => $type->amenities ?? [],
                'rooms_count' => $type->rooms_count,
            ]);

        return Inertia::render('RoomTypes/Index', ['roomTypes' => $types]);
    }

    public function store(RoomTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['amenities'] = $this->cleanAmenities($data['amenities'] ?? []);

        RoomType::create($data);

        return redirect()->route('room-types.index')->with('success', 'Room type created.');
    }

    public function update(RoomTypeRequest $request, RoomType $roomType): RedirectResponse
    {
        $data = $request->validated();
        $data['amenities'] = $this->cleanAmenities($data['amenities'] ?? []);

        $roomType->update($data);

        return redirect()->route('room-types.index')->with('success', 'Room type updated.');
    }

    public function destroy(RoomType $roomType): RedirectResponse
    {
        $count = $roomType->rooms()->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'room_type' => "This type still has {$count} room(s). Move or delete them first.",
            ]);
        }

        $roomType->delete();

        return redirect()->route('room-types.index')->with('success', 'Room type deleted.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'room-type';
        $slug = $base;
        $counter = 2;

        while (RoomType::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * @param  array<int, string|null>  $amenities
     * @return list<string>
     */
    private function cleanAmenities(array $amenities): array
    {
        $clean = array_map(fn (?string $a): string => trim((string) $a), $amenities);

        return array_values(array_unique(array_filter($clean, fn (string $a): bool => $a !== '')));
    }
}

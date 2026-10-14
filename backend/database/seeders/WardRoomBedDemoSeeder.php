<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Room;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardRoomBedDemoSeeder extends Seeder
{
    /**
     * Rooms whose type dictates the nightly price and the bed count.
     *
     * @var array<string, array{price: float, beds: int}>
     */
    private const ROOM_TYPE_RULES = [
        Room::TYPE_GENERAL => ['price' => 15.00, 'beds' => 4],
        Room::TYPE_SEMI_PRIVATE => ['price' => 30.00, 'beds' => 2],
        Room::TYPE_PRIVATE => ['price' => 55.00, 'beds' => 1],
        Room::TYPE_ISOLATION => ['price' => 80.00, 'beds' => 1],
    ];

    /**
     * Five floors, five rooms each, with a mix of room types per floor.
     *
     * @var array<int, array{floor: int, code: string, name: string, type: string, description: string, rooms: array<int, string>}>
     */
    private const WARDS = [
        [
            'floor' => 1,
            'code' => 'FL1-GEN',
            'name' => 'General Medicine Ward',
            'type' => Ward::TYPE_GENERAL,
            'description' => 'Ground floor general admission ward.',
            'rooms' => [Room::TYPE_GENERAL, Room::TYPE_GENERAL, Room::TYPE_SEMI_PRIVATE, Room::TYPE_PRIVATE, Room::TYPE_ISOLATION],
        ],
        [
            'floor' => 2,
            'code' => 'FL2-SUR',
            'name' => 'Surgical Ward',
            'type' => Ward::TYPE_SURGICAL,
            'description' => 'Post-operative and surgical recovery.',
            'rooms' => [Room::TYPE_GENERAL, Room::TYPE_GENERAL, Room::TYPE_SEMI_PRIVATE, Room::TYPE_PRIVATE, Room::TYPE_ISOLATION],
        ],
        [
            'floor' => 3,
            'code' => 'FL3-MAT',
            'name' => 'Maternity Ward',
            'type' => Ward::TYPE_MATERNITY,
            'description' => 'Antenatal and postnatal care.',
            'rooms' => [Room::TYPE_GENERAL, Room::TYPE_GENERAL, Room::TYPE_SEMI_PRIVATE, Room::TYPE_PRIVATE, Room::TYPE_PRIVATE],
        ],
        [
            'floor' => 4,
            'code' => 'FL4-PED',
            'name' => 'Pediatric Ward',
            'type' => Ward::TYPE_PEDIATRIC,
            'description' => 'Care for infants, children and adolescents.',
            'rooms' => [Room::TYPE_GENERAL, Room::TYPE_GENERAL, Room::TYPE_SEMI_PRIVATE, Room::TYPE_SEMI_PRIVATE, Room::TYPE_PRIVATE],
        ],
        [
            'floor' => 5,
            'code' => 'FL5-ICU',
            'name' => 'Intensive Care Unit',
            'type' => Ward::TYPE_ICU,
            'description' => 'High-dependency and intensive care.',
            'rooms' => [Room::TYPE_PRIVATE, Room::TYPE_PRIVATE, Room::TYPE_PRIVATE, Room::TYPE_ISOLATION, Room::TYPE_ISOLATION],
        ],
    ];

    public function run(): void
    {
        foreach (self::WARDS as $definition) {
            $capacity = array_sum(array_map(
                fn (string $type) => self::ROOM_TYPE_RULES[$type]['beds'],
                $definition['rooms'],
            ));

            $ward = Ward::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'floor' => (string) $definition['floor'],
                    'ward_type' => $definition['type'],
                    'capacity' => $capacity,
                    'description' => $definition['description'],
                    'is_active' => true,
                ],
            );

            foreach ($definition['rooms'] as $index => $roomType) {
                $rule = self::ROOM_TYPE_RULES[$roomType];
                $roomNumber = (string) (($definition['floor'] * 100) + $index + 1);

                $room = Room::updateOrCreate(
                    ['ward_id' => $ward->id, 'room_number' => $roomNumber],
                    [
                        'room_type' => $roomType,
                        'capacity' => $rule['beds'],
                        'price_per_day' => $rule['price'],
                        'notes' => null,
                        'is_active' => true,
                    ],
                );

                $labels = ['A', 'B', 'C', 'D'];

                for ($i = 0; $i < $rule['beds']; $i++) {
                    $existing = Bed::withTrashed()
                        ->where('room_id', $room->id)
                        ->where('label', $labels[$i])
                        ->first();

                    if ($existing === null) {
                        Bed::create([
                            'room_id' => $room->id,
                            'label' => $labels[$i],
                            'status' => $this->bedStatus($index, $i),
                            'notes' => null,
                        ]);
                    } elseif ($existing->status !== Bed::STATUS_OCCUPIED) {
                        // Never touch a bed that a real admission occupies.
                        $existing->update([
                            'status' => $this->bedStatus($index, $i),
                            'deleted_at' => null,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * A mostly-available set with a few cleaning/maintenance beds for realism.
     * Occupied beds are never seeded so they always match real admissions.
     */
    private function bedStatus(int $roomIndex, int $bedIndex): string
    {
        if ($roomIndex === 0 && $bedIndex === 3) {
            return Bed::STATUS_CLEANING;
        }

        if ($roomIndex === 2 && $bedIndex === 1) {
            return Bed::STATUS_MAINTENANCE;
        }

        return Bed::STATUS_AVAILABLE;
    }
}

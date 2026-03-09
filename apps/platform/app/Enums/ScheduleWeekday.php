<?php

namespace App\Enums;

enum ScheduleWeekday: string
{
    case Monday = 'monday';
    case Tuesday = 'tuesday';
    case Wednesday = 'wednesday';
    case Thursday = 'thursday';
    case Friday = 'friday';
    case Saturday = 'saturday';
    case Sunday = 'sunday';

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Понедельник',
            self::Tuesday => 'Вторник',
            self::Wednesday => 'Среда',
            self::Thursday => 'Четверг',
            self::Friday => 'Пятница',
            self::Saturday => 'Суббота',
            self::Sunday => 'Воскресенье',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Monday => 1,
            self::Tuesday => 2,
            self::Wednesday => 3,
            self::Thursday => 4,
            self::Friday => 5,
            self::Saturday => 6,
            self::Sunday => 7,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $weekday) => [
                'value' => $weekday->value,
                'label' => $weekday->label(),
            ],
            self::cases(),
        );
    }
}

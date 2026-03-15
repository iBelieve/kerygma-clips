<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LectionaryService
{
    /** @var array<string, string> */
    public const array COLOR_MAP = [
        'white' => '#9e8554',
        'black' => '#27272a',
        'blue' => '#2662a3',
        'purple' => '#66479c',
        'red' => '#a13333',
        'green' => '#3f8164',
    ];

    /**
     * @return array<string, array{name: string, color: string|null}>
     */
    public function getDays(int $year, int $month): array
    {
        $key = "lectionary:{$year}-{$month}";

        return Cache::rememberForever($key, function () use ($year, $month): array {
            return $this->fetchDays($year, $month);
        });
    }

    /**
     * @return array<string, array{name: string, color: string|null}>
     */
    private function fetchDays(int $year, int $month): array
    {
        try {
            $response = Http::get("https://mluther.org/api/lectionary/{$year}/{$month}");

            if (! $response->successful()) {
                return [];
            }

            /** @var array<int, array{date: string, short_name: string, color_key: string}> $entries */
            $entries = $response->json('data', []);
            $days = [];

            foreach ($entries as $entry) {
                $days[$entry['date']] = [
                    'name' => $entry['short_name'],
                    'color' => self::COLOR_MAP[$entry['color_key']] ?? null,
                ];
            }

            return $days;
        } catch (\Throwable) {
            return [];
        }
    }
}

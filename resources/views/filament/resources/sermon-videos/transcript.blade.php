@php
    $transcript = $getState();
    $segments = $transcript['segments'] ?? [];

    $formatTimestamp = function (float $seconds) use ($segments): string {
        $lastStart = end($segments)['start'] ?? 0;
        $totalSeconds = (int) $seconds;
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $secs = $totalSeconds % 60;

        if ($lastStart >= 3600) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    };
@endphp

@if (count($segments))
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="overflow-x-auto">
            <table class="w-full">
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($segments as $segment)
                        <tr class="transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="whitespace-nowrap py-2 pe-3 ps-4 align-baseline text-sm tabular-nums text-gray-500 sm:ps-6 dark:text-gray-400"
                                style="font-variant-numeric: tabular-nums;"
                            >
                                {{ $formatTimestamp($segment['start']) }}
                            </td>
                            <td class="w-full py-2 pe-4 align-baseline text-sm text-gray-950 sm:pe-6 dark:text-white">
                                {{ trim($segment['text']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-center p-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">No transcript available.</p>
        </div>
    </div>
@endif

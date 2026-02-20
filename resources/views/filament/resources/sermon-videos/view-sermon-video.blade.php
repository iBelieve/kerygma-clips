<x-filament-panels::page>
    @php
        $transcript = $this->getRecord()->transcript;
        $segments = $transcript['segments'] ?? [];
        $lastStart = end($segments)['start'] ?? 0;
    @endphp

    @if (count($segments))
        <div class="flex items-center gap-3">
            <label for="gapThreshold" class="whitespace-nowrap text-sm font-medium text-gray-950 dark:text-white">
                Gap threshold
            </label>
            <x-filament::input.wrapper class="max-w-48">
                <x-filament::input
                    type="number"
                    id="gapThreshold"
                    wire:model.live.debounce.500ms="gapThreshold"
                    min="1"
                />
                <x-slot name="suffix">seconds</x-slot>
            </x-filament::input.wrapper>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto py-3">
                <table class="w-full">
                    <tbody>
                        @php $previousEnd = null; @endphp
                        @foreach ($segments as $segment)
                            @if ($previousEnd !== null)
                                @php $gap = $segment['start'] - $previousEnd; @endphp
                                @if ($gap > $this->gapThreshold)
                                    <tr>
                                        <td colspan="2" class="px-4 sm:px-6">
                                            <div class="flex items-center gap-3 py-2">
                                                <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                                <span class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">
                                                    {{ $this->formatGap($gap) }}
                                                </span>
                                                <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endif
                            <tr class="transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap py-2 pe-3 ps-4 align-baseline text-sm text-end tabular-nums text-gray-500 sm:ps-6 dark:text-gray-400"
                                    style="font-variant-numeric: tabular-nums;"
                                >
                                    {{ $this->formatTimestamp($segment['start'], $lastStart) }}
                                </td>
                                <td class="w-full py-2 pe-4 align-baseline text-sm text-gray-950 sm:pe-6 dark:text-white">
                                    {{ trim($segment['text']) }}
                                </td>
                            </tr>
                            @php $previousEnd = $segment['end']; @endphp
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
</x-filament-panels::page>

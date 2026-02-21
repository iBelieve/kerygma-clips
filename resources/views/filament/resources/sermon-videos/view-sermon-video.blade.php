<x-filament-panels::page>
    @if (count($this->transcriptRows))
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-end gap-3">
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

            <div
                x-data="viewTranscript()"
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('view-transcript') }}"
                x-on:mouseleave="clearHighlight()"
                class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="overflow-x-auto py-3">
                    <table class="w-full">
                        <tbody>
                            @foreach ($this->transcriptRows as $row)
                                @if ($row['type'] === 'gap')
                                    <tr
                                        @if ($row['inClip'])
                                            class="bg-emerald-100 transition duration-75 dark:bg-emerald-500/10"
                                        @else
                                            x-bind:class="isGapHighlighted({{ $row['prevSegmentIndex'] }}, {{ $row['nextSegmentIndex'] }}) ? 'bg-orange-100 dark:bg-orange-500/10' : ''"
                                            class="transition duration-75"
                                        @endif
                                    >
                                        <td colspan="2" class="px-4 sm:px-6">
                                            <div class="flex items-center gap-3 py-2">
                                                <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                                <span class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">
                                                    {{ $row['label'] }}
                                                </span>
                                                <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                            </div>
                                        </td>
                                    </tr>
                                @else
                                    <tr
                                        @if ($row['inClip'])
                                            x-on:mouseenter="clearHighlight()"
                                        @else
                                            x-on:mouseenter="setHighlight({{ $row['segmentIndex'] }}, {{ $row['highlightEnd'] }})"
                                            x-on:click="$wire.createClip({{ $row['segmentIndex'] }}, {{ $row['highlightEnd'] }})"
                                            x-bind:class="isHighlighted({{ $row['segmentIndex'] }}) ? 'bg-orange-100 dark:bg-orange-500/10' : ''"
                                        @endif
                                        class="{{ $row['inClip'] ? 'bg-emerald-100 dark:bg-emerald-500/10' : 'cursor-pointer' }} transition duration-75"
                                    >
                                        <td class="whitespace-nowrap py-1 pe-3 ps-4 align-baseline text-xs text-end tabular-nums text-gray-500 sm:ps-6 dark:text-gray-400"
                                            style="font-variant-numeric: tabular-nums;"
                                        >
                                            {{ $this->formatTimestamp($row['start']) }}
                                        </td>
                                        <td class="w-full py-1 pe-4 align-baseline text-sm text-gray-950 sm:pe-6 dark:text-white">
                                            {{ $row['text'] }}
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
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

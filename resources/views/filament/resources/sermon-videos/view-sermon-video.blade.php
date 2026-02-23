<x-filament-panels::page>
    @if (count($this->transcriptData['segments']))
        <div
            x-data="viewTranscript(@js($this->transcriptData))"
            class="flex flex-col gap-4"
        >
            <div class="flex items-center justify-end gap-3">
                <label for="gapThreshold" class="whitespace-nowrap text-sm font-medium text-gray-950 dark:text-white">
                    Gap threshold
                </label>
                <x-filament::input.wrapper class="max-w-48">
                    <x-filament::input
                        type="number"
                        id="gapThreshold"
                        x-model.debounce.500ms="gapThreshold"
                        min="1"
                    />
                    <x-slot name="suffix">seconds</x-slot>
                </x-filament::input.wrapper>
            </div>

            <div
                dusk="transcript-table"
                x-on:mouseleave="clearHighlight()"
                class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="overflow-x-auto py-3">
                    <table class="w-full">
                        <tbody>
                            <template x-for="(row, i) in rows" :key="i">
                                <tr
                                    x-bind:dusk="row.type === 'segment' ? `segment-row-${row.segmentIndex}` : `gap-row-${i}`"
                                    x-bind:class="
                                        row.type === 'gap'
                                            ? (gapInClip(row.prevSegmentIndex, row.nextSegmentIndex)
                                                ? 'bg-emerald-100 dark:bg-emerald-500/10'
                                                : (isGapHighlighted(row.prevSegmentIndex, row.nextSegmentIndex)
                                                    ? 'bg-orange-100 dark:bg-orange-500/10'
                                                    : ''))
                                            : (inClip(row.segmentIndex)
                                                ? 'bg-emerald-100 dark:bg-emerald-500/10'
                                                : (isHighlighted(row.segmentIndex)
                                                    ? 'bg-orange-100 dark:bg-orange-500/10 cursor-pointer'
                                                    : 'cursor-pointer'))
                                    "
                                    x-on:mouseenter="
                                        row.type === 'segment' && (inClip(row.segmentIndex)
                                            ? clearHighlight()
                                            : setHighlight(row.segmentIndex))
                                    "
                                    x-on:click="
                                        row.type === 'segment' && !inClip(row.segmentIndex)
                                            && createClip(row.segmentIndex, highlightEnds[row.segmentIndex])
                                    "
                                    class="transition duration-75"
                                >
                                    {{-- Gap row content --}}
                                    <td
                                        x-show="row.type === 'gap'"
                                        colspan="2"
                                        class="px-4 sm:px-6"
                                    >
                                        <div class="flex items-center gap-3 py-2">
                                            <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                            <span
                                                class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500"
                                                x-text="row.label"
                                            ></span>
                                            <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                        </div>
                                    </td>

                                    {{-- Segment row content --}}
                                    <td
                                        x-show="row.type === 'segment'"
                                        class="whitespace-nowrap py-1 pe-3 ps-4 align-baseline text-xs text-end tabular-nums text-gray-500 sm:ps-6 dark:text-gray-400"
                                        x-text="row.type === 'segment' ? formatTimestamp(row.start) : ''"
                                    ></td>
                                    <td
                                        x-show="row.type === 'segment'"
                                        class="w-full py-1 pe-4 align-baseline text-sm text-gray-950 sm:pe-6 dark:text-white"
                                        x-text="row.text"
                                    ></td>
                                </tr>
                            </template>
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

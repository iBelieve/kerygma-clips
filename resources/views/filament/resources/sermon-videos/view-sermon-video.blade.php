<x-filament-panels::page>
    @if (count($this->transcriptData['segments']))
        <div
            x-data="viewTranscript(@js($this->transcriptData))"
            x-on:mouseup.window="endDrag()"
            x-bind:class="{ 'select-none': dragging }"
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
                x-on:mouseleave="!dragging && clearHighlight()"
                class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="overflow-x-auto py-3">
                    <table class="w-full">
                        <tbody>
                            <template x-for="(row, i) in rows" :key="i">
                                <tr
                                    x-bind:dusk="row.type === 'segment' ? `segment-row-${row.segmentIndex}` : `gap-row-${i}`"
                                    x-bind:class="{
                                        'bg-emerald-100 dark:bg-emerald-500/10': row.type === 'gap'
                                            ? gapInClip(row.prevSegmentIndex, row.nextSegmentIndex)
                                            : inClip(row.segmentIndex),
                                        'bg-orange-100 dark:bg-orange-500/10': row.type === 'gap'
                                            ? isGapHighlighted(row.prevSegmentIndex, row.nextSegmentIndex)
                                            : isHighlighted(row.segmentIndex),
                                        'cursor-pointer': row.type === 'segment' && !inClip(row.segmentIndex),
                                        'group/drag cursor-ns-resize': row.type === 'segment'
                                            && (isClipStart(row.segmentIndex) || isClipEnd(row.segmentIndex)),
                                    }"
                                    x-on:mouseenter="
                                        row.type === 'segment' && (dragging
                                            ? handleDragOver(row.segmentIndex)
                                            : (inClip(row.segmentIndex)
                                                ? clearHighlight()
                                                : setHighlight(row.segmentIndex)))
                                    "
                                    x-on:mousedown="
                                        row.type === 'segment' && startDragFromRow(row.segmentIndex, $event)
                                    "
                                    x-on:click="
                                        !dragging && row.type === 'segment' && !inClip(row.segmentIndex)
                                            && createClip(row.segmentIndex, highlightEnds[row.segmentIndex])
                                    "
                                    class="relative transition duration-75"
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
                                    >
                                        <span x-text="row.type === 'segment' ? formatTimestamp(row.start) : ''"></span>
                                    </td>
                                    <td
                                        x-show="row.type === 'segment'"
                                        class="w-full py-1 pe-4 align-baseline text-sm text-gray-950 sm:pe-6 dark:text-white"
                                    >
                                        <div class="flex items-baseline gap-2">
                                            <span class="flex-1" x-text="row.text"></span>

                                            {{-- Duration badge on clip start row --}}
                                            <span
                                                x-show="isClipStart(row.segmentIndex)"
                                                x-text="formatDuration(clipDurationOfSegment(row.segmentIndex))"
                                                class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium tabular-nums text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                                            ></span>

                                            {{-- Delete button on clip start row --}}
                                            <button
                                                x-show="isClipStart(row.segmentIndex)"
                                                x-on:click.stop="deleteClip(clips[clipIndexOfSegment(row.segmentIndex)].id)"
                                                type="button"
                                                title="Delete clip"
                                                class="shrink-0 rounded p-0.5 text-gray-400 transition hover:bg-red-100 hover:text-red-600 dark:text-gray-500 dark:hover:bg-red-500/10 dark:hover:text-red-400"
                                            >
                                                <x-heroicon-o-trash class="h-4 w-4" />
                                            </button>
                                        </div>

                                        {{-- Top drag handle for clip start --}}
                                        <div
                                            x-show="row.type === 'segment' && isClipStart(row.segmentIndex)"
                                            class="absolute inset-x-0 top-0 h-1 bg-emerald-400/40 transition group-hover/drag:bg-emerald-400"
                                        ></div>

                                        {{-- Bottom drag handle for clip end --}}
                                        <div
                                            x-show="row.type === 'segment' && isClipEnd(row.segmentIndex)"
                                            class="absolute inset-x-0 bottom-0 h-1 bg-emerald-400/40 transition group-hover/drag:bg-emerald-400"
                                        ></div>
                                    </td>
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

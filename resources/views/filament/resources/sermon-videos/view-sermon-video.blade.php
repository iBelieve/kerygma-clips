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
                x-on:mouseleave="clearHighlight()"
                x-bind:style="dragging ? 'touch-action: none' : ''"
                class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="overflow-x-auto py-3">
                    <table class="w-full">
                        <tbody>
                            <template x-for="(row, i) in rows" :key="i">
                                <tr
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
                                        row.type === 'segment'
                                            ? (dragging
                                                ? dragOver(row.segmentIndex)
                                                : (inClip(row.segmentIndex)
                                                    ? clearHighlight()
                                                    : setHighlight(row.segmentIndex)))
                                            : (dragging
                                                ? dragOver(dragging.handle === 'end' ? row.nextSegmentIndex : row.prevSegmentIndex)
                                                : null)
                                    "
                                    x-on:click="
                                        row.type === 'segment' && !dragging && !inClip(row.segmentIndex)
                                            && createClip(row.segmentIndex, highlightEnds[row.segmentIndex])
                                    "
                                    class="transition duration-75"
                                >
                                    {{-- Gap row content --}}
                                    <td
                                        x-show="row.type === 'gap'"
                                        colspan="3"
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

                                    {{-- Segment row: timestamp --}}
                                    <td
                                        x-show="row.type === 'segment'"
                                        class="whitespace-nowrap py-1 pe-3 ps-4 align-baseline text-xs text-end tabular-nums text-gray-500 sm:ps-6 dark:text-gray-400"
                                        x-text="row.type === 'segment' ? formatTimestamp(row.start) : ''"
                                    ></td>

                                    {{-- Segment row: text + duration badge --}}
                                    <td
                                        x-show="row.type === 'segment'"
                                        class="w-full py-1 pe-4 align-baseline text-sm text-gray-950 sm:pe-6 dark:text-white"
                                    >
                                        <span x-text="row.text"></span>
                                        <template x-if="row.type === 'segment' && (clipHandleType(row.segmentIndex) === 'start' || clipHandleType(row.segmentIndex) === 'both')">
                                            <span
                                                class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400"
                                                x-text="formatDuration(
                                                    dragging && dragging.clipId === clipFor(row.segmentIndex)?.id
                                                        ? clipDuration(dragging.currentStart, dragging.currentEnd)
                                                        : clipDuration(clipFor(row.segmentIndex)?.start, clipFor(row.segmentIndex)?.end)
                                                )"
                                            ></span>
                                        </template>
                                    </td>

                                    {{-- Segment row: drag handles --}}
                                    <td
                                        x-show="row.type === 'segment'"
                                        class="w-8 pe-2 align-middle"
                                    >
                                        {{-- Start handle --}}
                                        <template x-if="row.type === 'segment' && (clipHandleType(row.segmentIndex) === 'start' || clipHandleType(row.segmentIndex) === 'both')">
                                            <div
                                                x-on:pointerdown="startDrag(clipFor(row.segmentIndex).id, 'start', $event)"
                                                class="flex cursor-row-resize items-center justify-center rounded p-1 text-emerald-500 hover:bg-emerald-100 dark:hover:bg-emerald-500/20"
                                                title="Drag to adjust clip start"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </template>
                                        {{-- End handle --}}
                                        <template x-if="row.type === 'segment' && (clipHandleType(row.segmentIndex) === 'end' || clipHandleType(row.segmentIndex) === 'both')">
                                            <div
                                                x-on:pointerdown="startDrag(clipFor(row.segmentIndex).id, 'end', $event)"
                                                class="flex cursor-row-resize items-center justify-center rounded p-1 text-emerald-500 hover:bg-emerald-100 dark:hover:bg-emerald-500/20"
                                                title="Drag to adjust clip end"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                        </template>
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

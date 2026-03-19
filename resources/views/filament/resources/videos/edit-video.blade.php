<x-filament-panels::page>
    {{ $this->content }}

    @if (count($this->transcriptData['segments']))
        <div
            x-data="viewTranscript(@js($this->transcriptData))"
            x-on:mouseup.window="endDrag()"
            x-on:mouseleave="!dragging && clearHighlight()"
            x-bind:class="{ 'select-none': dragging }"
            dusk="transcript-table"
            class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        >
            <div class="py-3">
                <div
                    class="grid w-full"
                    x-bind:style="diarize
                        ? 'grid-template-columns: auto auto 1fr'
                        : 'grid-template-columns: auto 1fr'"
                >
                    <template x-for="(row, i) in rows" :key="i">
                        <div
                            x-bind:dusk="row.type === 'segment' ? `segment-row-${row.segmentIndex}` : `gap-row-${i}`"
                            x-bind:class="{
                                'bg-emerald-100 dark:bg-emerald-500/10': row.type === 'gap' || row.type === 'speaker-change'
                                    ? gapInClip(row.prevSegmentIndex, row.nextSegmentIndex)
                                    : inClip(row.segmentIndex),
                                'bg-orange-100 dark:bg-orange-500/10': row.type === 'gap' || row.type === 'speaker-change'
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
                            class="relative col-span-full grid grid-cols-subgrid items-baseline transition duration-75"
                        >
                            {{-- Gap row content --}}
                            <div
                                x-show="row.type === 'gap'"
                                class="col-span-full px-4 sm:px-6"
                            >
                                <div class="flex items-center gap-3 py-2">
                                    <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                    <span
                                        class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500"
                                        x-text="row.label"
                                    ></span>
                                    <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                </div>
                            </div>

                            {{-- Speaker change divider --}}
                            <div
                                x-show="row.type === 'speaker-change'"
                                class="col-span-full px-4 py-2 sm:px-6"
                            >
                                <div class="border-t border-gray-200 dark:border-gray-700"></div>
                            </div>

                            {{-- Segment: timestamp column --}}
                            <div
                                x-show="row.type === 'segment'"
                                class="whitespace-nowrap py-1 pe-3 ps-4 text-end text-xs tabular-nums text-gray-500 sm:ps-6 dark:text-gray-400"
                            >
                                <span x-text="row.type === 'segment' ? formatTimestamp(row.start) : ''"></span>
                            </div>

                            {{-- Segment: speaker column (only for diarized videos) --}}
                            <div
                                x-show="row.type === 'segment' && diarize"
                                class="whitespace-nowrap py-1 pe-3"
                            >
                                <button
                                    x-show="row.showSpeaker"
                                    type="button"
                                    x-on:click.stop="$wire.mountAction('renameSpeaker', { speaker: row.speaker })"
                                    class="rounded px-1.5 py-0.5 text-xs font-medium text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-500/10"
                                    x-text="$wire.speakerNames[row.speaker] || row.speaker"
                                ></button>
                            </div>

                            {{-- Segment: text column --}}
                            <div
                                x-show="row.type === 'segment'"
                                class="min-w-0 py-1 pe-4 text-sm text-gray-950 sm:pe-6 dark:text-white"
                            >
                                <div class="flex items-baseline gap-2">
                                    <span class="flex-1" x-text="row.text"></span>

                                    {{-- Duration badge on clip start row --}}
                                    <span
                                        x-show="isClipStart(row.segmentIndex)"
                                        x-text="formatDuration(clipDurationOfSegment(row.segmentIndex))"
                                        class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium tabular-nums text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400"
                                    ></span>
                                </div>
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
                        </div>
                    </template>
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

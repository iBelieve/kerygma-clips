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
                x-data="{
                    highlightStart: null,
                    highlightEnd: null,

                    dragging: false,
                    dragClipId: null,
                    dragEdge: null,
                    dragOriginalStart: null,
                    dragOriginalEnd: null,
                    dragCurrentStart: null,
                    dragCurrentEnd: null,

                    segmentTimings: @js($this->segmentTimings),
                    clipBoundaries: @js(
                        $this->getRecord()->sermonClips()
                            ->orderBy('start_segment_index')
                            ->get(['id', 'start_segment_index', 'end_segment_index'])
                            ->map(fn($c) => ['id' => $c->id, 'start' => $c->start_segment_index, 'end' => $c->end_segment_index])
                            ->values()
                    ),

                    get dragDuration() {
                        if (!this.dragging || this.dragCurrentStart === null || this.dragCurrentEnd === null) return null;
                        const startTime = this.segmentTimings[this.dragCurrentStart]?.start ?? 0;
                        const endTime = this.segmentTimings[this.dragCurrentEnd]?.end ?? 0;
                        return endTime - startTime;
                    },

                    formatDuration(seconds) {
                        const total = Math.round(seconds);
                        const m = Math.floor(total / 60);
                        const s = total % 60;
                        return m + ':' + String(s).padStart(2, '0');
                    },

                    wouldExceedMax(testStart, testEnd) {
                        const startTime = this.segmentTimings[testStart]?.start ?? 0;
                        const endTime = this.segmentTimings[testEnd]?.end ?? 0;
                        return (endTime - startTime) > 90;
                    },

                    wouldOverlap(testStart, testEnd) {
                        return this.clipBoundaries.some(c =>
                            c.id !== this.dragClipId &&
                            testStart <= c.end &&
                            testEnd >= c.start
                        );
                    },

                    startDrag(clipId, edge, clipStart, clipEnd) {
                        this.dragging = true;
                        this.dragClipId = clipId;
                        this.dragEdge = edge;
                        this.dragOriginalStart = clipStart;
                        this.dragOriginalEnd = clipEnd;
                        this.dragCurrentStart = clipStart;
                        this.dragCurrentEnd = clipEnd;
                        this.highlightStart = null;
                        this.highlightEnd = null;
                    },

                    onDragOver(segmentIndex) {
                        if (!this.dragging) return;
                        let testStart = this.dragEdge === 'start' ? segmentIndex : this.dragCurrentStart;
                        let testEnd = this.dragEdge === 'end' ? segmentIndex : this.dragCurrentEnd;
                        if (testStart > testEnd) return;
                        if (this.wouldOverlap(testStart, testEnd)) return;
                        if (this.wouldExceedMax(testStart, testEnd)) return;
                        this.dragCurrentStart = testStart;
                        this.dragCurrentEnd = testEnd;
                    },

                    async endDrag() {
                        if (!this.dragging) return;
                        const changed = this.dragCurrentStart !== this.dragOriginalStart
                                      || this.dragCurrentEnd !== this.dragOriginalEnd;
                        const clipId = this.dragClipId;
                        const newStart = this.dragCurrentStart;
                        const newEnd = this.dragCurrentEnd;

                        this.dragging = false;
                        this.dragClipId = null;
                        this.dragEdge = null;
                        this.dragOriginalStart = null;
                        this.dragOriginalEnd = null;
                        this.dragCurrentStart = null;
                        this.dragCurrentEnd = null;

                        if (changed) {
                            await $wire.resizeClip(clipId, newStart, newEnd);
                        }
                    },

                    isInDragPreview(segmentIndex) {
                        if (!this.dragging) return false;
                        return segmentIndex >= this.dragCurrentStart && segmentIndex <= this.dragCurrentEnd;
                    },

                    isGapInDragPreview(prevIndex, nextIndex) {
                        if (!this.dragging) return false;
                        return prevIndex >= this.dragCurrentStart && nextIndex <= this.dragCurrentEnd;
                    },
                }"
                x-on:mouseleave="if (!dragging) { highlightStart = null; highlightEnd = null; }"
                x-on:mouseup.window="endDrag()"
                x-bind:class="{ 'cursor-row-resize select-none': dragging }"
                class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="overflow-x-auto py-3">
                    <table class="w-full">
                        <tbody>
                            @foreach ($this->transcriptRows as $row)
                                @if ($row['type'] === 'gap')
                                    <tr
                                        @if ($row['inClip'])
                                            x-bind:class="{
                                                'bg-blue-100 dark:bg-blue-500/10': isGapInDragPreview({{ $row['prevSegmentIndex'] }}, {{ $row['nextSegmentIndex'] }}),
                                                'bg-emerald-100 dark:bg-emerald-500/10': !isGapInDragPreview({{ $row['prevSegmentIndex'] }}, {{ $row['nextSegmentIndex'] }}),
                                            }"
                                        @else
                                            x-bind:class="{
                                                'bg-blue-100 dark:bg-blue-500/10': isGapInDragPreview({{ $row['prevSegmentIndex'] }}, {{ $row['nextSegmentIndex'] }}),
                                                'bg-orange-100 dark:bg-orange-500/10': !dragging && highlightStart !== null && {{ $row['prevSegmentIndex'] }} >= highlightStart && {{ $row['nextSegmentIndex'] }} <= highlightEnd,
                                            }"
                                        @endif
                                        class="transition duration-75"
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
                                            x-on:mouseenter="if (!dragging) { highlightStart = null; highlightEnd = null; } onDragOver({{ $row['segmentIndex'] }})"
                                            x-bind:class="{
                                                'bg-blue-100 dark:bg-blue-500/10': isInDragPreview({{ $row['segmentIndex'] }}),
                                                'bg-emerald-100 dark:bg-emerald-500/10': !isInDragPreview({{ $row['segmentIndex'] }}),
                                                'border-t-2 border-t-blue-400': dragging && dragCurrentStart === {{ $row['segmentIndex'] }},
                                                'border-b-2 border-b-blue-400': dragging && dragCurrentEnd === {{ $row['segmentIndex'] }},
                                            }"
                                        @else
                                            x-on:mouseenter="if (dragging) { onDragOver({{ $row['segmentIndex'] }}); } else { highlightStart = {{ $row['segmentIndex'] }}; highlightEnd = {{ $row['highlightEnd'] }}; }"
                                            x-on:click="if (!dragging) { $wire.createClip({{ $row['segmentIndex'] }}, {{ $row['highlightEnd'] }}) }"
                                            x-bind:class="{
                                                'bg-blue-100 dark:bg-blue-500/10': isInDragPreview({{ $row['segmentIndex'] }}),
                                                'bg-orange-100 dark:bg-orange-500/10': !dragging && highlightStart !== null && {{ $row['segmentIndex'] }} >= highlightStart && {{ $row['segmentIndex'] }} <= highlightEnd,
                                                'border-t-2 border-t-blue-400': dragging && dragCurrentStart === {{ $row['segmentIndex'] }},
                                                'border-b-2 border-b-blue-400': dragging && dragCurrentEnd === {{ $row['segmentIndex'] }},
                                            }"
                                        @endif
                                        class="{{ $row['inClip'] ? '' : 'cursor-pointer' }} relative transition duration-75"
                                    >
                                        <td class="whitespace-nowrap py-1 pe-3 ps-4 align-baseline text-xs text-end tabular-nums text-gray-500 sm:ps-6 dark:text-gray-400"
                                            style="font-variant-numeric: tabular-nums;"
                                        >
                                            {{ $this->formatTimestamp($row['start']) }}
                                        </td>
                                        <td class="relative w-full py-1 pe-4 align-baseline text-sm text-gray-950 sm:pe-6 dark:text-white">
                                            {{ $row['text'] }}

                                            @if ($row['inClip'] && $row['segmentIndex'] === $row['clipStart'])
                                                {{-- Static duration badge --}}
                                                <span
                                                    x-show="!dragging || dragClipId !== {{ $row['clipId'] }}"
                                                    class="absolute right-4 top-1 inline-flex items-center rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-medium text-emerald-700 sm:right-6 dark:text-emerald-400"
                                                >
                                                    {{ $this->clipDurations[$row['clipId']] ?? '' }}
                                                </span>
                                                {{-- Dynamic duration badge during drag --}}
                                                <span
                                                    x-show="dragging && dragClipId === {{ $row['clipId'] }}"
                                                    x-text="dragDuration !== null ? formatDuration(dragDuration) : ''"
                                                    x-cloak
                                                    class="absolute right-4 top-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium sm:right-6"
                                                    x-bind:class="dragDuration > 90 ? 'bg-red-500/15 text-red-700 dark:text-red-400' : 'bg-blue-500/15 text-blue-700 dark:text-blue-400'"
                                                ></span>
                                            @endif

                                            @if ($row['inClip'] && $row['segmentIndex'] === $row['clipStart'])
                                                {{-- Top drag handle --}}
                                                <div
                                                    class="group/handle absolute inset-x-0 -top-1 z-10 flex h-3 cursor-row-resize items-center justify-center"
                                                    x-on:mousedown.prevent="startDrag({{ $row['clipId'] }}, 'start', {{ $row['clipStart'] }}, {{ $row['clipEnd'] }})"
                                                >
                                                    <div class="h-0.5 w-12 rounded-full bg-emerald-500 opacity-0 transition group-hover/handle:opacity-100"></div>
                                                </div>
                                            @endif

                                            @if ($row['inClip'] && $row['segmentIndex'] === $row['clipEnd'])
                                                {{-- Bottom drag handle --}}
                                                <div
                                                    class="group/handle absolute inset-x-0 -bottom-1 z-10 flex h-3 cursor-row-resize items-center justify-center"
                                                    x-on:mousedown.prevent="startDrag({{ $row['clipId'] }}, 'end', {{ $row['clipStart'] }}, {{ $row['clipEnd'] }})"
                                                >
                                                    <div class="h-0.5 w-12 rounded-full bg-emerald-500 opacity-0 transition group-hover/handle:opacity-100"></div>
                                                </div>
                                            @endif
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

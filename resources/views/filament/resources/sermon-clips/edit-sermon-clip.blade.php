<x-filament-panels::page>
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="save"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    @php
        $clip = $this->getRecord();
        $clipSegments = $this->clipSegments;
    @endphp

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Video player --}}
        @if ($clip->clip_video_path)
            <div class="shrink-0">
                <video
                    controls
                    preload="metadata"
                    class="w-full max-w-xs rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10"
                    src="{{ Storage::disk('public')->url($clip->clip_video_path) }}"
                ></video>
            </div>
        @endif

        {{-- Readonly transcript --}}
        @if (count($clipSegments))
            <div
                x-data="readonlyTranscript({ segments: @js($clipSegments) })"
                class="flex min-w-0 flex-1 flex-col gap-4"
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

                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="overflow-x-auto py-3">
                        <table class="w-full">
                            <tbody>
                                <template x-for="(row, i) in rows" :key="i">
                                    <tr>
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
                                            <span x-text="row.text"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>

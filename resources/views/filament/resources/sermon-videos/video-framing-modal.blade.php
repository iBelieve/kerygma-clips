<div
    x-data="videoFraming({ cropCenter: @js($record->vertical_video_crop_center), videoId: @js($record->id) })"
    x-on:mousemove.window="onDrag($event)"
    x-on:mouseup.window="endDrag()"
    x-bind:class="{ 'select-none': dragging }"
    class="flex flex-col gap-4"
>
    {{-- Frame with crop overlay --}}
    <div
        x-ref="container"
        class="relative mx-auto w-full overflow-hidden rounded-lg"
    >
        <img
            x-ref="frame"
            src="{{ Storage::disk('public')->url($record->preview_frame_path) }}"
            alt="Video frame preview"
            class="block w-full"
            draggable="false"
        />

        {{-- Dark overlay left --}}
        <div
            class="absolute inset-y-0 left-0 bg-black/60 transition-all"
            x-bind:style="`width: ${boxLeftPct}%`"
        ></div>

        {{-- Dark overlay right --}}
        <div
            class="absolute inset-y-0 right-0 bg-black/60 transition-all"
            x-bind:style="`width: ${100 - boxLeftPct - boxWidthPct}%`"
        ></div>

        {{-- Crop box (draggable) --}}
        <div
            class="absolute inset-y-0 cursor-ew-resize border-x-2 border-white/80 transition-all"
            x-bind:style="`left: ${boxLeftPct}%; width: ${boxWidthPct}%`"
            x-on:mousedown="startDrag($event)"
        >
            {{-- Left edge indicator --}}
            <div class="absolute inset-y-0 left-0 w-1 bg-white/40"></div>

            {{-- Right edge indicator --}}
            <div class="absolute inset-y-0 right-0 w-1 bg-white/40"></div>

            {{-- Center line --}}
            <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-white/30"></div>
        </div>
    </div>

    {{-- Controls --}}
    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500 dark:text-gray-400">
            Crop center: <span x-text="cropCenter" class="tabular-nums font-medium text-gray-950 dark:text-white"></span>%
        </span>

        <x-filament::button
            size="sm"
            x-on:click="save()"
            x-bind:disabled="!changed || saving"
        >
            <span x-show="!saving">Save &amp; Re-convert</span>
            <span x-show="saving" x-cloak>Saving&hellip;</span>
        </x-filament::button>
    </div>
</div>

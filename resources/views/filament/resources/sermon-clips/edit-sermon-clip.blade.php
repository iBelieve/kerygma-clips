<x-filament-panels::page>
    {{ $this->content }}

    @php
        $clip = $this->getRecord();
    @endphp

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        {{-- Video player --}}
        @if ($clip->clip_video_path)
            <div class="shrink-0 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <video
                    controls
                    class="w-full max-w-xs"
                    src="{{ Storage::disk('public')->url($clip->clip_video_path) }}"
                ></video>
            </div>
        @endif

        {{-- Readonly transcript --}}
        @if (count($this->transcriptRows))
            <div class="min-w-0 flex-1">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="overflow-x-auto py-3">
                        <table class="w-full">
                            <tbody>
                                @foreach ($this->transcriptRows as $row)
                                    <tr>
                                        @if ($row['type'] === 'gap')
                                            <td colspan="2" class="px-4 sm:px-6">
                                                <div class="flex items-center gap-3 py-2">
                                                    <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                                    <span class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">
                                                        {{ $row['label'] }}
                                                    </span>
                                                    <div class="flex-1 border-t border-dashed border-gray-300 dark:border-gray-600"></div>
                                                </div>
                                            </td>
                                        @else
                                            <td class="whitespace-nowrap py-1 pe-3 ps-4 align-baseline text-end text-xs tabular-nums text-gray-500 sm:ps-6 dark:text-gray-400">
                                                {{ $row['timestamp'] }}
                                            </td>
                                            <td class="w-full py-1 pe-4 align-baseline text-sm text-gray-950 sm:pe-6 dark:text-white">
                                                {{ $row['text'] }}
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>

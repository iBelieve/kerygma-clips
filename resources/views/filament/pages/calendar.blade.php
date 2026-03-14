<x-filament-panels::page>
    <div x-data="calendarPage()" class="flex gap-6 items-start">
        {{-- Left Sidebar: Unscheduled Clips --}}
        <div class="w-72 shrink-0">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">
                Unscheduled Clips ({{ $this->unscheduledClips->count() }})
            </h3>
            <div class="space-y-1 max-h-[calc(100vh-12rem)] overflow-y-auto pr-1"
                 x-on:dragover.prevent="onDragOver($event)"
                 x-on:dragleave="onDragLeave($event)"
                 x-on:drop="onDropToUnschedule($event)">
                @forelse ($this->unscheduledClips as $clip)
                    <div draggable="true"
                         x-on:dragstart="onDragStart($event, {{ $clip->id }})"
                         x-on:dragend="onDragEnd($event)"
                         class="cursor-grab rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm transition hover:shadow dark:border-gray-700 dark:bg-gray-800">
                        <div class="font-medium text-gray-900 dark:text-white truncate">
                            {{ $clip->title ?: 'Untitled' }}
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $clip->video->title }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-400 dark:text-gray-500 italic py-4 text-center">
                        No unscheduled clips
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Main Area: Calendar --}}
        <div class="flex-1 min-w-0">
            {{-- Month navigation --}}
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <x-filament::icon-button
                        icon="heroicon-o-chevron-left"
                        wire:click="previousMonth"
                        label="Previous month"
                    />
                    <x-filament::icon-button
                        icon="heroicon-o-chevron-right"
                        wire:click="nextMonth"
                        label="Next month"
                    />
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white ml-2">
                        {{ $this->monthLabel }}
                    </h2>
                </div>
                <x-filament::button
                    wire:click="goToToday"
                    color="gray"
                    size="sm"
                >
                    Today
                </x-filament::button>
            </div>

            {{-- Day-of-week headers --}}
            <div class="grid grid-cols-7 text-center text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                <div>Sun</div>
                <div>Mon</div>
                <div>Tue</div>
                <div>Wed</div>
                <div>Thu</div>
                <div>Fri</div>
                <div>Sat</div>
            </div>

            {{-- Calendar grid --}}
            <div class="grid grid-cols-7 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                @foreach ($this->calendarDays as $day)
                    <div x-on:dragover.prevent="onDragOver($event, '{{ $day['date'] }}')"
                         x-on:drop="onDrop($event, '{{ $day['date'] }}')"
                         x-on:dragleave="onDragLeave($event)"
                         x-bind:class="hoveredDate === '{{ $day['date'] }}' && '!bg-amber-50 dark:!bg-amber-900/20'"
                         @class([
                             'min-h-24 border-b border-r border-gray-200 dark:border-gray-700 p-1.5 transition-colors',
                             'bg-white dark:bg-gray-900' => $day['isCurrentMonth'],
                             'bg-gray-50 dark:bg-gray-900/50' => ! $day['isCurrentMonth'],
                         ])>
                        <div @class([
                            'flex items-baseline gap-1 mb-2',
                            'opacity-50' => $day['isPast'],
                        ])>
                            <span @class([
                                'text-xs',
                                'font-bold text-amber-600 dark:text-amber-400' => $day['isToday'],
                                'text-gray-900 dark:text-gray-100' => $day['isCurrentMonth'] && ! $day['isToday'],
                                'text-gray-400 dark:text-gray-600' => ! $day['isCurrentMonth'],
                            ])>
                                {{ $day['dayNumber'] }}
                            </span>
                            @if ($day['lectionaryName'])
                                <span class="text-[10px] leading-tight font-medium truncate"
                                      @if ($day['lectionaryColor'])
                                          style="color: {{ $day['lectionaryColor'] }}"
                                      @endif
                                >
                                    {{ $day['lectionaryName'] }}
                                </span>
                            @endif
                        </div>
                        <div class="space-y-0.5">
                            @foreach ($day['clips'] as $clip)
                                <div draggable="true"
                                     x-on:dragstart="onDragStart($event, {{ $clip->id }})"
                                     x-on:dragend="onDragEnd($event)"
                                     @class([
                                         'group cursor-grab rounded px-1.5 py-0.5 text-xs ring-1 relative',
                                         'bg-blue-50 text-blue-900 ring-blue-200 dark:bg-blue-900/30 dark:text-blue-200 dark:ring-blue-700' => $day['isPast'],
                                         'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-200 dark:ring-amber-700' => ! $day['isPast'],
                                     ])>
                                    <span class="line-clamp-3">{{ $clip->title ?: 'Untitled' }}</span>
                                    <button x-on:click="unschedule({{ $clip->id }})"
                                            @class([
                                                'absolute top-0.5 right-0.5 opacity-0 group-hover:opacity-100 shrink-0 hover:text-red-500 dark:hover:text-red-400 transition-opacity',
                                                'text-blue-400 dark:text-blue-600' => $day['isPast'],
                                                'text-amber-400 dark:text-amber-600' => ! $day['isPast'],
                                            ])
                                            title="Unschedule">
                                        <x-heroicon-m-x-mark class="w-3 h-3" />
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>

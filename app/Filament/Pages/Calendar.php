<?php

namespace App\Filament\Pages;

use App\Models\VideoClip;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class Calendar extends Page
{
    protected static ?string $navigationLabel = 'Calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Calendar';

    protected string $view = 'filament.pages.calendar';

    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function previousMonth(): void
    {
        $date = CarbonImmutable::create($this->year, $this->month, 1)->subMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function nextMonth(): void
    {
        $date = CarbonImmutable::create($this->year, $this->month, 1)->addMonth();
        $this->year = $date->year;
        $this->month = $date->month;
    }

    public function goToToday(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    public function scheduleClip(int $clipId, string $date): void
    {
        VideoClip::where('id', $clipId)->update(['scheduled_date' => $date]);
    }

    public function unscheduleClip(int $clipId): void
    {
        VideoClip::where('id', $clipId)->update(['scheduled_date' => null]);
    }

    /**
     * @return Collection<int, VideoClip>
     */
    public function getUnscheduledClipsProperty(): Collection
    {
        return VideoClip::query()
            ->whereNull('scheduled_date')
            ->with('video')
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, VideoClip>
     */
    public function getScheduledClipsProperty(): Collection
    {
        $start = CarbonImmutable::create($this->year, $this->month, 1);

        $gridStart = $start->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $start->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        return VideoClip::query()
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->with('video')
            ->get();
    }

    /**
     * @return list<array{date: string, dayNumber: int, isCurrentMonth: bool, isToday: bool, clips: Collection<int, VideoClip>}>
     */
    public function getCalendarDaysProperty(): array
    {
        $start = CarbonImmutable::create($this->year, $this->month, 1);
        $end = $start->endOfMonth();

        $gridStart = $start->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $end->endOfWeek(Carbon::SATURDAY);

        $scheduledClips = $this->scheduledClips->groupBy(
            fn (VideoClip $clip): string => $clip->scheduled_date->toDateString()
        );

        $days = [];
        $current = $gridStart;

        while ($current <= $gridEnd) {
            $dateString = $current->toDateString();
            $days[] = [
                'date' => $dateString,
                'dayNumber' => $current->day,
                'isCurrentMonth' => $current->month === $this->month,
                'isToday' => $current->isToday(),
                'clips' => $scheduledClips->get($dateString, collect()),
            ];
            $current = $current->addDay();
        }

        return $days;
    }

    public function getMonthLabelProperty(): string
    {
        return CarbonImmutable::create($this->year, $this->month, 1)->format('F Y');
    }
}

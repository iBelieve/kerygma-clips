<?php

namespace App\Services;

class SubtitleGenerator
{
    private const MAX_WORDS_PER_GROUP = 4;

    /**
     * Generate ASS subtitle content from transcript word-level timing data.
     *
     * @param  array<string, mixed>  $transcript  The WhisperX transcript (with segments and word-level timing)
     * @param  float  $startsAt  The clip start time in the original video (used to offset all timings)
     * @return string|null The ASS file content, or null if no word-level timing data is available
     */
    public function generateAssContent(array $transcript, int $startSegmentIndex, int $endSegmentIndex, float $startsAt): ?string
    {
        $words = $this->extractWords($transcript, $startSegmentIndex, $endSegmentIndex, $startsAt);

        if ($words === []) {
            return null;
        }

        $phrases = $this->groupWordsIntoPhrases($words);

        return $this->buildAssFile($phrases);
    }

    /**
     * Extract words from the relevant transcript segments and offset their timings.
     *
     * @param  array<string, mixed>  $transcript
     * @return list<array{word: string, start: float, end: float}>
     */
    private function extractWords(array $transcript, int $startSegmentIndex, int $endSegmentIndex, float $startsAt): array
    {
        $segments = $transcript['segments'] ?? [];
        $words = [];

        for ($i = $startSegmentIndex; $i <= $endSegmentIndex; $i++) {
            $segment = $segments[$i] ?? null;

            if ($segment === null || ! isset($segment['words'])) {
                continue;
            }

            foreach ($segment['words'] as $word) {
                if (! isset($word['word'], $word['start'], $word['end'])) {
                    continue;
                }

                $words[] = [
                    'word' => $word['word'],
                    'start' => max(0.0, (float) $word['start'] - $startsAt),
                    'end' => max(0.0, (float) $word['end'] - $startsAt),
                ];
            }
        }

        return $words;
    }

    /**
     * Group words into short phrases for display.
     *
     * @param  list<array{word: string, start: float, end: float}>  $words
     * @return list<array{text: string, start: float, end: float}>
     */
    private function groupWordsIntoPhrases(array $words): array
    {
        $phrases = [];
        $chunk = [];

        foreach ($words as $word) {
            $chunk[] = $word;

            if (count($chunk) >= self::MAX_WORDS_PER_GROUP) {
                $phrases[] = $this->buildPhrase($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $phrases[] = $this->buildPhrase($chunk);
        }

        return $phrases;
    }

    /**
     * @param  list<array{word: string, start: float, end: float}>  $chunk
     * @return array{text: string, start: float, end: float}
     */
    private function buildPhrase(array $chunk): array
    {
        $text = implode(' ', array_map(fn (array $w): string => $w['word'], $chunk));

        return [
            'text' => mb_strtoupper($text),
            'start' => $chunk[0]['start'],
            'end' => $chunk[count($chunk) - 1]['end'],
        ];
    }

    /**
     * @param  list<array{text: string, start: float, end: float}>  $phrases
     */
    private function buildAssFile(array $phrases): string
    {
        $header = <<<'ASS'
            [Script Info]
            ScriptType: v4.00+
            PlayResX: 1080
            PlayResY: 1920
            WrapStyle: 0
            ScaledBorderAndShadow: yes

            [V4+ Styles]
            Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
            Style: Default,Montserrat,72,&H00FFFFFF,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,4,1.5,2,60,60,250,1

            [Events]
            Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
            ASS;

        // Remove the leading indentation from the heredoc
        $header = implode("\n", array_map('ltrim', explode("\n", $header)));

        $dialogueLines = [];
        foreach ($phrases as $phrase) {
            $start = $this->formatAssTime($phrase['start']);
            $end = $this->formatAssTime($phrase['end']);
            $dialogueLines[] = "Dialogue: 0,{$start},{$end},Default,,0,0,0,,{$phrase['text']}";
        }

        return $header.implode("\n", $dialogueLines)."\n";
    }

    private function formatAssTime(float $seconds): string
    {
        $totalSeconds = (int) floor($seconds);
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $secs = $totalSeconds % 60;
        $centiseconds = (int) round(($seconds - $totalSeconds) * 100);

        if ($centiseconds >= 100) {
            $centiseconds = 0;
            $secs++;
            if ($secs >= 60) {
                $secs = 0;
                $minutes++;
                if ($minutes >= 60) {
                    $minutes = 0;
                    $hours++;
                }
            }
        }

        return sprintf('%d:%02d:%02d.%02d', $hours, $minutes, $secs, $centiseconds);
    }
}

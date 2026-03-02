<?php

namespace App\Services;

class CaptionGenerator
{
    private const FONT_SIZE = 120;

    private const OUTLINE_SIZE = 10;

    private const MARGIN_VERTICAL = 350;

    private const MARGIN_HORIZONTAL = 80;

    private const MAX_WORDS_PER_PHRASE = 10;

    private const TARGET_WORDS_PER_PHRASE = 6;

    private const MIN_WORDS_FOR_COMMA_BREAK = 3;

    private const SILENCE_GAP_THRESHOLD = 0.7;

    /** Active word text colour (ASS BGR format — yellow/amber). */
    private const ACTIVE_WORD_COLOUR = '&H0000E5FF&';

    /** Default text colour (ASS BGR format — black). */
    private const DEFAULT_TEXT_COLOUR = '&H00000000&';

    /**
     * Generate ASS subtitle content from transcript segments.
     *
     * @param  array<int, array{start: float, end: float, text: string, words?: array<int, array{word: string, start?: float, end?: float, score?: float}>}>  $segments
     * @param  float  $clipStartTime  Absolute start time of the clip in the source video (seconds).
     * @param  float  $clipEndTime  Absolute end time of the clip in the source video (seconds).
     * @return string The complete ASS subtitle file content.
     */
    public function generateAss(array $segments, float $clipStartTime, float $clipEndTime): string
    {
        $phrases = $this->buildPhrases($segments, $clipStartTime, $clipEndTime);

        $header = <<<'ASS'
            [Script Info]
            Title: Sermon Clip Captions
            ScriptType: v4.00+
            PlayResX: 1080
            PlayResY: 1920
            WrapStyle: 0

            ASS;

        $marginH = self::MARGIN_HORIZONTAL;
        $marginV = self::MARGIN_VERTICAL;
        $fontSize = self::FONT_SIZE;
        $outline = self::OUTLINE_SIZE;

        $styles = <<<ASS
            [V4+ Styles]
            Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
            Style: Default,Montserrat,{$fontSize},&H00000000,&H000000FF,&H00FFFFFF,&H00000000,-1,0,0,0,100,100,0,0,1,{$outline},0,2,{$marginH},{$marginH},{$marginV},1

            ASS;

        $events = "[Events]\n";
        $events .= "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";

        foreach ($phrases as $phrase) {
            $phraseWords = $phrase['words'];

            foreach ($phraseWords as $activeIndex => $activeWord) {
                $start = $this->formatAssTimestamp($activeWord['start']);

                // This word's event lasts until the next word starts, or phrase ends
                $endTime = isset($phraseWords[$activeIndex + 1])
                    ? $phraseWords[$activeIndex + 1]['start']
                    : $phrase['end'];
                $end = $this->formatAssTimestamp($endTime);

                $text = $this->buildHighlightedText($phraseWords, $activeIndex);
                $events .= "Dialogue: 0,{$start},{$end},Default,,0,0,0,,{$text}\n";
            }
        }

        // Dedent the heredoc sections (they are indented for readability)
        $header = implode("\n", array_map('ltrim', explode("\n", $header)));
        $styles = implode("\n", array_map('ltrim', explode("\n", $styles)));

        return $header.$styles.$events;
    }

    /**
     * Convert float seconds to ASS timestamp format (H:MM:SS.cc).
     */
    private function formatAssTimestamp(float $seconds): string
    {
        $seconds = max(0.0, $seconds);
        $h = (int) floor($seconds / 3600);
        $m = (int) floor(fmod($seconds, 3600) / 60);
        $s = (int) floor(fmod($seconds, 60));
        $cs = (int) round(fmod($seconds, 1) * 100);

        if ($cs >= 100) {
            $cs = 0;
            $s++;
            if ($s >= 60) {
                $s = 0;
                $m++;
            }
            if ($m >= 60) {
                $m = 0;
                $h++;
            }
        }

        return sprintf('%d:%02d:%02d.%02d', $h, $m, $s, $cs);
    }

    /**
     * Build phrase text with ASS override tags highlighting the active word's text colour.
     *
     * @param  array<int, array{word: string, start: float, end: float}>  $words
     */
    private function buildHighlightedText(array $words, int $activeIndex): string
    {
        $parts = [];

        foreach ($words as $i => $word) {
            $escaped = $this->escapeAssText($word['word']);

            if ($i === $activeIndex) {
                $parts[] = '{\1c'.self::ACTIVE_WORD_COLOUR.'}'.$escaped.'{\1c'.self::DEFAULT_TEXT_COLOUR.'}';
            } else {
                $parts[] = $escaped;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Escape special ASS characters in dialogue text.
     */
    private function escapeAssText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('{', '\\{', $text);
        $text = str_replace('}', '\\}', $text);

        return $text;
    }

    /**
     * Extract all words from the given segments and group them into short phrases.
     *
     * @param  array<int, array{start: float, end: float, text: string, words?: array<int, array{word: string, start?: float, end?: float, score?: float}>}>  $segments
     * @return array<int, array{text: string, start: float, end: float, words: array<int, array{word: string, start: float, end: float}>}>
     */
    private function buildPhrases(array $segments, float $clipStartTime, float $clipEndTime): array
    {
        $words = $this->flattenWords($segments);

        if ($words === []) {
            return [];
        }

        $clipDuration = $clipEndTime - $clipStartTime;
        $phrases = [];
        $currentWords = [];
        $phraseStart = null;
        $phraseEnd = null;

        foreach ($words as $i => $word) {
            $wordStart = $word['start'] - $clipStartTime;
            $wordEnd = $word['end'] - $clipStartTime;

            // Clamp to clip bounds
            $wordStart = max(0.0, min($wordStart, $clipDuration));
            $wordEnd = max(0.0, min($wordEnd, $clipDuration));

            if ($phraseStart === null) {
                $phraseStart = $wordStart;
            }
            $phraseEnd = $wordEnd;
            $currentWords[] = [
                'word' => $word['word'],
                'start' => $wordStart,
                'end' => $wordEnd,
            ];

            $wordCount = count($currentWords);
            $isLast = ($i === count($words) - 1);
            $shouldBreak = false;

            if (! $isLast) {
                $nextWordStart = $words[$i + 1]['start'] - $clipStartTime;

                // Break at sentence-ending punctuation
                if (preg_match('/[.!?]$/', $word['word'])) {
                    $shouldBreak = true;
                }

                // Break at comma/semicolon/colon if phrase has 3+ words
                if ($wordCount >= self::MIN_WORDS_FOR_COMMA_BREAK && preg_match('/[,;:]$/', $word['word'])) {
                    $shouldBreak = true;
                }

                // Break at target word count
                if ($wordCount >= self::TARGET_WORDS_PER_PHRASE) {
                    $shouldBreak = true;
                }

                // Break on silence gap
                if ($nextWordStart - $wordEnd > self::SILENCE_GAP_THRESHOLD) {
                    $shouldBreak = true;
                }

                // Hard cap
                if ($wordCount >= self::MAX_WORDS_PER_PHRASE) {
                    $shouldBreak = true;
                }
            }

            if ($shouldBreak || $isLast) {
                $phrases[] = [
                    'text' => implode(' ', array_column($currentWords, 'word')),
                    'start' => $phraseStart,
                    'end' => $phraseEnd,
                    'words' => $currentWords,
                ];
                $currentWords = [];
                $phraseStart = null;
                $phraseEnd = null;
            }
        }

        return $phrases;
    }

    /**
     * Flatten words from all segments into a single list with resolved timestamps.
     *
     * @param  array<int, array{start: float, end: float, text: string, words?: array<int, array{word: string, start?: float, end?: float, score?: float}>}>  $segments
     * @return array<int, array{word: string, start: float, end: float}>
     */
    private function flattenWords(array $segments): array
    {
        $allWords = [];

        foreach ($segments as $segment) {
            $segStart = (float) $segment['start'];
            $segEnd = (float) $segment['end'];

            if (isset($segment['words']) && $segment['words'] !== []) {
                $segmentWords = array_values($segment['words']);
                $wordCount = count($segmentWords);

                foreach ($segmentWords as $j => $word) {
                    $wordText = trim($word['word']);
                    if ($wordText === '') {
                        continue;
                    }

                    $start = isset($word['start']) ? (float) $word['start'] : null;
                    $end = isset($word['end']) ? (float) $word['end'] : null;

                    // Resolve missing start timestamp
                    if ($start === null) {
                        if ($allWords !== []) {
                            $start = end($allWords)['end'];
                        } else {
                            $start = $segStart;
                        }
                    }

                    // Resolve missing end timestamp
                    if ($end === null) {
                        if ($j + 1 < $wordCount && isset($segmentWords[$j + 1]['start'])) {
                            $end = (float) $segmentWords[$j + 1]['start'];
                        } else {
                            $end = $segEnd;
                        }
                    }

                    $allWords[] = [
                        'word' => $wordText,
                        'start' => $start,
                        'end' => $end,
                    ];
                }
            } else {
                // Fallback: segment has text but no words array
                $text = trim($segment['text']);
                if ($text === '') {
                    continue;
                }

                $textWords = preg_split('/\s+/', $text);
                if ($textWords === false) {
                    continue;
                }

                $segDuration = $segEnd - $segStart;
                $count = count($textWords);

                foreach ($textWords as $j => $w) {
                    $wordStart = $segStart + ($segDuration * $j / $count);
                    $wordEnd = $segStart + ($segDuration * ($j + 1) / $count);

                    $allWords[] = [
                        'word' => $w,
                        'start' => $wordStart,
                        'end' => $wordEnd,
                    ];
                }
            }
        }

        return $allWords;
    }
}

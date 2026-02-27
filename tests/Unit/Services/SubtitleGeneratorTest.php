<?php

use App\Services\SubtitleGenerator;

function subtitleTranscript(array $segments): array
{
    return ['segments' => $segments, 'language' => 'en'];
}

function subtitleSegment(float $start, float $end, string $text, array $words): array
{
    return compact('start', 'end', 'text', 'words');
}

function subtitleWord(string $word, float $start, float $end): array
{
    return ['word' => $word, 'start' => $start, 'end' => $end, 'score' => 0.9];
}

test('generates ASS content with correct header', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(10.0, 12.0, 'Hello world', [
            subtitleWord('Hello', 10.0, 10.5),
            subtitleWord('world', 10.6, 11.0),
        ]),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 10.0);

    expect($result)
        ->toContain('[Script Info]')
        ->toContain('PlayResX: 1080')
        ->toContain('PlayResY: 1920')
        ->toContain('ScaledBorderAndShadow: yes')
        ->toContain('[V4+ Styles]')
        ->toContain('Montserrat')
        ->toContain('[Events]');
});

test('offsets word timings by startsAt', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(10.0, 12.0, 'Hello world', [
            subtitleWord('Hello', 10.0, 10.5),
            subtitleWord('world', 10.6, 11.0),
        ]),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 10.0);

    expect($result)
        ->toContain('0:00:00.00')
        ->not->toContain('0:00:10.00');
});

test('groups words into phrases of 4', function () {
    $words = [];
    for ($i = 0; $i < 12; $i++) {
        $words[] = subtitleWord("word{$i}", $i * 1.0, $i * 1.0 + 0.8);
    }

    $transcript = subtitleTranscript([
        subtitleSegment(0.0, 12.0, 'text', $words),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 0.0);

    // 12 words / 4 per group = 3 dialogue lines
    expect(substr_count($result, 'Dialogue:'))->toBe(3);
});

test('handles last group with fewer words', function () {
    $words = [];
    for ($i = 0; $i < 10; $i++) {
        $words[] = subtitleWord("word{$i}", $i * 1.0, $i * 1.0 + 0.8);
    }

    $transcript = subtitleTranscript([
        subtitleSegment(0.0, 10.0, 'text', $words),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 0.0);

    // 10 words: 4 + 4 + 2 = 3 dialogue lines
    expect(substr_count($result, 'Dialogue:'))->toBe(3);
});

test('uppercases text', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(0.0, 2.0, 'grace and mercy', [
            subtitleWord('grace', 0.0, 0.5),
            subtitleWord('and', 0.6, 0.8),
            subtitleWord('mercy', 0.9, 1.5),
        ]),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 0.0);

    expect($result)->toContain('GRACE AND MERCY');
});

test('formats ASS time correctly', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(0.0, 70.0, 'text', [
            subtitleWord('a', 0.0, 0.5),
            subtitleWord('b', 1.0, 1.5),
            subtitleWord('c', 2.0, 2.5),
            subtitleWord('d', 3.0, 3.5),
            subtitleWord('e', 65.5, 66.0),
            subtitleWord('f', 67.0, 67.5),
            subtitleWord('g', 68.0, 68.5),
            subtitleWord('h', 69.0, 69.5),
        ]),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 0.0);

    // First group: a-d, starts at 0.0, ends at 3.5
    // Second group: e-h, starts at 65.5, ends at 69.5
    expect($result)
        ->toContain('0:00:00.00,0:00:03.50')
        ->toContain('0:01:05.50,0:01:09.50');
});

test('returns null when no word data is available', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(0.0, 5.0, 'Segment without words', []),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 0.0);

    expect($result)->toBeNull();
});

test('returns null when segments have no words key', function () {
    $transcript = subtitleTranscript([
        ['start' => 0.0, 'end' => 5.0, 'text' => 'No words key'],
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 0.0);

    expect($result)->toBeNull();
});

test('clamps negative offset times to zero', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(9.8, 12.0, 'text', [
            subtitleWord('hello', 9.8, 10.0),
            subtitleWord('world', 10.1, 10.5),
        ]),
    ]);

    $generator = new SubtitleGenerator;
    // startsAt = 10.0, so first word at 9.8 would be -0.2, should be clamped to 0
    $result = $generator->generateAssContent($transcript, 0, 0, 10.0);

    // First word start (9.8 - 10.0 = -0.2) should be clamped to 0:00:00.00
    // Second word end (10.5 - 10.0 = 0.5) gives 0:00:00.50
    expect($result)->toContain('Dialogue: 0,0:00:00.00,0:00:00.50,');
});

test('skips words missing start or end timestamps', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(0.0, 5.0, 'text', [
            subtitleWord('good', 0.0, 0.5),
            ['word' => 'missing_start', 'end' => 1.0, 'score' => 0.9],
            ['word' => 'missing_end', 'start' => 1.5, 'score' => 0.9],
            subtitleWord('also_good', 2.0, 2.5),
        ]),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 0, 0.0);

    expect($result)
        ->toContain('GOOD')
        ->toContain('ALSO_GOOD')
        ->not->toContain('MISSING');
});

test('extracts words across multiple segments', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(0.0, 2.0, 'segment one', [
            subtitleWord('segment', 0.0, 0.5),
            subtitleWord('one', 0.6, 1.0),
        ]),
        subtitleSegment(2.0, 4.0, 'segment two', [
            subtitleWord('segment', 2.0, 2.5),
            subtitleWord('two', 2.6, 3.0),
        ]),
        subtitleSegment(4.0, 6.0, 'segment three', [
            subtitleWord('segment', 4.0, 4.5),
            subtitleWord('three', 4.6, 5.0),
        ]),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 0, 2, 0.0);

    expect($result)
        ->toContain('SEGMENT ONE SEGMENT TWO')
        ->toContain('SEGMENT THREE');
    expect(substr_count($result, 'Dialogue:'))->toBe(2);
});

test('only uses segments within the specified range', function () {
    $transcript = subtitleTranscript([
        subtitleSegment(0.0, 2.0, 'before', [
            subtitleWord('before', 0.0, 0.5),
        ]),
        subtitleSegment(2.0, 4.0, 'inside', [
            subtitleWord('inside', 2.0, 2.5),
        ]),
        subtitleSegment(4.0, 6.0, 'after', [
            subtitleWord('after', 4.0, 4.5),
        ]),
    ]);

    $generator = new SubtitleGenerator;
    $result = $generator->generateAssContent($transcript, 1, 1, 2.0);

    expect($result)
        ->toContain('INSIDE')
        ->not->toContain('BEFORE')
        ->not->toContain('AFTER');
});

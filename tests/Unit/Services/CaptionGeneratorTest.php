<?php

use App\Services\CaptionGenerator;

beforeEach(function () {
    $this->generator = new CaptionGenerator;
});

/**
 * Extract all Dialogue lines from ASS output.
 *
 * @return array<int, string>
 */
function dialogueLines(string $ass): array
{
    preg_match_all('/^Dialogue: .+$/m', $ass, $matches);

    return $matches[0];
}

/**
 * Strip ASS override tags from text, returning plain words.
 */
function stripOverrides(string $text): string
{
    return preg_replace('/\{[^}]*\}/', '', $text);
}

/**
 * Extract the text portion from a Dialogue line (everything after the 9th comma).
 */
function dialogueText(string $line): string
{
    // Dialogue: Layer,Start,End,Style,Name,MarginL,MarginR,MarginV,Effect,Text
    $parts = explode(',', $line, 10);

    return $parts[9] ?? '';
}

test('it generates valid ASS structure with header, styles, and events', function () {
    $segments = [
        [
            'start' => 10.0,
            'end' => 15.0,
            'text' => 'Hello world',
            'words' => [
                ['word' => 'Hello', 'start' => 10.0, 'end' => 10.5],
                ['word' => 'world', 'start' => 10.6, 'end' => 11.0],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 10.0, 15.0);

    expect($result)
        ->toContain('[Script Info]')
        ->toContain('PlayResX: 1080')
        ->toContain('PlayResY: 1920')
        ->toContain('[V4+ Styles]')
        ->toContain('Style: Default,Montserrat,')
        ->toContain('[Events]')
        ->toContain('Dialogue: 0,');
});

test('it emits one dialogue event per word with active word highlighted', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 3.0,
            'text' => 'Grace and mercy',
            'words' => [
                ['word' => 'Grace', 'start' => 0.0, 'end' => 0.5],
                ['word' => 'and', 'start' => 0.6, 'end' => 0.8],
                ['word' => 'mercy', 'start' => 0.9, 'end' => 1.5],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 3.0);
    $lines = dialogueLines($result);

    // One dialogue event per word
    expect($lines)->toHaveCount(3);

    // First event: "Grace" is active (yellow outline), others are plain
    expect($lines[0])
        ->toContain('{\3c&H0000E5FF&}Grace{\3c&H00FFFFFF&}')
        ->toContain(' and mercy');

    // Second event: "and" is active
    expect($lines[1])
        ->toContain('Grace {\3c&H0000E5FF&}and{\3c&H00FFFFFF&} mercy');

    // Third event: "mercy" is active
    expect($lines[2])
        ->toContain('Grace and {\3c&H0000E5FF&}mercy{\3c&H00FFFFFF&}');
});

test('it offsets timestamps relative to clip start', function () {
    $segments = [
        [
            'start' => 100.0,
            'end' => 105.0,
            'text' => 'Grace and mercy',
            'words' => [
                ['word' => 'Grace', 'start' => 100.0, 'end' => 100.5],
                ['word' => 'and', 'start' => 100.6, 'end' => 100.8],
                ['word' => 'mercy', 'start' => 100.9, 'end' => 101.5],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 100.0, 105.0);
    $lines = dialogueLines($result);

    // First word starts at 0:00:00.00 (100.0 - 100.0), ends at next word start 0:00:00.60
    expect($lines[0])->toContain('0:00:00.00,0:00:00.60');

    // Last word ends at phrase end 0:00:01.50
    expect($lines[2])->toContain('0:00:00.90,0:00:01.50');
});

test('it groups words into phrases with target word count', function () {
    $words = [];
    for ($i = 0; $i < 15; $i++) {
        $words[] = ['word' => "word{$i}", 'start' => $i * 0.5, 'end' => $i * 0.5 + 0.4];
    }

    $segments = [
        [
            'start' => 0.0,
            'end' => 8.0,
            'text' => implode(' ', array_column($words, 'word')),
            'words' => $words,
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 8.0);
    $lines = dialogueLines($result);

    // 15 words → one dialogue event per word
    expect($lines)->toHaveCount(15);

    // First phrase has 6 words (target=6), so first 6 events share the same word set
    $firstPhraseText = stripOverrides(dialogueText($lines[0]));
    expect($firstPhraseText)->toBe('word0 word1 word2 word3 word4 word5');

    // Second phrase starts at word6
    $secondPhraseText = stripOverrides(dialogueText($lines[6]));
    expect($secondPhraseText)->toBe('word6 word7 word8 word9 word10 word11');

    // Third phrase: remaining 3 words
    $thirdPhraseText = stripOverrides(dialogueText($lines[12]));
    expect($thirdPhraseText)->toBe('word12 word13 word14');
});

test('it breaks phrases at sentence-ending punctuation', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 5.0,
            'text' => 'Hello world. This is a test.',
            'words' => [
                ['word' => 'Hello', 'start' => 0.0, 'end' => 0.4],
                ['word' => 'world.', 'start' => 0.5, 'end' => 1.0],
                ['word' => 'This', 'start' => 1.1, 'end' => 1.4],
                ['word' => 'is', 'start' => 1.5, 'end' => 1.7],
                ['word' => 'a', 'start' => 1.8, 'end' => 1.9],
                ['word' => 'test.', 'start' => 2.0, 'end' => 2.5],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 5.0);
    $lines = dialogueLines($result);

    // 6 words → 6 events (2 in first phrase, 4 in second)
    expect($lines)->toHaveCount(6);

    // First phrase: "Hello world."
    $phrase1 = stripOverrides(dialogueText($lines[0]));
    expect($phrase1)->toBe('Hello world.');

    // Second phrase: "This is a test."
    $phrase2 = stripOverrides(dialogueText($lines[2]));
    expect($phrase2)->toBe('This is a test.');
});

test('it breaks phrases at comma with 3+ words accumulated', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 5.0,
            'text' => 'Grace and mercy, and peace are yours',
            'words' => [
                ['word' => 'Grace', 'start' => 0.0, 'end' => 0.4],
                ['word' => 'and', 'start' => 0.5, 'end' => 0.7],
                ['word' => 'mercy,', 'start' => 0.8, 'end' => 1.2],
                ['word' => 'and', 'start' => 1.3, 'end' => 1.5],
                ['word' => 'peace', 'start' => 1.6, 'end' => 1.9],
                ['word' => 'are', 'start' => 2.0, 'end' => 2.2],
                ['word' => 'yours', 'start' => 2.3, 'end' => 2.8],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 5.0);
    $lines = dialogueLines($result);

    // 7 words → 7 events (3 in first phrase, 4 in second)
    expect($lines)->toHaveCount(7);

    // First phrase breaks at comma: "Grace and mercy,"
    $phrase1 = stripOverrides(dialogueText($lines[0]));
    expect($phrase1)->toBe('Grace and mercy,');

    // Second phrase: "and peace are yours"
    $phrase2 = stripOverrides(dialogueText($lines[3]));
    expect($phrase2)->toBe('and peace are yours');
});

test('it does not break at comma with fewer than 3 words', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 3.0,
            'text' => 'Yes, indeed it is',
            'words' => [
                ['word' => 'Yes,', 'start' => 0.0, 'end' => 0.3],
                ['word' => 'indeed', 'start' => 0.4, 'end' => 0.8],
                ['word' => 'it', 'start' => 0.9, 'end' => 1.0],
                ['word' => 'is', 'start' => 1.1, 'end' => 1.3],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 3.0);
    $lines = dialogueLines($result);

    // "Yes," is only 1 word, no comma break; 4 words < target of 6 → single phrase
    expect($lines)->toHaveCount(4);

    $phrase = stripOverrides(dialogueText($lines[0]));
    expect($phrase)->toBe('Yes, indeed it is');
});

test('it handles words without start timestamps', function () {
    $segments = [
        [
            'start' => 5.0,
            'end' => 10.0,
            'text' => 'Hello world today',
            'words' => [
                ['word' => 'Hello', 'start' => 5.0, 'end' => 5.5],
                ['word' => 'world', 'end' => 6.5],  // missing start
                ['word' => 'today', 'start' => 7.0, 'end' => 7.5],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 5.0, 10.0);
    $lines = dialogueLines($result);

    expect($lines)->toHaveCount(3);

    $phrase = stripOverrides(dialogueText($lines[0]));
    expect($phrase)->toBe('Hello world today');
});

test('it handles words without end timestamps', function () {
    $segments = [
        [
            'start' => 5.0,
            'end' => 10.0,
            'text' => 'Hello world today',
            'words' => [
                ['word' => 'Hello', 'start' => 5.0, 'end' => 5.5],
                ['word' => 'world', 'start' => 6.0],  // missing end
                ['word' => 'today', 'start' => 7.0, 'end' => 7.5],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 5.0, 10.0);
    $lines = dialogueLines($result);

    expect($lines)->toHaveCount(3);

    $phrase = stripOverrides(dialogueText($lines[0]));
    expect($phrase)->toBe('Hello world today');
});

test('it handles segments with no words array', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 5.0,
            'text' => 'This is a fallback segment with no words',
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 5.0);

    expect($result)->toContain('Dialogue:');
    // 8 words → events with active word highlighting
    $lines = dialogueLines($result);
    expect($lines)->toHaveCount(8);
});

test('it handles empty segments array', function () {
    $result = $this->generator->generateAss([], 0.0, 5.0);

    expect($result)
        ->toContain('[Script Info]')
        ->toContain('[V4+ Styles]')
        ->toContain('[Events]');

    // No dialogue events
    expect($result)->not->toContain('Dialogue:');
});

test('it handles single-word segments', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 1.0,
            'text' => 'Amen',
            'words' => [
                ['word' => 'Amen', 'start' => 0.0, 'end' => 0.8],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 1.0);

    $lines = dialogueLines($result);
    expect($lines)->toHaveCount(1);
    expect($lines[0])->toContain('{\3c&H0000E5FF&}Amen{\3c&H00FFFFFF&}');
});

test('it caps phrases at maximum word count', function () {
    // 20 words with no punctuation and tight spacing (no silence gaps)
    $words = [];
    for ($i = 0; $i < 20; $i++) {
        $words[] = ['word' => "word{$i}", 'start' => $i * 0.3, 'end' => $i * 0.3 + 0.25];
    }

    $segments = [
        [
            'start' => 0.0,
            'end' => 6.25,
            'text' => implode(' ', array_column($words, 'word')),
            'words' => $words,
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 6.25);
    $lines = dialogueLines($result);

    // 20 words → 20 dialogue events total
    expect($lines)->toHaveCount(20);

    // With target=6, phrases are: 6, 6, 6, 2
    // Verify phrase boundaries by checking the word set in each phrase
    $phrase1 = stripOverrides(dialogueText($lines[0]));
    $phrase2 = stripOverrides(dialogueText($lines[6]));
    $phrase3 = stripOverrides(dialogueText($lines[12]));
    $phrase4 = stripOverrides(dialogueText($lines[18]));

    expect($phrase1)->toBe('word0 word1 word2 word3 word4 word5');
    expect($phrase2)->toBe('word6 word7 word8 word9 word10 word11');
    expect($phrase3)->toBe('word12 word13 word14 word15 word16 word17');
    expect($phrase4)->toBe('word18 word19');
});

test('it breaks phrases on silence gaps', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 5.0,
            'text' => 'Hello world long pause here',
            'words' => [
                ['word' => 'Hello', 'start' => 0.0, 'end' => 0.4],
                ['word' => 'world', 'start' => 0.5, 'end' => 1.0],
                // > 0.7s gap here
                ['word' => 'long', 'start' => 2.0, 'end' => 2.4],
                ['word' => 'pause', 'start' => 2.5, 'end' => 2.9],
                ['word' => 'here', 'start' => 3.0, 'end' => 3.4],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 5.0);
    $lines = dialogueLines($result);

    // 5 words → 5 events (2 in first phrase, 3 in second)
    expect($lines)->toHaveCount(5);

    $phrase1 = stripOverrides(dialogueText($lines[0]));
    expect($phrase1)->toBe('Hello world');

    $phrase2 = stripOverrides(dialogueText($lines[2]));
    expect($phrase2)->toBe('long pause here');
});

test('it clamps phrase timing to clip bounds', function () {
    $segments = [
        [
            'start' => 9.5,
            'end' => 11.0,
            'text' => 'Before and after',
            'words' => [
                ['word' => 'Before', 'start' => 9.5, 'end' => 9.9],
                ['word' => 'and', 'start' => 10.0, 'end' => 10.3],
                ['word' => 'after', 'start' => 10.4, 'end' => 11.0],
            ],
        ],
    ];

    // Clip starts at 10.0, so "Before" starts before the clip
    $result = $this->generator->generateAss($segments, 10.0, 11.0);

    // The first word start should be clamped to 0:00:00.00
    expect($result)->toContain('0:00:00.00');
});

test('it escapes special ASS characters in text', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 3.0,
            'text' => 'Test {override} and \\slash',
            'words' => [
                ['word' => 'Test', 'start' => 0.0, 'end' => 0.4],
                ['word' => '{override}', 'start' => 0.5, 'end' => 1.0],
                ['word' => 'and', 'start' => 1.1, 'end' => 1.3],
                ['word' => '\\slash', 'start' => 1.4, 'end' => 2.0],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 3.0);

    expect($result)
        ->toContain('\\{override\\}')
        ->toContain('\\\\slash');
});

test('it returns valid ASS with no dialogue when segments have empty text', function () {
    $segments = [
        [
            'start' => 0.0,
            'end' => 5.0,
            'text' => '',
            'words' => [],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 5.0);

    expect($result)
        ->toContain('[Script Info]')
        ->toContain('[Events]')
        ->not->toContain('Dialogue:');
});

test('it formats timestamps correctly', function () {
    // We test indirectly via a segment that starts at specific times
    $segments = [
        [
            'start' => 0.0,
            'end' => 3661.99,
            'text' => 'test',
            'words' => [
                ['word' => 'test', 'start' => 0.0, 'end' => 3661.99],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 0.0, 3662.0);

    // 3661.99 seconds = 1 hour, 1 minute, 1 second, 99 centiseconds
    expect($result)->toContain('1:01:01.99');
});

test('it handles multiple segments', function () {
    $segments = [
        [
            'start' => 10.0,
            'end' => 12.0,
            'text' => 'First segment words here',
            'words' => [
                ['word' => 'First', 'start' => 10.0, 'end' => 10.3],
                ['word' => 'segment', 'start' => 10.4, 'end' => 10.8],
                ['word' => 'words', 'start' => 10.9, 'end' => 11.2],
                ['word' => 'here', 'start' => 11.3, 'end' => 11.6],
            ],
        ],
        [
            'start' => 12.0,
            'end' => 14.0,
            'text' => 'Second segment now',
            'words' => [
                ['word' => 'Second', 'start' => 12.0, 'end' => 12.4],
                ['word' => 'segment', 'start' => 12.5, 'end' => 12.9],
                ['word' => 'now', 'start' => 13.0, 'end' => 13.3],
            ],
        ],
    ];

    $result = $this->generator->generateAss($segments, 10.0, 14.0);
    $lines = dialogueLines($result);

    // 7 total words < target of 6… wait, 7 > 6, so first phrase takes 6, second takes 1
    expect($lines)->toHaveCount(7);

    // First phrase: 6 words spanning both segments
    $phrase1 = stripOverrides(dialogueText($lines[0]));
    expect($phrase1)->toBe('First segment words here Second segment');

    // Second phrase: remaining word
    $phrase2 = stripOverrides(dialogueText($lines[6]));
    expect($phrase2)->toBe('now');
});

<?php

use App\Services\CaptionGenerator;

beforeEach(function () {
    $this->generator = new CaptionGenerator;
});

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

    // Timestamps should be relative to clip start (0:00:00.00 based)
    expect($result)
        ->toContain('Dialogue: 0,0:00:00.00,0:00:01.50,Default,,0,0,0,,Grace and mercy');
});

test('it groups words into phrases of approximately 6 words', function () {
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

    // Should produce 3 phrases: 6 + 6 + 3 words
    preg_match_all('/Dialogue:/', $result, $matches);
    expect(count($matches[0]))->toBe(3);
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

    // Should break at periods: "Hello world." then "This is a test."
    expect($result)
        ->toContain('Hello world.')
        ->toContain('This is a test.');

    preg_match_all('/Dialogue:/', $result, $matches);
    expect(count($matches[0]))->toBe(2);
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

    // Comma break after 3 words, remaining 4 words < target(6) → single phrase
    expect($result)
        ->toContain('Grace and mercy,')
        ->toContain('and peace are yours');

    preg_match_all('/Dialogue:/', $result, $matches);
    expect(count($matches[0]))->toBe(2);
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

    // "Yes," is only 1 word, so no break at comma — and 4 words < target(6), so all in one phrase
    preg_match_all('/Dialogue:/', $result, $matches);
    expect(count($matches[0]))->toBe(1);
    expect($result)
        ->toContain('Yes, indeed it is');
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

    // Should not throw, should produce a dialogue line
    expect($result)->toContain('Dialogue:');
    expect($result)->toContain('Hello world today');
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

    expect($result)->toContain('Dialogue:');
    expect($result)->toContain('Hello world today');
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
    expect($result)->toContain('This is a');
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

    preg_match_all('/Dialogue:/', $result, $matches);
    expect(count($matches[0]))->toBe(1);
    expect($result)->toContain('Amen');
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

    // With target of 6 words and hard cap of 10, 20 words should produce 4 phrases (3×6 + 1×2)
    preg_match_all('/Dialogue:/', $result, $matches);
    expect(count($matches[0]))->toBe(4);
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

    expect($result)
        ->toContain('Hello world')
        ->toContain('long pause here');

    preg_match_all('/Dialogue:/', $result, $matches);
    expect(count($matches[0]))->toBe(2);
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

    // The phrase start should be clamped to 0:00:00.00
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

    // Words from both segments should appear (grouped into phrases of ~6 words)
    // 7 total words => phrases of 6 + 1
    preg_match_all('/Dialogue:/', $result, $matches);
    expect(count($matches[0]))->toBe(2);
    expect($result)
        ->toContain('First segment words here Second segment')
        ->toContain('now');
});

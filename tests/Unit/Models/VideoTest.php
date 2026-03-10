<?php

use App\Models\Video;

test('getMidsentenceSubtitle returns null when subtitle is null', function () {
    $video = new Video;

    expect($video->getMidsentenceSubtitle())->toBeNull();
});

test('getMidsentenceSubtitle lowercases leading "The"', function () {
    $video = new Video(['subtitle' => 'The Lord\'s Supper']);

    expect($video->getMidsentenceSubtitle())->toBe('the Lord\'s Supper');
});

test('getMidsentenceSubtitle lowercases leading "A"', function () {
    $video = new Video(['subtitle' => 'A New Beginning']);

    expect($video->getMidsentenceSubtitle())->toBe('a New Beginning');
});

test('getMidsentenceSubtitle lowercases leading "An"', function () {
    $video = new Video(['subtitle' => 'An Ordinary Sunday']);

    expect($video->getMidsentenceSubtitle())->toBe('an Ordinary Sunday');
});

test('getMidsentenceSubtitle preserves subtitle not starting with a minor word', function () {
    $video = new Video(['subtitle' => 'Easter Sunday']);

    expect($video->getMidsentenceSubtitle())->toBe('Easter Sunday');
});

test('getMidsentenceSubtitle does not lowercase "The" in the middle', function () {
    $video = new Video(['subtitle' => 'Beyond The Veil']);

    expect($video->getMidsentenceSubtitle())->toBe('Beyond The Veil');
});

test('getMidsentenceSubtitle does not lowercase partial matches like "Anthem"', function () {
    $video = new Video(['subtitle' => 'Anthem of Grace']);

    expect($video->getMidsentenceSubtitle())->toBe('Anthem of Grace');
});

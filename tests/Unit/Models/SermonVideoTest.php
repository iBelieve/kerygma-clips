<?php

use App\Models\SermonVideo;

test('getMidsentenceSubtitle returns null when subtitle is null', function () {
    $video = new SermonVideo;

    expect($video->getMidsentenceSubtitle())->toBeNull();
});

test('getMidsentenceSubtitle lowercases leading "The"', function () {
    $video = new SermonVideo(['subtitle' => 'The Lord\'s Supper']);

    expect($video->getMidsentenceSubtitle())->toBe('the Lord\'s Supper');
});

test('getMidsentenceSubtitle lowercases leading "A"', function () {
    $video = new SermonVideo(['subtitle' => 'A New Beginning']);

    expect($video->getMidsentenceSubtitle())->toBe('a New Beginning');
});

test('getMidsentenceSubtitle lowercases leading "An"', function () {
    $video = new SermonVideo(['subtitle' => 'An Ordinary Sunday']);

    expect($video->getMidsentenceSubtitle())->toBe('an Ordinary Sunday');
});

test('getMidsentenceSubtitle preserves subtitle not starting with a minor word', function () {
    $video = new SermonVideo(['subtitle' => 'Easter Sunday']);

    expect($video->getMidsentenceSubtitle())->toBe('Easter Sunday');
});

test('getMidsentenceSubtitle does not lowercase "The" in the middle', function () {
    $video = new SermonVideo(['subtitle' => 'Beyond The Veil']);

    expect($video->getMidsentenceSubtitle())->toBe('Beyond The Veil');
});

test('getMidsentenceSubtitle does not lowercase partial matches like "Anthem"', function () {
    $video = new SermonVideo(['subtitle' => 'Anthem of Grace']);

    expect($video->getMidsentenceSubtitle())->toBe('Anthem of Grace');
});

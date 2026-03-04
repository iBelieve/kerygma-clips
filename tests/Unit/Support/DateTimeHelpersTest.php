<?php

use App\Support\DateTimeHelpers;
use Carbon\Carbon;

test('rounds down to the hour when minutes are less than 15', function () {
    $date = Carbon::parse('2025-12-10 18:07:30');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('H:i:s'))->toBe('18:00:00');
});

test('rounds to half hour when minutes are between 15 and 44', function () {
    $date = Carbon::parse('2025-12-10 18:23:45');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('H:i:s'))->toBe('18:30:00');
});

test('rounds up to next hour when minutes are 45 or greater', function () {
    $date = Carbon::parse('2025-12-10 18:53:50');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('H:i:s'))->toBe('19:00:00');
});

test('preserves exact hour times', function () {
    $date = Carbon::parse('2025-12-10 18:00:00');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('H:i:s'))->toBe('18:00:00');
});

test('preserves exact half hour times', function () {
    $date = Carbon::parse('2025-12-10 18:30:00');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('H:i:s'))->toBe('18:30:00');
});

test('rounds exactly 15 minutes to half hour', function () {
    $date = Carbon::parse('2025-12-10 18:15:00');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('H:i:s'))->toBe('18:30:00');
});

test('rounds exactly 45 minutes to next hour', function () {
    $date = Carbon::parse('2025-12-10 18:45:00');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('H:i:s'))->toBe('19:00:00');
});

test('handles rounding up at 23:45 to midnight', function () {
    $date = Carbon::parse('2025-12-10 23:53:00');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('Y-m-d H:i:s'))->toBe('2025-12-11 00:00:00');
});

test('preserves the date portion when rounding', function () {
    $date = Carbon::parse('2025-12-10 18:23:45');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->format('Y-m-d'))->toBe('2025-12-10');
});

test('preserves the timezone when rounding', function () {
    $date = Carbon::parse('2025-12-10 18:53:50', 'America/Chicago');
    $rounded = DateTimeHelpers::roundToNearestHalfHour($date);

    expect($rounded->timezoneName)->toBe('America/Chicago');
    expect($rounded->format('H:i:s'))->toBe('19:00:00');
});

test('does not mutate the original date', function () {
    $date = Carbon::parse('2025-12-10 18:53:50');
    DateTimeHelpers::roundToNearestHalfHour($date);

    expect($date->format('H:i:s'))->toBe('18:53:50');
});

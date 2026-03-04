<?php

use App\Filament\Pages\ManageSettings;
use App\Models\Settings;
use App\Models\User;
use Livewire\Livewire;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('it can render the settings page', function () {
    Livewire::test(ManageSettings::class)
        ->assertSuccessful();
});

test('it can save call to action', function () {
    Livewire::test(ManageSettings::class)
        ->fillForm(['call_to_action' => 'Follow us on YouTube!'])
        ->call('save');

    expect(Settings::instance()->call_to_action)
        ->toBe('Follow us on YouTube!');
});

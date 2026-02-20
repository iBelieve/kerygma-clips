<?php

use Filament\Pages\BasePage;
use Illuminate\Support\Facades\File;

test('filament pages override getTitle instead of only getHeading', function () {
    $files = File::allFiles(app_path('Filament'));

    $violations = [];

    foreach ($files as $file) {
        $className = str($file->getRelativePathname())
            ->replace('/', '\\')
            ->replace('.php', '')
            ->prepend('App\\Filament\\')
            ->toString();

        if (! class_exists($className)) {
            continue;
        }

        $reflection = new ReflectionClass($className);

        if (! $reflection->isSubclassOf(BasePage::class) || $reflection->isAbstract()) {
            continue;
        }

        $definesGetHeading = $reflection->hasMethod('getHeading')
            && $reflection->getMethod('getHeading')->getDeclaringClass()->getName() === $className;

        $definesGetTitle = $reflection->hasMethod('getTitle')
            && $reflection->getMethod('getTitle')->getDeclaringClass()->getName() === $className;

        if ($definesGetHeading && ! $definesGetTitle) {
            $violations[] = $className;
        }
    }

    expect($violations)
        ->toBeEmpty(
            'These Filament pages override getHeading() without getTitle(). '
            .'Override getTitle() instead so the browser tab title is also updated: '
            .implode(', ', $violations)
        );
});

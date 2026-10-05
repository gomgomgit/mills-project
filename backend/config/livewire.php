<?php

/*
 * Hanya kunci yang berbeda dari bawaan Livewire (vendor/livewire/livewire/
 * config/livewire.php) — sisanya digabung otomatis oleh mergeConfigFrom.
 *
 * navigate: bar progres bawaan wire:navigate diwarnai brand (#249360) supaya
 * sama dengan bar loading request Livewire (components/loading-assets).
 */
return [
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#249360',
    ],
];

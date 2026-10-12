<?php

$config = require __DIR__.'/../../config/horizon.php';

it('gives every queue its own Horizon supervisor', function (string $queue) use ($config) {
    $supervisors = collect($config['defaults'])
        ->filter(fn (array $supervisor) => $supervisor['queue'] === [$queue]);

    expect($supervisors)->toHaveCount(1);
})->with(['search', 'enrich', 'pagespeed', 'mail', 'default']);

it('keeps retry_after above every supervisor timeout', function () use ($config) {
    $queue = require __DIR__.'/../../config/queue.php';
    $maxTimeout = collect($config['defaults'])->max('timeout');

    expect($queue['connections']['redis']['retry_after'])->toBeGreaterThan($maxTimeout);
});

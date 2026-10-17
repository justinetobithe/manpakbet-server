<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

class Realtime
{
    public static function dispatch(object $event): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $exception) {
            Log::warning('Realtime broadcast failed', ['event' => $event::class, 'error' => $exception->getMessage()]);
        }
    }
}

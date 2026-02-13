<?php

namespace Plokko\ActivityLogger\Listeners;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Plokko\ActivityLogger\Facades\ActivityLog;

class LogEventActivity
{
    /**
     * Create the event listener.
     */
    public function __construct() {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        /// Try to get data for event
        $data = null;
        if ($event instanceof Arrayable) {
            $data = $event->toArray();
        } elseif ($event instanceof JsonSerializable) {
            $data = $event->jsonSerialize();
        }
        /// Log event
        ActivityLog::logEvent($event, $data);
    }
}

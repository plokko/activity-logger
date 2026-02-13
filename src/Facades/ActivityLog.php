<?php

namespace Plokko\ActivityLogger\Facades;

use Illuminate\Support\Facades\Facade;
use Plokko\ActivityLogger\ActivityLogger;

class ActivityLog extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ActivityLogger::class;
    }
}

<?php

namespace Plokko\ActivityLogger\Observers;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Plokko\ActivityLogger\Facades\ActivityLog;

class ModelActivityLogObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        ActivityLog::logModelEvent('created', $model);
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        ActivityLog::logModelEvent('updated', $model);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        ActivityLog::logModelEvent('deleted', $model);
    }

    /**
     * Handle the Model "restored" event.
     */
    public function restored(Model $model): void
    {
        ActivityLog::logModelEvent('restored', $model);
    }

    /**
     * Handle the Model "force deleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        ActivityLog::logModelEvent('forceDeleted', $model);
    }
}

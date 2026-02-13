<?php

namespace Plokko\ActivityLogger\Contracts;

interface LoggableModel
{
    /**
     * Return true if the model event should be logged
     *
     * @param string model event ('created', 'updated', 'deleted', 'restored', 'forceDeleted')
     */
    public function shouldLogModelEvent(string $modelEvent): bool;

    /**
     * Get loggable fields for ActivityLog
     *
     * @return ?array data to be logged or null if it should be ignored.
     */
    public function toLoggableData(): ?array;

    /**
     * Check if the log should track model changes.
     *
     * @param string model event ('created', 'updated', 'deleted', 'restored', 'forceDeleted')
     * @return array|bool if true all changed data will be logged, false no data will be logged otherwise a list of field to be included should be specified (ex. ['name','email'])
     */
    public function trackChanges(string $event): array|bool;
}

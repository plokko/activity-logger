<?php

namespace Plokko\ActivityLogger\Contracts;

interface LoggableEvent
{
    /**
     * Get event data for logs
     *
     * @return ?array data to be logged or null if it should be ignored.
     */
    public function toLoggableData(): ?array;
}

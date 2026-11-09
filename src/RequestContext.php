<?php

namespace Plokko\ActivityLogger;

use Illuminate\Support\Arr;

class RequestContext
{
    protected array $data = [];

    public function add(array $context): void
    {
        $this->data = array_replace_recursive($this->data, $context);
    }

    public function set(array $context): void
    {
        $this->data = $context;
    }

    public function forget(string ...$keys): void
    {
        $keys ? Arr::forget($this->data, $keys) : $this->data = [];
    }

    public function all(): array
    {
        return $this->data;
    }
}

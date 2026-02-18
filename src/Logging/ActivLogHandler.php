<?php

namespace Plokko\ActivityLogger\Logging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class ActivLogHandler extends AbstractProcessingHandler
{
    public function __construct(
        protected readonly string $endpoint,
        protected readonly string $token,
        /** Timeout in seconds */
        protected int $timeout = 2,
        Level|string|int $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $payload = [
            'datetime' => $record->datetime->format(\DateTime::ATOM),
            'level' => $record->level->getName(),
            'message' => $record->message,
            ...$record->context,
            //'channel' => $record->channel,

            ///'extra' => $record->extra, ///???
        ];

        dd($record, $payload);
        /// Send request
        $response = Http::baseUrl($this->endpoint)
            ->withToken($this->token)
            ->timeout($this->timeout)
            ->post('/api/logs', $payload);

        $response->onError(fn ($e) => Log::error('Unable to send ActiveLog logs: ' . $e->getMessage(), ['endpoint' => $this->endpoint, 'timeout' => $this->timeout, 'error' => $e]));
    }
}

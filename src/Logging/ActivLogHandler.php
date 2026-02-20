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
        protected bool $async = true,
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

        /// Send request
        $pending = Http::async($this->async)
            ->baseUrl($this->endpoint)
            ->withToken($this->token)
            ->timeout($this->timeout)
            ->asJson()
            ->acceptJson()
            ->post('/api/logs', $payload)
            ->then(function (\Illuminate\Http\Client\Response $response) {
                if (! $response->successful() || $response->json('successfull') !== true) {
                    $e = $response->toException();
                    Log::error('Unable to send ActiveLog logs: ' . $e?->getMessage(), ['endpoint' => $this->endpoint, 'timeout' => $this->timeout, 'error' => $e]);
                }
            });

        if ($this->async) {
            app()->terminating(fn () => $pending->wait());
        }
    }
}

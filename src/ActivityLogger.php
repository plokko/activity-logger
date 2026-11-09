<?php

namespace Plokko\ActivityLogger;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Plokko\ActivityLogger\Contracts\LoggableModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;
use UnexpectedValueException;

/**
 * Activity logger main implementation
 */
class ActivityLogger
{
    /**
     * @var ?Function(Request):bool return true if the request should be logged
     */
    protected ?Closure $requestMatcher = null;

    public function __construct(protected array $config) {}

    /**
     * Get logger for a specific type
     *
     * @param  string  $type  type: mode, acces or log
     */
    protected function getLoggerFor(string $type): LoggerInterface
    {
        // / Get channel name based on log type ///
        $channel = match ($type) {
            'log' => $this->config['log_channel'] ?? 'default',
            'models', 'access', 'events' => $this->config[$type]['channel'] ?? 'default',
            default => throw new UnexpectedValueException("Unexpected LogAction type $type"),
        };

        return $this->getLogger($channel);
    }

    /**
     * Get logger for a specific channel
     *
     * @param  string  $channel  channel name, defined in activity-loggable config
     */
    protected function getLogger(string $channel): LoggerInterface
    {
        $channelConfig = $this->config['channels'][$channel];

        return Log::build($channelConfig);
    }

    /**
     * Log a generic event
     *
     * @param  string|\Stringable  $message  log message
     */
    public function log(mixed $level, string|\Stringable $message, mixed $context = null): void
    {
        $this->getLoggerFor('log')
            ->log(
                level: $level,
                message: $message,
                context: $this->getLogData(
                    type: 'log',
                    context: $context,
                    extra: [],
                )
            );
    }

    public function info(string|\Stringable $message, mixed $context = null): void
    {
        $this->log('info', $message, $context);
    }

    public function notice(string|\Stringable $message, mixed $context = null): void
    {
        $this->log('notice', $message, $context);
    }

    /**
     * Define a closure that checks if the request should be logged.
     * If defined it will overrides the include/exclude definitions in cofing.
     *
     * @param ?Function(Request $request):bool return true if the request should be logged
     * @return $this
     */
    public function definerRequestMatcher(?Closure $requestMatcher)
    {
        $this->requestMatcher = $requestMatcher;

        return $this;
    }

    /**
     * Internal function to log model events
     *
     * @param  string  $event  model event name
     * @param  Model  $model  model
     * @return bool true if the model event has been logged
     *
     * @internal
     */
    public function logModelEvent(string $event, Model $model): bool
    {
        $model_data = null;
        if ($model instanceof LoggableModel) {

            if (! $model->shouldLogModelEvent($event)) {
                return false;
            }

            $model_data = $model->toLoggableData();
        }
        $changes = $this->getModelChanges($event, $model);

        $previus = null;
        if ($changes) {
            $model_data = $changes['current'];
            $previus = $changes['previus'];
        }

        $model_type = $model->getMorphClass();

        // / Model data ///
        $extra = [
            'model_type' => $model_type,
            'model_id' => $model->getKey(), // model primary key
            'model_event' => $event, // model primary key
        ];
        if ($model_data) {
            $extra['model_data'] = $model_data;
        }

        if ($previus) {
            $extra['previus'] = $previus;
        }

        // Log event //
        $this->getLoggerFor('models')
            ->notice("Model.$event $model_type", $this->getLogData(
                type: 'model_event',
                context: null,
                extra: $extra,
            ));

        return true;
    }

    protected function getModelChanges(string $event, Model $model): ?array
    {
        if (! $model instanceof LoggableModel) {
            return null;
        }
        $track = $model->trackChanges($event);
        if (! $track) {
            return null;
        }

        $hidden = $model->getHidden();
        $redactText = $this->config['models']['redacted_text'] ?? '<redacted>';
        // / Filter track data if specified ///
        $filterData = function (array $data) use ($track, $hidden, $redactText): array {
            // Determina quali campi considerare
            $data = is_array($track) ? array_intersect_key($data, array_flip($track)) : $data;
            // / Hide sensible data
            foreach ($hidden as $key) {
                if (! empty($data[$key])) {
                    $data[$key] = $redactText;
                }
            }

            return $data;
        };
        // / Current data
        $current = match ($event) {
            'created' => $filterData($model->getAttributes()),
            'updated' => $filterData($model->getChanges()),
            'restored' => ['deleted_at' => null],
            'deleted', 'forceDeleted' => $filterData($model->getOriginal()),
            default => null,
        };
        // / Previus data
        $previus = match ($event) {
            'updated' => collect($current)
                ->mapWithKeys(fn ($value, $field) => [
                    $field => in_array($field, $hidden) ? $redactText : $model->getOriginal($field),
                ])
                ->all(),
            'deleted', 'forceDeleted' => null,
            'restored' => ['deleted_at' => $model->getOriginal('deleted_at')],
            default => null,
        };

        return compact('current', 'previus');
    }

    /**
     * Logs a generic event.
     *
     * @param  object  $event  Event class
     * @param  ?array  $data  optional data of the event
     */
    public function logEvent(object $event, ?array $data = null): void
    {
        $eventClass = $event::class;
        // Log event //
        $this->getLoggerFor('events')
            ->notice("Event $eventClass", $this->getLogData(
                type: 'event',
                context: $data,
                extra: [
                    'event' => $eventClass,
                ],
            ));
    }

    /**
     * Check if a request matches the allowed paths/routes
     *
     * @param  Request  $request  Request to check
     */
    protected function matchAllowedPaths(Request $request): bool
    {
        $matchPaths = $this->config['access']['match']['paths'] ?? '*';
        $matchRoutes = $this->config['access']['match']['routes'] ?? '*';

        // / If not catchall
        if (
            ($this->config['access']['match'] ?? null) === '*' ||
            ($matchPaths === '*' && $matchRoutes === '*')
        ) {
            return true;
        }

        // / Check path match
        if ($matchPaths !== '*') {
            if (is_string($matchPaths)) {
                $matchPaths = [$matchPaths];
            }
            foreach ($matchPaths as $path) {
                if ($request->is($path)) {
                    return true;
                }
            }
        }

        // / Check route match
        if ($matchRoutes !== '*') {
            if (is_string($matchRoutes)) {
                $matchPaths = [$matchRoutes];
            }
            foreach ($matchRoutes as $pattern) {
                if ($request->routeIs($pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a request matches the exclude paths/routes
     *
     * @param  Request  $request  Request to check
     */
    protected function matchExcludedPaths(Request $request): bool
    {
        $matchPaths = $this->config['access']['exclude']['paths'] ?? [];
        $matchRoutes = $this->config['access']['exclude']['routes'] ?? [];

        foreach ($matchPaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }
        foreach ($matchRoutes as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Internal function, logs request from middleware
     *
     * @param  Request  $request  Request to be logged
     * @return bool return true if it has been logged, false if it's in the exclude list.
     *
     * @internal
     */
    public function logAccessRequest(Request $request, ?Response $response): bool
    {
        // Check if need to be logged
        $ignoreGuests = (bool) $this->config['access']['ignore_guests'] ?? true;
        if ($ignoreGuests && ! auth()->check()) {
            // Ignore guests
            return false;
        }

        // / Check if request passes allowed/exluded routes ///
        if ($this->requestMatcher) {
            // Use requestMatcher
            if (! ($this->requestMatcher)($request)) {
                return false;
            }
        } elseif (! $this->matchAllowedPaths($request) || $this->matchExcludedPaths($request)) {
            return false;
        }
        // Log request
        $this->logRequest($request, $response);

        return true;
    }

    public function logRequest(Request $request, ?Response $response, ?Throwable $exception = null): void
    {
        $method = $request->getMethod();
        $uri = $request->getRequestUri();
        $client_ip = $request->getClientIp();
        $is_json = $request->isJson();
        $is_secure = $request->isSecure();

        $exception ??= $response?->exception ?? null;

        $statusCode = $this->resolveStatusCode($response, $exception);
        $exception_class = $exception ? $exception::class : null;
        $message = null;

        if ($exception) {
            $message = $exception->getMessage() ?: null;
        } elseif ($response?->isRedirect()) {
            $message = $response->headers->get('Location');
        }

        $this->getLoggerFor('access')
            ->log(match (true) {
                $statusCode >= 500 => 'error',
                $statusCode >= 400 => 'warning',
                default => 'notice',
            }, "> $method $uri", $this->getLogData(
                type: 'access',
                context: $this->requestContext()->all() ?: null,
                extra: array_filter(
                    compact('method', 'uri', 'client_ip', 'is_json', 'is_secure', 'statusCode', 'message', 'exception_class'),
                    fn ($v) => $v !== null,
                ),
            ));
    }

    protected function resolveStatusCode(?Response $response, ?Throwable $exception): int
    {
        if ($exception && ($code = $this->statusFromException($exception)) !== null) {
            return $code;
        }

        return $response?->getStatusCode() ?? ($exception ? 500 : 200);
    }

    protected function statusFromException(Throwable $exception): ?int
    {
        return match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof ValidationException => $exception->status, // 422
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => $exception->status() ?? 403,
            $exception instanceof ModelNotFoundException,
            $exception instanceof RecordsNotFoundException => 404,
            $exception instanceof TokenMismatchException => 419,
            default => null,
        };
    }

    /**
     * Internal function to create a Log interface data payload
     *
     * @return array log context data
     */
    protected function getLogData(
        string $type,
        ?array $context = null,
        array $extra = []
    ): array {
        $user = Auth::user();

        $user_data = ($user && $user instanceof LoggableModel) ?
            $user->toLoggableData() :
            null;

        return array_filter([
            'type' => $type,
            'auth_id' => $user?->id, // User id
            'auth_class' => $user?->getMorphClass(), // <-- Write morphed class (polymotphic type) or className
            'auth_data' => $user_data,
            // /todo: Cast user data?
            'context' => $context,
            ...$extra,
        ]);
    }

    protected function requestContext(): RequestContext
    {
        return app(RequestContext::class);
    }

    public function addRequestContext(array $context): static
    {
        $this->requestContext()->add($context);

        return $this;
    }

    public function getRequestContext(): array
    {
        return $this->requestContext()->all();
    }
}

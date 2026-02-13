<?php

namespace Plokko\ActivityLogger;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Plokko\ActivityLogger\Contracts\LoggableModel;
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
    protected function getLoggerFor(string $type): \Psr\Log\LoggerInterface
    {
        /// Get channel name based on log type ///
        $channel = match ($type) {
            'log' => $this->config['log_channel'] ?? 'default',
            'models', 'access' => $this->config[$type]['channel'] ?? 'default',
            default => throw new UnexpectedValueException("Unexpected LogAction type $type"),
        };

        return $this->getLogger($channel);
    }

    /**
     * Get logger for a specific channel
     *
     * @param  string  $channel  channel name, defined in activity-loggable config
     */
    protected function getLogger(string $channel): \Psr\Log\LoggerInterface
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
     * @internal
     *
     * @param  string  $event  model event name
     * @param  Model  $model  model
     * @return bool true if the model event has been logged
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

        /// Model data ///
        $extra = [
            'model_type' => $model_type,
            'model_id' => $model->getKey(), // model primary key
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
        /// Filter track data if specified ///
        $filterData = function (array $data) use ($track, $hidden, $redactText): array {
            // Determina quali campi considerare
            $data = is_array($track) ? array_intersect_key($data, array_flip($track)) : $data;
            /// Hide sensible data
            foreach ($hidden as $key) {
                if (! empty($data[$key])) {
                    $data[$key] = $redactText;
                }
            }

            return $data;
        };
        /// Current data
        $current = match ($event) {
            'created' => $filterData($model->getAttributes()),
            'updated' => $filterData($model->getChanges()),
            'restored' => ['deleted_at' => null],
            'deleted', 'forceDeleted' => $filterData($model->getOriginal()),
            default => null,
        };
        /// Previus data
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
     * Check if a request matches the allowed paths/routes
     *
     * @param  Request  $request  Request to check
     */
    protected function matchAllowedPaths(Request $request): bool
    {
        $matchPaths = $this->config['access']['match']['paths'] ?? '*';
        $matchRoutes = $this->config['access']['match']['routes'] ?? '*';

        /// If not catchall
        if (
            ($this->config['access']['match'] ?? null) === '*' ||
            ($matchPaths === '*' && $matchRoutes === '*')
        ) {
            return true;
        }

        /// Check path match
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

        /// Check route match
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
     *
     * @internal
     *
     * @return bool return true if it has been logged, false if it's in the exclude list.
     */
    public function logAccessRequest(Request $request): bool
    {
        /// Check if request passes allowed/exluded routes ///
        if ($this->requestMatcher) {
            // Use requestMatcher
            if (! ($this->requestMatcher)($request)) {
                return false;
            }
        } elseif (! $this->matchAllowedPaths($request) || $this->matchExcludedPaths($request)) {
            return false;
        }
        // Todo: log

        $method = $request->getMethod();
        $uri = $request->getRequestUri();
        $client_ip = $request->getClientIp();
        $is_json = $request->isJson();
        $is_secure = $request->isSecure();

        // Log access //
        $this->getLoggerFor('access')
            ->notice("> $method $uri", $this->getLogData(
                type: 'access',
                context: null,
                extra: compact('method', 'uri', 'client_ip', 'is_json', 'is_secure'),
            ));

        return true;
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
            ///todo: Cast user data?
            'context' => $context,
            ...$extra,
        ]);
    }
}

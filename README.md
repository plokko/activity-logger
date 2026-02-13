# Activity logger
A Laravel helper for logging user activity on models and routes.

This packages logs user activity in the website by tracking:
 - **access** - route access to the website
 - **model** - model events (create, update, delete, forceDelete, restore )
 - **logs** - extra logs added by the developer
 - **events** - custom events 

## Installation

Install the package via composer:
```bash
composer require plokko/activity-logger
```

### Setup access logs

Register the AccessLogger middleware in your application

by adding it in the bootstrap\app.php file (Laravel >11):
```diff
return Application::configure(basePath: dirname(__DIR__))
    
    ///...  
    ->withMiddleware(function (Middleware $middleware) {
        
        ///... 

        /// Inertia ///
        $middleware->web(append: [
            HandleInertiaRequests::class,//<< Other middlewares
+            \Plokko\ActivityLogger\Http\Middleware\AccessLogger::class,
        ]);
        
```

To select what routes/paths to log you can either use the configuration (see the `access` configuration in the log secthion) or define a custom function in your `AppServiceProvider`: 

```php
use Plokko\ActivityLogger\Facades\ActivityLog;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ActivityLog::definerRequestMatcher(function(Request $request){
            /// Your logic here, must return true if has to be logged, false otherwise.
            return $request->is('admin/*');
        });
```

### Setup model logs

To track model changes add the `ModelActivityLogObserver` observer to all the model you want to track

```php
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Plokko\ActivityLogger\Observers\ModelActivityLogObserver;

#[ObservedBy([ModelActivityLogObserver::class])]
class MyModel
{
    //...
}
```

#### Customize model logging
To allow deeper model log customization implement the `LoggableModel` interface

```php
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Plokko\ActivityLogger\Observers\ModelActivityLogObserver;
use Plokko\ActivityLogger\Contracts\LoggableModel;

#[ObservedBy([ModelActivityLogObserver::class])]
class MyModel implements LoggableModel
{
    /**
     * Return true if the model event should be logged
     *
     * @param string model event ('created', 'updated', 'deleted', 'restored', 'forceDeleted')
     */
    public function shouldLogModelEvent(string $modelEvent): bool
    {
        return true;
    }

    /**
     * Get loggable fields for ActivityLog
     *
     * @return ?array data to be logged or null if it should be ignored.
     */
    public function toLoggableData(): ?array
    {
        return $this->only('name', 'email');
    }

    /**
     * Check if the log should track model changes.
     *
     * @param string model event ('created', 'updated', 'deleted', 'restored', 'forceDeleted')
     * @return array|bool if true all changed data will be logged, false no data will be logged otherwise a list of field to be included should be specified (ex. ['name','email'])
     */
    public function trackChanges(string $event): array|bool
    {
        return true;
    }
}
```

The interface implements various methods:
  - `shouldLogModelEvent(string $modelEvent): bool` - check if the model should be logged
  - `toLoggableData():?array` - return the data that should be shown in the logs, used to show Authenticable data in all logs.
  - `shouldLogModelEvent(string $modelEvent): bool` - check if the model should be logged
  - `trackChanges(string $event): array|bool` - allow to track model changes (ex. track what content was changed betwee updates)


### Setup event logging

If you want to log your events register the `LogEventActivity` listener to your event in your `AppServeceProvider`:
```php
use Illuminate\Support\Facades\Event;
use Plokko\ActivityLogger\Listeners\LogEventActivity;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /// Add logging for all your custom events
        Event::listen(OrderShipped::class, LogEventActivity::class);
    }
}
```
The data of the event will be automatically logged if your event implements `JsonSerializable`, `Arrayable` or `LoggableEvent` interface:

```php
use Plokko\ActivityLogger\Contracts\LoggableEvent;

class OrderShipped implements LoggableEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $orderCode,
        public string $OrderDescription,
        public array $items,
    ) {}
 
    /**
     * Get event data for logs
     *
     * @return ?array data to be logged or null if it should be ignored.
     */
    public function toLoggableData(): ?array{
        return [
            'code' => $this->orderCode,
            'description' => $this->OrderDescription,
            'item_count' => count($this->items),
        ];
    }
}
```
If the event implements multiple interfaces the data will be taken from the first implemented interface in this order: `LoggableEvent`, `Arrayable`, `JsonSerializable`.

## Configuration
To publish default configuration files run this command in console:
```bash
php artisan vendor:publish --tag=activity-logger:config
```
```php
<?php

/**
 * Activity logger config
 */
return [

    /** Channel to use for other logs */
    'log_channel' => 'default',

    /**
     * Log channel definition
     *
     * @see https://laravel.com/docs/12.x/logging#configuration
     **/
    'channels' => [
        'models' => [
            'driver' => 'daily',
            'days' => 60,
            'path' => storage_path('logs/activity/models.log'),
        ],
        'access' => [
            'driver' => 'daily',
            'days' => 60,
            'path' => storage_path('logs/activity/access.log'),
        ],
        'events' => [
            'driver' => 'daily',
            'days' => 60,
            'path' => storage_path('logs/activity/events.log'),
        ],
        'default' => [
            'driver' => 'daily',
            'days' => 60,
            'path' => storage_path('logs/activity/other.log'),
        ],
    ],

    /** Model logs settings */
    'models' => [
        /** Channel to use for model activity */
        'channel' => 'models',
        /** Text to be replaced for hidden fields when tracking for changes */
        'redacted_text' => '<redacted>',
    ],

    /** Event logs settings */
    'events' => [
        /** Channel to use for model activity */
        'channel' => 'events',
    ],

    /** Logs only this urls */
    'access' => [
        /** Default channel to use for traffic (routes) logs */
        'channel' => 'access',

        /**
         * Define matching rules for logging access requests
         */
        'match' => [
            /**  Match paths
             *
             * @var bool|string|string[]
             *                           - '*'|null match all
             *                           - string - single match (ex. '/admin/*')
             *                           - string[] - list of matches (ex. ['/admin/*', '/test/*'])
             */
            'path' => '*',
            /**  Match paths
             *
             * @var bool|string|string[]
             *                           - '*'|null match all
             *                           - string - single match (ex. 'test.*')
             *                           - string[] - list of matches (ex. ['test.*', 'dump.*'])
             */
            'routes' => '*',
        ],

        /**
         * Define rules for excluding requests from  logs.
         * If the
         */
        'exclude' => [
            /**  Match paths
             *
             * @var bool|string|string[]
             *                           - '*'|null match all
             *                           - string - single match (ex. '/test/*')
             *                           - string[] - list of matches (ex. ['/test/*', '/dump/*'])
             */
            'paths' => [],
            /**  Match routes
             *
             * @var bool|string|string[]
             *                           - '*'|null match all
             *                           - string - single match (ex. 'test.*')
             *                           - string[] - list of matches (ex. ['test.*', 'dump.*'])
             */
            'routes' => [],
        ],
    ],
];
```

 - `log_channel` - string - identifies the channel to use for generic logs
 - `channels` - array<string,array> - defines all the available log channels (see [Laravel log channel definition](https://laravel.com/docs/12.x/logging#configuration) )
 - `models` - define model logs settings
    - `channel` - string - defines the channel to use for models logs
    - `redacted_text` - string - content to replace in hidden fields while tracing model changes.
 - `events` - define event log settings
    - `channel` - string - defines the channel to use for event logs 

 - `access` - define access logs settings
    - `channel` - string - defines the channel to use for traffic (routes) logs
    - `match` - defines the routes/paths to log
        - `path` - bool|string|string[] - ...
        - `routes` - bool|string|string[] - ...
    - `exclude` - defines the routes/paths to exclude (must be in matching rules)
        - `path` - bool|string|string[] - ...
        - `routes` - bool|string|string[]- ...

...TODO...

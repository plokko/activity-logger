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

### Publishing configuration

To publish default configuration files run this command in console:
```bash
php artisan vendor:publish --tag=activity-logger:config
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
To see how to exclude/include paths/routes see the configuration.


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

...TODO...

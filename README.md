# Activity logger
A Laravel helper for logging user activity on models and routes.

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

...

### Setup model logs
...

## Configuration

...

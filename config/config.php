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
        'activlog' => [
            'driver' => 'activlog',
            'endpoint' => env('ACTIVLOG_ENDPOINT'),
            'token' => env('ACTIVLOG_TOKEN'),
            'timeout' => 2,
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

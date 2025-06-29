<?php

use App\Services\Mailer;
use App\Services\UserService;

/**
 * Define your service configurations here.
 *
 * Each key is the service ID (typically a class FQCN or an alias).
 * Each value is the definition:
 * - A string: FQCN of a class for auto-resolution (constructor injection).
 * - A callable (closure): A factory function that returns the service instance.
 * - An array: For advanced configuration (e.g., singleton, specific arguments).
 */
return [
    // --- Scalar/Parameter Definitions ---
    'app_name' => 'DI Playground App',
    'admin_email' => 'admin@example.com',
    'mailer_dsn' => 'smtp://localhost:1025',

    // --- Service Definitions ---

    // Mailer Service: Simple class, auto-resolved.
    Mailer::class => [
        'class' => Mailer::class,
        'arguments' => [
            'dsn' => 'mailer_dsn' // Inject the 'mailer_dsn' parameter by name
        ]
    ],

    // UserService: Depends on Mailer. Container will inject Mailer automatically.
    // Store this definition in a variable for reuse.
    'userServiceDefinition' => [ // Define the service configuration for UserService
        'class' => UserService::class,
        'singleton' => true, // Ensure only one instance of UserService is created
        'arguments' => [
            // Mailer::class is correctly type-hinted and resolved by the container automatically.
            // 'mailer' => Mailer::class, // This line is not strictly needed if Mailer is type-hinted
            'adminEmail' => 'admin_email' // Inject the 'admin_email' parameter by name
        ]
    ],
    UserService::class => 'userServiceDefinition', // Reference the definition by its key

    // --- Aliases ---
    // Now, 'app.user_service' will point to the same detailed definition.
    'app.user_service' => 'userServiceDefinition', // Point alias to the shared definition key
    'app.mailer' => Mailer::class, // Mailer still simple, but could also be a shared definition if needed

    // --- Another example with a simple factory ---
    'random_number_generator' => function() {
        return rand(1, 100);
    },

    // --- An interface binding to a concrete implementation (if you had an interface for Mailer) ---
    // \App\Interfaces\MailerInterface::class => Mailer::class,
];


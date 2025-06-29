<?php

require_once __DIR__ . '/../vendor/autoload.php';



use Masoud4\HttpTools\Container\Container;
use Masoud4\HttpTools\Container\ContainerInterface;
use Masoud4\HttpTools\Container\Exception\NotFoundException;
use Masoud4\HttpTools\Container\Exception\ContainerException;

use App\Services\UserService;
use App\Services\Mailer;
echo "<pre>";
echo "--- Starting DI Container Playground ---" . PHP_EOL;

// 3. Load service definitions from config file
$serviceDefinitions = require_once __DIR__ . '/../app/Config/services.php';

// 4. Instantiate the Container with definitions
$container = new Container($serviceDefinitions);

// --- Demonstrate Service Resolution ---

echo PHP_EOL . "Attempting to resolve services..." . PHP_EOL;

try {
    // Resolve UserService - it should automatically resolve and inject Mailer
    /** @var UserService $userService */
    $userService = $container->get(UserService::class);
    $userService->registerUser('Alice', 'alice@example.com');

    echo PHP_EOL;

    // Resolve Mailer directly
    /** @var Mailer $mailer */
    $mailer = $container->get(Mailer::class);
    $mailer->sendEmail('bob@example.com', 'Test Email', 'This is a direct email via resolved Mailer.');

    echo PHP_EOL;

    // Test singleton: should be the same instance as before
    $anotherUserService = $container->get(UserService::class);
    if ($userService === $anotherUserService) {
        echo "UserService is a singleton (as expected)." . PHP_EOL;
    } else {
        echo "UserService is NOT a singleton (unexpected)." . PHP_EOL;
    }

    // Resolve a parameter
    $appName = $container->get('app_name');
    echo "Application Name: {$appName}" . PHP_EOL;

    // Resolve an alias
    $aliasedUserService = $container->get('app.user_service');
    if ($userService === $aliasedUserService) {
        echo "'app.user_service' alias resolves to the same UserService instance." . PHP_EOL;
    }

    // Resolve a factory-defined service
    $randomNumber = $container->get('random_number_generator');
    echo "Random number from factory: {$randomNumber}" . PHP_EOL;
    $anotherRandomNumber = $container->get('random_number_generator');
    echo "Another random number from factory (should be different each time if not singleton): {$anotherRandomNumber}" . PHP_EOL;


} catch (NotFoundException $e) {
    echo "ERROR: Service not found: " . $e->getMessage() . PHP_EOL;
} catch (ContainerException $e) {
    echo "ERROR: Container Exception: " . $e->getMessage() . PHP_EOL;
} catch (\Throwable $e) {
    echo "UNEXPECTED ERROR: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "--- DI Container Playground Finished ---" . PHP_EOL;

echo "</pre>";
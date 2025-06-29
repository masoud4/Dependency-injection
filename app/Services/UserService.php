<?php
namespace App\Services;


class UserService
{
    private Mailer $mailer;
    private string $adminEmail;
    // Removed: private Request $request; // Example of a third dependency

    // The DI Container will automatically inject Mailer and the string adminEmail
    public function __construct(Mailer $mailer, string $adminEmail) // Removed Request $request
    {
        $this->mailer = $mailer;
        // Removed: $this->request = $request;
        $this->adminEmail = $adminEmail;
        echo "UserService initialized with Mailer and Admin Email: " . $this->adminEmail . PHP_EOL;
    }

    public function registerUser(string $username, string $userEmail): void
    {
        echo "Registering user: {$username} ({$userEmail})" . PHP_EOL;
        // Example: Send a welcome email using the injected Mailer
        $this->mailer->sendEmail($userEmail, "Welcome to our app!", "Hello {$username}, welcome!");
        // Removed: Example: Log request method using injected Request
        // Removed: echo "User registered via HTTP method: " . $this->request->method() . PHP_EOL;
        
        // Example: Send notification to admin
        $this->mailer->sendEmail($this->adminEmail, "New User Registered", "User {$username} registered!");
    }
}


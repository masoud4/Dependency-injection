<?php
namespace App\Services;

class Mailer
{
    private string $dsn;

    public function __construct(string $dsn)
    {
        $this->dsn = $dsn;
        echo "Mailer service initialized with DSN: " . $this->dsn . PHP_EOL;
    }

    public function sendEmail(string $recipient, string $subject, string $body): void
    {
        echo "Sending email via [{$this->dsn}] to {$recipient}: Subject - '{$subject}', Body - '{$body}'" . PHP_EOL;
    }
}


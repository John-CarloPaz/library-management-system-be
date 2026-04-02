<?php

namespace App\Contracts\Mail;

interface TransactionalEmailClient
{
    /**
     * Sends a transactional email.
     *
     * @return string|null Provider message id when available.
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlContent): ?string;
}

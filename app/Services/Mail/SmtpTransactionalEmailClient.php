<?php

namespace App\Services\Mail;

use App\Contracts\Mail\TransactionalEmailClient;
use App\Mail\TransactionalHtmlMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SmtpTransactionalEmailClient implements TransactionalEmailClient
{
    public function send(string $toEmail, string $toName, string $subject, string $htmlContent): ?string
    {
        $fromAddress = (string) config('mail.from.address');
        if ($fromAddress === '' || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'MAIL_FROM_ADDRESS must be a valid email address (current: ' . ($fromAddress === '' ? '[empty]' : $fromAddress) . ').'
            );
        }

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Recipient email is invalid: ' . $toEmail);
        }

        Mail::to($toEmail, $toName)->send(new TransactionalHtmlMail($subject, $htmlContent));

        return null;
    }
}

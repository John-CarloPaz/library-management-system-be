<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TransactionalHtmlMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $subjectLine,
        private readonly string $htmlContent,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject($this->subjectLine)
            ->html($this->htmlContent);
    }
}

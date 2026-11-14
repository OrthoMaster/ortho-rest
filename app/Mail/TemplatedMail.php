<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Một email của quán đã ghép xong nội dung (mẫu + dữ liệu). */
class TemplatedMail extends Mailable
{
    public function __construct(
        public string $mailSubject,
        public string $body,
        public Address $sender,
        public ?string $replyAddress = null,
        public string $key = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->sender,
            replyTo: $this->replyAddress ? [new Address($this->replyAddress)] : [],
            subject: $this->mailSubject,
            tags: array_filter([$this->key]),
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->body);
    }
}

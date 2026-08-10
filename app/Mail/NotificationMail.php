<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 중요 알림(위험/공지) 이메일. 큐 없이 직접(동기) 발송한다.
 */
class NotificationMail extends Mailable
{
    use SerializesModels;

    public function __construct(public Notification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[SmartLogis] '.$this->notification->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: ['n' => $this->notification],
        );
    }
}

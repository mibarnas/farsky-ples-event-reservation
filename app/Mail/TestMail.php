<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Test e-mail sent from the event dashboard to the logged-in user, to check mail
 * delivery and rendering. It carries a dummy QR code and is not a ticket.
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Same shape as a real reservation QR code, but no reservation uses it. */
    public const DUMMY_QR_CODE = 'TEST-00000000-0000-0000-0000-000000000000';

    public string $qrPng;

    public string $qrUrl;

    public function __construct(
        public Event $event,
        public User $recipient,
    ) {
        $this->qrUrl = route('reservation.show', ['qrCode' => self::DUMMY_QR_CODE]);
        $this->qrPng = (new Writer(new GDLibRenderer(300, 2)))->writeString($this->qrUrl);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[TEST] '.$this->event->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test-mail',
        );
    }
}

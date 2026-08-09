<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subject());
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.otp',
            with: [
                'code' => $this->code,
                'purposeLabel' => $this->purposeLabel(),
                'ttl' => (int) config('ppob.otp.ttl_minutes', 10),
            ],
        );
    }

    private function subject(): string
    {
        return match ($this->purpose) {
            'reset_password' => 'Kode Reset Kata Sandi — '.config('app.name'),
            'login' => 'Kode Login — '.config('app.name'),
            default => 'Kode Verifikasi Akun — '.config('app.name'),
        };
    }

    private function purposeLabel(): string
    {
        return match ($this->purpose) {
            'reset_password' => 'mengatur ulang kata sandi',
            'login' => 'masuk ke akun',
            default => 'memverifikasi akun',
        };
    }
}

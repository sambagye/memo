<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Soutenance;

class SoutenanceNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $soutenance;
    public $recipientType; // 'etudiant', 'jury', 'encadreur'
    public $type; // 'programmee', 'rappel', 'terminee'

    public function __construct(Soutenance $soutenance, string $recipientType, string $type = 'programmee')
    {
        $this->soutenance = $soutenance;
        $this->recipientType = $recipientType;
        $this->type = $type;
    }

    public function envelope(): Envelope
    {
        $subjects = [
            'programmee' => 'Soutenance de mémoire programmée',
            'rappel' => 'Rappel: Soutenance de mémoire demain',
            'terminee' => 'Soutenance de mémoire terminée',
        ];

        return new Envelope(
            subject: $subjects[$this->type] . ' - ISI',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.soutenance-notification',
            with: [
                'soutenance' => $this->soutenance,
                'recipientType' => $this->recipientType,
                'type' => $this->type,
                'etudiant' => $this->soutenance->etudiant,
                'jury' => $this->soutenance->jury,
                'platformUrl' => config('app.frontend_url', 'http://localhost:4200'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

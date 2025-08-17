<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Affectation;

class AffectationNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $affectation;
    public $recipientType; // 'etudiant' ou 'encadreur'

    public function __construct(Affectation $affectation, string $recipientType)
    {
        $this->affectation = $affectation;
        $this->recipientType = $recipientType;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle affectation de mémoire - ISI',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.affectation-notification',
            with: [
                'affectation' => $this->affectation,
                'recipientType' => $this->recipientType,
                'etudiant' => $this->affectation->etudiant,
                'encadreur' => $this->affectation->encadreur,
                'sujet' => $this->affectation->sujet,
                'platformUrl' => config('app.frontend_url', 'http://localhost:4200'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

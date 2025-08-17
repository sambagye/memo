<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\SeanceEncadrement;

class SeanceNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $seance;
    public $type; // 'programmee', 'rappel', 'annulee', 'terminee'

    public function __construct(SeanceEncadrement $seance, string $type = 'programmee')
    {
        $this->seance = $seance;
        $this->type = $type;
    }

    public function envelope(): Envelope
    {
        $subjects = [
            'programmee' => 'Nouvelle séance d\'encadrement programmée',
            'rappel' => 'Rappel: Séance d\'encadrement demain',
            'annulee' => 'Séance d\'encadrement annulée',
            'terminee' => 'Compte-rendu de séance d\'encadrement',
        ];

        return new Envelope(
            subject: $subjects[$this->type] . ' - ISI',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seance-notification',
            with: [
                'seance' => $this->seance,
                'type' => $this->type,
                'etudiant' => $this->seance->affectation->etudiant,
                'encadreur' => $this->seance->affectation->encadreur,
                'sujet' => $this->seance->affectation->sujet,
                'platformUrl' => config('app.frontend_url', 'http://localhost:4200'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

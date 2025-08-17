<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Sujet;

class SujetValidationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sujet;

    public function __construct(Sujet $sujet)
    {
        $this->sujet = $sujet;
    }

    public function envelope(): Envelope
    {
        $action = $this->sujet->statut === 'valide' ? 'validé' : 'refusé';

        return new Envelope(
            subject: "Votre sujet de mémoire a été {$action} - ISI",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sujet-validation',
            with: [
                'sujet' => $this->sujet,
                'encadreur' => $this->sujet->encadreur,
                'isApproved' => $this->sujet->statut === 'valide',
                'platformUrl' => config('app.frontend_url', 'http://localhost:4200'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

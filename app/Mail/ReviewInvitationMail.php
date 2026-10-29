<?php

namespace App\Mail;

use App\Models\ProjectContract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProjectContract $contract,
        public string $reviewerName,
        public string $revieweeName
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.review_invitation_subject', ['project' => $this->contract->project->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.review-invitation',
        );
    }
}

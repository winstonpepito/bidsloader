<?php

namespace App\Mail;

use App\Services\FBOFeed\LoadResult;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeedLoadNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly array $results,
    ) {
    }

    public function envelope(): Envelope
    {
        $totalLoaded = 0;
        $totalErrors = 0;
        foreach ($this->results as $result) {
            if ($result instanceof LoadResult) {
                $totalLoaded += $result->entriesLoaded;
                $totalErrors += $result->errorsCount;
            }
        }

        $subject = config('fbo.email_subject', 'FBO FeedLoader Notification')
            . ' - ' . now()->format('Y-m-d H:i')
            . " ({$totalLoaded} loaded, {$totalErrors} errors)";

        return new Envelope(
            from: config('fbo.email_from'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.feed-notification',
            with: ['results' => $this->results],
        );
    }
}

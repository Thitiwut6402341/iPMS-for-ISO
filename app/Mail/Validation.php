<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Validation extends Mailable
{
    use Queueable, SerializesModels;
    public $customerName;
    public $projectName;
    public $step;
    public $documentName;
    public $link;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($customerName, $projectName, $step, $documentName, $link)
    {
        $this->customerName = $customerName;
        $this->projectName = $projectName;
        $this->step = $step;
        $this->documentName = $documentName;
        $this->link = $link;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: '99IS-CoDE',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        if ($this->documentName === "PROJECT PLAN (PP)") {
            return new Content(
                view: 'pp',
                with: [
                    "customerName" => $this->customerName,
                    "projectName" => $this->projectName,
                    "step" => $this->step,
                    "documentName" => $this->documentName,
                    "link" => $this->link,
                ],
            );
        } else if ($this->documentName === "SOFTWARE REQUIREMENT SPECIFICATIONS (SRS)") {
            return new Content(
                view: 'srs',
                with: [
                    "customerName" => $this->customerName,
                    "projectName" => $this->projectName,
                    "step" => $this->step,
                    "documentName" => $this->documentName,
                    "link" => $this->link,
                ],
            );
        } else {
            return new Content(
                view: 'uat',
                with: [
                    "customerName" => $this->customerName,
                    "projectName" => $this->projectName,
                    "step" => $this->step,
                    "documentName" => $this->documentName,
                    "link" => $this->link,
                ],
            );
        }
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}

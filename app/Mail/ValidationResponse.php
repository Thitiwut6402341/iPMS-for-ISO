<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ValidationResponse extends Mailable
{
    use Queueable, SerializesModels;
    public $isValidated;
    public $customerName;
    public $projectName;
    public $link;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($isValidated, $customerName, $projectName, $link)
    {
        $this->isValidated = $isValidated;
        $this->customerName = $customerName;
        $this->projectName = $projectName;
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
        if($this->isValidated == true){
            return new Content(
                view: 'customerapprove',
                with: [
                    "customerName" => $this->customerName,
                    "link" => $this->link,
                ],
            );
        }else{
            return new Content(
                view: 'customerreject',
                with: [
                    "customerName" => $this->customerName,
                    "projectName" => $this->projectName,
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

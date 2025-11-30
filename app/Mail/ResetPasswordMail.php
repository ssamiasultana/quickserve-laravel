<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;
    public $resetLink;
    public $token;
    /**
     * Create a new message instance.
     * 
     *
     * @return void
     */
    public function __construct($resetLink,$token)
    {
        //
        $this->resetLink = $resetLink;
        $this->token = $token;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: 'Reset Password Mail',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'view.name',
        );
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

    public function build(){
        return $this->subject('Reset Your Password')
        ->html($this->getEmailHtml());
    }

    private function getEmailHtml()
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 40px auto; padding: 20px; background-color: #ffffff; border-radius: 8px; }
                h2 { color: #3B82F6; }
                .button { display: inline-block; padding: 14px 28px; background-color: #3B82F6; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #6b7280; }
                .link-text { word-break: break-all; color: #3B82F6; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Reset Your Password</h2>
                <p>You are receiving this email because we received a password reset request for your account.</p>
                
                <div style='text-align: center;'>
                    <a href='{$this->resetLink}' class='button'>Reset Password</a>
                </div>
                <p>This password reset link will expire in <strong>60 minutes</strong>.</p>
                
                <p>If you did not request a password reset, no further action is required.</p>
                
                <div class='footer'>
                    <p><strong>Having trouble?</strong></p>
                    <p>If you're having trouble clicking the button, copy and paste the URL below into your web browser:</p>
                    <p class='link-text'>{$this->resetLink}</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}

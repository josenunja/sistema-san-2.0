<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessInvitation extends Notification
{
    public function __construct(private string $business, private string $link) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Invitación a '.$this->business)->line('Tienes una invitación para trabajar en '.$this->business.'.')->action('Aceptar invitación', $this->link)->line('Caduca en 48 horas y solo puede utilizarse una vez. Si ya tienes cuenta, utiliza tu contraseña actual.');
    }
}

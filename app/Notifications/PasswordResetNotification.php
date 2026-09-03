<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $resetUrl = "{$frontendUrl}/reset-password?token={$this->token}&email={$notifiable->email}";

        return (new MailMessage())
            ->subject('إعادة تعيين كلمة المرور — HumaScale')
            ->greeting("مرحبًا {$notifiable->name}،")
            ->line('تلقينا طلبًا لإعادة تعيين كلمة المرور الخاصة بحسابك.')
            ->line('استخدم الرابط أدناه لإعادة تعيين كلمة المرور:')
            ->action('إعادة تعيين كلمة المرور', $resetUrl)
            ->line('هذا الرابط صالح لمدة 60 دقيقة فقط.')
            ->line('إذا لم تطلب إعادة تعيين كلمة المرور، يمكنك تجاهل هذه الرسالة.')
            ->salutation('فريق HumaScale');
    }
}

<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class EmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'auth.verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage())
            ->subject('تفعيل حسابك في منصة HumaScale')
            ->greeting("مرحبًا {$notifiable->name}،")
            ->line('شكرًا لتسجيلك في منصة HumaScale لتقييم الاستعداد للنمو.')
            ->line('اضغط على الزر أدناه لتفعيل حسابك:')
            ->action('تفعيل الحساب', $verificationUrl)
            ->line('هذا الرابط صالح لمدة 60 دقيقة فقط.')
            ->line('إذا لم تقم بإنشاء حساب، يمكنك تجاهل هذه الرسالة.')
            ->salutation('فريق HumaScale');
    }
}

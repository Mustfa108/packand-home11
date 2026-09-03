<?php

namespace App\Notifications;

use App\Models\Assessment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AssessmentCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Assessment $assessment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title_ar'      => 'اكتمل تحليل تقييمك! 🎉',
            'body_ar'       => 'تم الانتهاء من تحليل نتائج تقييمك. يمكنك الآن الاطلاع على النتائج التفصيلية وخطة التطوير.',
            'assessment_id' => $this->assessment->id,
            'type'          => 'assessment_completed',
        ];
    }
}

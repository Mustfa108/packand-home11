<?php

namespace App\Events;

use App\Models\CommunityMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommunityMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public CommunityMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('community-chat')];
    }

    public function broadcastAs(): string
    {
        return 'community.message';
    }

    public function broadcastWith(): array
    {
        return $this->message->toApiArray();
    }
}

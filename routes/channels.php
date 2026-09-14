<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('community-chat', function ($user) {
    return $user instanceof User || $user instanceof Admin;
});

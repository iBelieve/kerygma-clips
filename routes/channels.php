<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('sermon-updates', fn (User $user) => true);

<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id) => $user->id === $id);

Broadcast::channel('backoffice.transactions', fn (User $user) => $user->isBackoffice() && $user->hasPermissionTo('transactions.process', 'web'));

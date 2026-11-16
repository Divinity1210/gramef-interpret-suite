<?php

namespace App\Observers;

use App\Helpers\RoleHelper;
use App\Mail\UserCreatedMail;
use App\Mail\UserMail;
use App\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    public function creating(User $user)
    {
        $user->email_verified_at = now();
    }

    public function updating(User $user)
    {
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send email verification notification: ' . $e->getMessage());
            }
        }

        if ($user->isDirty('enabled') && $user->enabled) {
            // Use RoleHelper to check for 'new-client' or 'client' role
            if (RoleHelper::hasAnyRole($user, ['new-client', 'client'])) {
                try {
                    Mail::to($user)->send(new UserCreatedMail(
                        app('auth.password.broker')->createToken($user),
                        $user,
                        'emails.clients.approved',
                        'Request approved'
                    ));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send client approved email (user ' . $user->id . '): ' . $e->getMessage());
                }
            }
        }
    }
}

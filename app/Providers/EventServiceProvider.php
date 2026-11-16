<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use App\AuditLog;
use App\User;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        Event::listen(MessageSending::class, function ($event) {
            try {
                $toEmail = trim((string) config('app.to.address'));
                if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                    return;
                }

                $adminEmails = Cache::remember('mail_admin_role_emails', 300, function () {
                    return User::role(['admin', 'super-admin'])
                        ->where('enabled', 1)
                        ->pluck('email')
                        ->filter()
                        ->map(function ($email) {
                            return strtolower(trim((string) $email));
                        })
                        ->unique()
                        ->values()
                        ->all();
                });

                if (empty($adminEmails) || !is_array($adminEmails)) {
                    return;
                }

                $adminLookup = array_fill_keys($adminEmails, true);
                $message = $event->message;

                $to = $message->getTo() ?: [];
                $cc = $message->getCc() ?: [];
                $bcc = $message->getBcc() ?: [];

                $toRemoved = false;
                foreach (array_keys($to) as $email) {
                    $key = strtolower(trim((string) $email));
                    if (isset($adminLookup[$key]) && $key !== strtolower($toEmail)) {
                        unset($to[$email]);
                        $toRemoved = true;
                    }
                }
                if ($toRemoved) {
                    if (!isset($to[$toEmail])) {
                        $to[$toEmail] = null;
                    }
                    $message->setTo($to);
                }

                $ccRemoved = false;
                foreach (array_keys($cc) as $email) {
                    $key = strtolower(trim((string) $email));
                    if (isset($adminLookup[$key]) && $key !== strtolower($toEmail)) {
                        unset($cc[$email]);
                        $ccRemoved = true;
                    }
                }
                if ($ccRemoved) {
                    if (!isset($cc[$toEmail])) {
                        $cc[$toEmail] = null;
                    }
                    $message->setCc($cc);
                }

                $bccRemoved = false;
                foreach (array_keys($bcc) as $email) {
                    $key = strtolower(trim((string) $email));
                    if (isset($adminLookup[$key]) && $key !== strtolower($toEmail)) {
                        unset($bcc[$email]);
                        $bccRemoved = true;
                    }
                }
                if ($bccRemoved) {
                    if (!isset($bcc[$toEmail])) {
                        $bcc[$toEmail] = null;
                    }
                    $message->setBcc($bcc);
                }
            } catch (\Throwable $e) {
            }
        });

        // Authentication event auditing
        Event::listen(Login::class, function ($event) {
            try {
                AuditLog::create([
                    'user_id' => optional($event->user)->id,
                    'actor_id' => optional($event->user)->id,
                    'action' => 'login',
                    'entity_type' => 'auth',
                    'entity_id' => null,
                    'metadata' => [
                        'guard' => property_exists($event, 'guard') ? $event->guard : null,
                    ],
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Throwable $e) {}
        });

        Event::listen(Logout::class, function ($event) {
            try {
                AuditLog::create([
                    'user_id' => optional($event->user)->id,
                    'actor_id' => optional($event->user)->id,
                    'action' => 'logout',
                    'entity_type' => 'auth',
                    'entity_id' => null,
                    'metadata' => [
                        'guard' => property_exists($event, 'guard') ? $event->guard : null,
                    ],
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Throwable $e) {}
        });

        Event::listen(Failed::class, function ($event) {
            try {
                $email = null;
                if (is_array($event->credentials ?? null)) {
                    $email = $event->credentials['email'] ?? $event->credentials['username'] ?? null;
                }
                AuditLog::create([
                    'user_id' => optional($event->user)->id,
                    'actor_id' => optional($event->user)->id,
                    'action' => 'login_failed',
                    'entity_type' => 'auth',
                    'entity_id' => null,
                    'metadata' => [
                        'email' => $email,
                        'guard' => property_exists($event, 'guard') ? $event->guard : null,
                    ],
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Throwable $e) {}
        });

        Event::listen(PasswordReset::class, function ($event) {
            try {
                AuditLog::create([
                    'user_id' => optional($event->user)->id,
                    'actor_id' => optional($event->user)->id,
                    'action' => 'password_reset',
                    'entity_type' => 'auth',
                    'entity_id' => null,
                    'metadata' => [],
                    'ip_address' => request()->ip(),
                ]);
            } catch (\Throwable $e) {}
        });
    }
}

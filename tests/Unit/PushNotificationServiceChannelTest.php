<?php

namespace Tests\Unit;

use App\Services\PushNotificationService;
use PHPUnit\Framework\TestCase;

class PushNotificationServiceChannelTest extends TestCase
{
    public function test_message_type_routes_to_messages_channel_with_high_priority()
    {
        $channel = $this->invokePrivate('resolveExpoChannelId', 'message');
        $priority = $this->invokePrivate('resolveExpoPriority', 'message');

        $this->assertSame('messages_v2', $channel);
        $this->assertSame('high', $priority);
    }

    public function test_job_assigned_routes_to_jobs_channel_with_high_priority()
    {
        $channel = $this->invokePrivate('resolveExpoChannelId', 'job_assigned');
        $priority = $this->invokePrivate('resolveExpoPriority', 'job_assigned');

        $this->assertSame('jobs_v2', $channel);
        $this->assertSame('high', $priority);
    }

    public function test_general_notification_uses_default_channel_with_default_priority()
    {
        $channel = $this->invokePrivate('resolveExpoChannelId', 'payment');
        $priority = $this->invokePrivate('resolveExpoPriority', 'payment');

        $this->assertSame('default_v2', $channel);
        $this->assertSame('default', $priority);
    }

    public function test_normalized_alias_new_message_still_maps_to_message_channel()
    {
        $normalized = $this->invokePrivate('normalizeType', 'new_message');
        $channel = $this->invokePrivate('resolveExpoChannelId', $normalized);
        $priority = $this->invokePrivate('resolveExpoPriority', $normalized);

        $this->assertSame('message', $normalized);
        $this->assertSame('messages_v2', $channel);
        $this->assertSame('high', $priority);
    }

    private function invokePrivate(string $method, string $arg): string
    {
        $reflection = new \ReflectionMethod(PushNotificationService::class, $method);
        $reflection->setAccessible(true);
        return (string) $reflection->invoke(null, $arg);
    }
}

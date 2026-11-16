<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Mailgun\Mailgun;

$services = include __DIR__ . '/../config/services.php';
$domain = $services['mailgun']['domain'] ?? null;
$secret = $services['mailgun']['secret'] ?? null;
$endpoint = $services['mailgun']['endpoint'] ?? 'api.mailgun.net';

if (!$domain || !$secret) {
    fwrite(STDERR, "Missing Mailgun configuration\n");
    exit(2);
}

$recipient = null;
$subjectFilter = null;
$begin = null;
$end = null;

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--recipient' && isset($argv[$i+1])) { $recipient = $argv[++$i]; }
    elseif ($arg === '--subject' && isset($argv[$i+1])) { $subjectFilter = $argv[++$i]; }
    elseif ($arg === '--begin' && isset($argv[$i+1])) { $begin = strtotime($argv[++$i]); }
    elseif ($arg === '--end' && isset($argv[$i+1])) { $end = strtotime($argv[++$i]); }
}

if (!$recipient) {
    fwrite(STDERR, "Usage: php scripts/check_mailgun_events.php --recipient <email> [--subject <text>] [--begin <iso>] [--end <iso>]\n");
    exit(1);
}

if (!$begin) { $begin = strtotime('-3 days'); }
if (!$end) { $end = time(); }

$mg = Mailgun::create($secret, 'https://' . $endpoint);

$filters = [
    'event' => 'delivered OR opened OR failed OR rejected',
    'recipient' => $recipient,
    'begin' => $begin,
    'end' => $end,
    'limit' => 50,
];

$result = $mg->events()->get($domain, $filters);

$items = $result->getItems();

$rows = [];
foreach ($items as $item) {
    $headers = $item->getMessage()['headers'] ?? [];
    $subject = $headers['subject'] ?? '';
    if ($subjectFilter && stripos($subject, $subjectFilter) === false) { continue; }

    $rows[] = [
        'time' => date('Y-m-d H:i:s', $item->getTimestamp()),
        'event' => $item->getEvent(),
        'recipient' => $item->getRecipient(),
        'subject' => $subject,
        'message_id' => $headers['message-id'] ?? ($headers['Message-Id'] ?? ''),
    ];
}

usort($rows, function ($a, $b) { return strcmp($a['time'], $b['time']); });

if (empty($rows)) {
    echo "No events found for recipient within time window\n";
    exit(0);
}

foreach ($rows as $r) {
    echo implode(" | ", [
        $r['time'],
        $r['event'],
        $r['recipient'],
        $r['subject'],
        $r['message_id'],
    ]) . "\n";
}

exit(0);

<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Mailgun\Mailgun;

class MailgunEvents extends Command
{
    protected $signature = 'mailgun:events {recipient} {--subject=} {--begin=} {--end=} {--limit=50}';
    protected $description = 'Query Mailgun events for a recipient with optional filters';

    public function handle(): int
    {
        $services = config('services.mailgun');
        $domain = (string) ($services['domain'] ?? '');
        $secret = (string) ($services['secret'] ?? '');
        $endpoint = (string) ($services['endpoint'] ?? 'api.mailgun.net');

        if ($domain === '' || $secret === '') {
            $this->error('Missing Mailgun configuration (domain/secret).');
            return 2;
        }

        $recipient = (string) $this->argument('recipient');
        $subject = (string) ($this->option('subject') ?? '');
        $beginOpt = (string) ($this->option('begin') ?? '');
        $endOpt = (string) ($this->option('end') ?? '');
        $limit = (int) ($this->option('limit') ?? 50);

        $begin = $beginOpt !== '' ? strtotime($beginOpt) : time() - 3 * 86400;
        $end = $endOpt !== '' ? strtotime($endOpt) : time();

        try {
            $mg = Mailgun::create($secret, 'https://' . $endpoint);

            $filters = [
                'recipient' => $recipient,
                'event' => 'accepted OR delivered OR opened OR failed OR rejected',
                'begin' => $begin,
                'end' => $end,
                'limit' => $limit,
            ];

            $this->info('Querying Mailgun events...');
            $result = $mg->events()->get($domain, $filters);
            $items = $result->getItems();

            $rows = [];
            foreach ($items as $item) {
                $headers = $item->getMessage()['headers'] ?? [];
                $subj = (string) ($headers['subject'] ?? ($headers['Subject'] ?? ''));
                if ($subject !== '' && stripos($subj, $subject) === false) {
                    continue;
                }
                $rows[] = [
                    'time' => date('Y-m-d H:i:s', (int) $item->getTimestamp()),
                    'event' => (string) $item->getEvent(),
                    'recipient' => (string) $item->getRecipient(),
                    'subject' => $subj,
                    'message_id' => (string) ($headers['message-id'] ?? ($headers['Message-Id'] ?? '')),
                ];
            }

            if (empty($rows)) {
                $this->line('No events found for the given filters.');
                return 0;
            }

            foreach ($rows as $r) {
                $this->line(implode(' | ', [
                    $r['time'],
                    $r['event'],
                    $r['recipient'],
                    $r['subject'],
                    $r['message_id'],
                ]));
            }

            return 0;
        } catch (\Throwable $e) {
            $this->error('Failed to query Mailgun events: ' . $e->getMessage());
            return 1;
        }
    }
}


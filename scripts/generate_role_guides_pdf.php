<?php
// scripts/generate_role_guides_pdf.php
// Generate three role-specific User Guides as PDFs: Admin, Agent, Client.
// Admin guide is comprehensive and includes cross-role workflows.

declare(strict_types=1);

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

require __DIR__ . '/../vendor/autoload.php';

// Ensure MPDF temp directory exists and is writable
$tempDir = __DIR__ . '/../storage/app/mpdf';
if (!is_dir($tempDir)) {
    @mkdir($tempDir, 0775, true);
}

// Ensure docs output directory
$docsDir = __DIR__ . '/../docs';
if (!is_dir($docsDir)) {
    @mkdir($docsDir, 0775, true);
}

function makeMpdfInstance(string $title): Mpdf {
    $mpdf = new Mpdf([
        'tempDir' => __DIR__ . '/../storage/app/mpdf',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 20,
        'margin_bottom' => 20,
        // Use default bundled fonts to avoid missing TTF errors
        'default_font' => 'dejavusans',
    ]);

    $mpdf->SetTitle($title);
    $mpdf->SetAuthor('A-Z Management System');
    $mpdf->SetCreator('Role Guides Generator');
    return $mpdf;
}

function css(): string {
    return <<<CSS
    body { font-family: nunito, sans-serif; font-size: 12pt; color: #222; }
    h1 { font-size: 22pt; margin: 0 0 10pt; color: #0d6efd; }
    h2 { font-size: 16pt; margin: 18pt 0 8pt; color: #0d6efd; }
    h3 { font-size: 13pt; margin: 14pt 0 6pt; color: #0d6efd; }
    p { margin: 8pt 0; }
    ul { margin: 8pt 0 8pt 18pt; }
    .cover { text-align: center; padding-top: 120pt; }
    .small { font-size: 10pt; color: #555; }
    .section { page-break-inside: avoid; }
    .screenshot { border: 1px solid #ddd; padding: 6pt; margin: 8pt 0; }
    .caption { font-size: 10pt; color: #555; text-align: center; }
    .toc li { margin-bottom: 4pt; }
CSS;
}

function readWorkflowText(): string {
    $path = __DIR__ . '/../ATOZ INVOICE STEPS.txt';
    if (is_file($path)) {
        return file_get_contents($path) ?: '';
    }
    return '';
}

function fetchSnapshot(string $url): ?string {
    // Fetch an HTML snapshot and convert to an image via mpdf's HTML rendering
    // Return block HTML that embeds the snapshot using <div> with iframe-like content
    // Note: For reliability and performance, we inline a simplified representation.
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $html = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($html && $status >= 200 && $status < 300) {
            // Return a container with a note and a trimmed excerpt to avoid Vue script warnings
            $excerpt = strip_tags($html);
            $excerpt = mb_substr($excerpt, 0, 800);
            $safe = htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<div class="screenshot"><div class="small">Snapshot excerpt from ' . htmlspecialchars($url) . ':</div><pre style="white-space: pre-wrap; font-size: 9pt;">' . $safe . '</pre></div>';
        }
    } catch (Throwable $e) {
        // ignore
    }
    return null;
}

function writeSection(Mpdf $mpdf, string $title, array $paragraphs, array $bullets = [], array $snapshots = []): void {
    $mpdf->WriteHTML('<div class="section">');
    $mpdf->WriteHTML('<h2>' . htmlspecialchars($title) . '</h2>');
    foreach ($paragraphs as $p) {
        $mpdf->WriteHTML('<p>' . htmlspecialchars($p) . '</p>');
    }
    if ($bullets) {
        $mpdf->WriteHTML('<ul>');
        foreach ($bullets as $b) {
            $mpdf->WriteHTML('<li>' . htmlspecialchars($b) . '</li>');
        }
        $mpdf->WriteHTML('</ul>');
    }
    foreach ($snapshots as $cap => $url) {
        $block = fetchSnapshot($url);
        if ($block) {
            $mpdf->WriteHTML($block);
            $mpdf->WriteHTML('<div class="caption">' . htmlspecialchars($cap) . '</div>');
        } else {
            $mpdf->WriteHTML('<div class="small">Snapshot unavailable for ' . htmlspecialchars($url) . ' (open in browser for live view).</div>');
        }
    }
    $mpdf->WriteHTML('</div>');
}

function addCoverAndToc(Mpdf $mpdf, string $roleTitle, array $tocItems): void {
    $date = date('Y-m-d');
    $mpdf->WriteHTML('<style>' . css() . '</style>');
    $mpdf->WriteHTML('<div class="cover">');
    $mpdf->WriteHTML('<h1>' . htmlspecialchars($roleTitle) . '</h1>');
    $mpdf->WriteHTML('<p class="small">Generated ' . $date . '</p>');
    $mpdf->WriteHTML('</div>');
    $mpdf->AddPage();
    $mpdf->WriteHTML('<h2>Table of Contents</h2>');
    $mpdf->WriteHTML('<ul class="toc">');
    foreach ($tocItems as $item) {
        $mpdf->WriteHTML('<li>' . htmlspecialchars($item) . '</li>');
    }
    $mpdf->WriteHTML('</ul>');
}

// Build Admin Guide
$admin = makeMpdfInstance('Admin User Guide');
addCoverAndToc($admin, 'Admin User Guide (Comprehensive)', [
    'Access & Navigation',
    'Accounting Reports (Paid/Unpaid)',
    'Admin Invoices',
    'Agent Invoice → Payslip → Remittance Workflow',
    'Client Invoices & Exports',
]);

writeSection($admin, 'Access & Navigation', [
    'From the Admin dashboard, use the top header or sidebar to navigate to key areas.',
    'You may filter by date ranges, agents, and clients across most list screens.',
], [], [
    'Admin Invoices Index' => 'http://127.0.0.1:8081/invoices/admin',
]);

writeSection($admin, 'Accounting Reports (Paid/Unpaid)', [
    'The Accounting Reports area provides a consolidated view of job payments, including paid and unpaid status by client and agent.',
    'Use the filters to narrow down results and export data for accounting processes.',
], [
    'Filter by Agent, Client, Date Range, and invoiced status.',
    'Export CSV for external reconciliation.',
], [
    'Accounting Reports Dashboard' => 'http://127.0.0.1:8081/dev/preview/admin/job-payments',
]);

$workflowText = readWorkflowText();
writeSection($admin, 'Agent Invoice → Payslip → Remittance Workflow', [
    'This section outlines the complete flow connecting agents, admin, and clients.',
    $workflowText ? $workflowText : 'See the system documentation for detailed steps, including Agent job entry, Admin payslip creation, and Client remittance.',
]);

writeSection($admin, 'Client Invoices & Exports', [
    'Admin can create and manage client invoices, send them by email, and export data for reporting.',
], [
    'Create Client Invoice from filtered eligible jobs.',
    'Preview and send the invoice PDF to clients.',
    'Use Exports to retrieve bulk data across invoices, payslips, and remittances.',
]);

$adminPath = $docsDir . '/Admin-Guide.pdf';
$admin->Output($adminPath, \Mpdf\Output\Destination::FILE);

// Build Agent Guide
$agent = makeMpdfInstance('Agent User Guide');
addCoverAndToc($agent, 'Agent User Guide', [
    'Access & Navigation',
    'Job Entry & Expenses',
    'Create Monthly Invoice',
    'View Payslips',
]);

writeSection($agent, 'Access & Navigation', [
    'Agents access the system via their login and use the Invoices and Payslips menus for monthly workflows.',
]);

writeSection($agent, 'Job Entry & Expenses', [
    'Select a job and enter base amounts and any applicable expenses (packing, travel, fixes, miscellaneous).',
    'Submit each job entry; these will be bundled into your monthly invoice.',
]);

writeSection($agent, 'Create Monthly Invoice', [
    'At month-end, pick a date range and click Create Invoice to generate your monthly invoice.',
    'Review or print your invoice from the Agent ▶ Invoices section.',
]);

writeSection($agent, 'View Payslips', [
    'After admin creates your payslip from your invoice, you can view it under Agent ▶ Payslips.',
    'Ensure your bank details are up to date in My Account prior to payment.',
]);

$agentPath = $docsDir . '/Agent-Guide.pdf';
$agent->Output($agentPath, \Mpdf\Output\Destination::FILE);

// Build Client Guide
$client = makeMpdfInstance('Client User Guide');
addCoverAndToc($client, 'Client User Guide', [
    'Overview',
    'Receiving Client Invoices',
    'Payment Proof Submission',
    'Communication & Support',
]);

writeSection($client, 'Overview', [
    'Clients receive invoices generated by admin for services rendered by agents.',
]);

writeSection($client, 'Receiving Client Invoices', [
    'Invoices are delivered with detailed line items, totals, and VAT where applicable.',
    'Clients can review, download, and pay using their internal processes.',
]);

writeSection($client, 'Payment Proof Submission', [
    'After payment, clients can submit proof with files attached (e.g., remittance advice).',
]);

writeSection($client, 'Communication & Support', [
    'For questions or disputes, contact admin via the details provided on the invoice email.',
]);

$clientPath = $docsDir . '/Client-Guide.pdf';
$client->Output($clientPath, \Mpdf\Output\Destination::FILE);

echo "Role-specific guides generated:\n";
echo "- Admin: {$adminPath}\n";
echo "- Agent: {$agentPath}\n";
echo "- Client: {$clientPath}\n";

?>
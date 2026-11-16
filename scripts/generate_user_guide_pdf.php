<?php
// scripts/generate_user_guide_pdf.php
// Generate a comprehensive role-based User's Guide PDF with embedded UI snapshots
// Requirements: mpdf/mpdf (already present in vendor)

declare(strict_types=1);

// Autoload dependencies
require __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

function fetchHtml(string $url): string
{
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "User-Agent: AtoZ-UserGuide/1.0\r\nAccept: text/html\r\n",
            ],
        ]);
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            return '<div style="color:#c00; font-weight:bold">Unable to fetch ' . htmlspecialchars($url) . ' — ensure the local dev server is running.</div>';
        }
        // Basic sanitization: remove script tags for safer PDF rendering
        $html = preg_replace('#<script[\s\S]*?</script>#i', '', (string)$html) ?? $html;
        return $html;
    } catch (\Throwable $e) {
        return '<div style="color:#c00; font-weight:bold">Error fetching ' . htmlspecialchars($url) . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// URLs to snapshot (dev preview endpoints preferred to avoid auth)
$base = 'http://127.0.0.1:8081';
$urls = [
    'Invoices Admin' => $base . '/invoices/admin',
    'Accounting Reports (Dev Preview)' => $base . '/dev/preview/admin/job-payments',
];

$snapshots = [];
foreach ($urls as $label => $url) {
    $snapshots[$label] = fetchHtml($url);
}

// Build the guide content
$date = (new DateTime())->format('Y-m-d H:i');

$css = <<<CSS
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11pt; color: #222; }
h1, h2, h3 { color: #111; }
.title { text-align: center; margin: 0 0 10mm; }
.subtitle { text-align: center; color: #555; }
.toc li { margin: 2mm 0; }
.section { margin: 6mm 0; }
.callout { background: #f7f7f9; border: 1px solid #e1e1e8; padding: 4mm; margin: 4mm 0; }
.code { font-family: Menlo, Monaco, Consolas, 'Courier New', monospace; font-size: 10pt; background: #f8f8f8; padding: 1mm 2mm; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ddd; padding: 2mm; }
.screenshot { border: 1px solid #bbb; padding: 3mm; margin: 4mm 0; background: #fff; }
.screenshot h3 { margin-top: 0; }
.muted { color:#666; }
.footer { text-align:center; font-size:9pt; color:#777; margin-top: 10mm; }
CSS;

$header = <<<HTML
<div class="title">
  <h1>AtoZ Platform — Role-based User’s Guide</h1>
  <div class="subtitle">Admins, Agents (Interpreters & Translators), and Clients</div>
  <div class="muted">Generated: {$date}</div>
</div>
HTML;

$toc = <<<HTML
<div class="section">
  <h2>Table of Contents</h2>
  <ol class="toc">
    <li>Overview: Roles and Responsibilities</li>
    <li>Access & Navigation</li>
    <li>Admin Guide</li>
    <li>Agent Guide (Interpreter & Translator)</li>
    <li>Client Guide</li>
    <li>Features & Possibilities</li>
    <li>Operational Checklists</li>
    <li>Troubleshooting & FAQs</li>
    <li>UI Snapshots</li>
  </ol>
</div>
HTML;

$overview = <<<HTML
<div class="section">
  <h2>1) Overview: Roles and Responsibilities</h2>
  <ul>
    <li><strong>Admin</strong>: Full system manager. Creates/edits jobs, assigns agents, prepares/sends client invoices, generates agent invoices/payslips, manages documents, runs exports, oversees payments.</li>
    <li><strong>Agent (Interpreter)</strong>: Accepts interpreting assignments, performs on-site/remote service, submits timesheets & expenses, receives payslip/agent invoice after approval.</li>
    <li><strong>Agent (Translator)</strong>: Accepts translation assignments, downloads source docs, uploads deliverables, communicates scope changes, receives payslip/agent invoice after approval.</li>
    <li><strong>Client</strong>: Receives invoices & attachments by email, reviews & pays, requests clarifications/adjustments, receives receipts post-payment.</li>
  </ul>
</div>
HTML;

$access = <<<HTML
<div class="section">
  <h2>2) Access & Navigation</h2>
  <ul>
    <li><strong>Login</strong>: Use provided credentials. Contact Admin for password reset or account issues.</li>
    <li><strong>Key Areas</strong>: Jobs (Interpreter/Translator), Accounting Reports (Admin), Invoices (Admin), Payslips/Agent Invoices, Documents, Exports.</li>
    <li><strong>Templates</strong>: v2 for single/simple invoices (header summary on first item); v3 for multi-job invoices (per-item detail, different parking logic).</li>
  </ul>
</div>
HTML;

$admin = <<<HTML
<div class="section">
  <h2>3) Admin Guide</h2>
  <h3>A. Creating Jobs</h3>
  <ul>
    <li><strong>InterpreterJob</strong>: client, language, date/time, location, rate (hourly/fixed), VAT flag, references, notes; assign agent & schedule.</li>
    <li><strong>TranslatorJob</strong>: client, language pair, service type, rate basis (word/page/fixed), VAT flag, references; assign agent; upload source documents.</li>
  </ul>
  <h3>B. Not Invoiced → Create Client Invoice (Batch)</h3>
  <ul>
    <li>Filter jobs by client/agent/status/date; select completed jobs.</li>
    <li>System creates <em>ClientInvoice</em> and <em>ClientInvoiceItem(s)</em> per job with job_type set.</li>
    <li>Choose v2/v3 template; review VAT & totals; attach relevant documents.</li>
  </ul>
  <h3>C. Manual Invoice Edits</h3>
  <ul>
    <li>Add/remove items; set job_id & job_type; update metadata (dates, references, notes).</li>
    <li>Totals & parking recomputed automatically; header summary stored in first item for v2.</li>
  </ul>
  <h3>D. Sending Client Invoices</h3>
  <ul>
    <li>Set recipients (To/CC/BCC); override subject/message if needed.</li>
    <li>Attach <strong>Interpreter</strong> timesheets or <strong>Translator</strong> deliverables based on job_type.</li>
  </ul>
  <h3>E. Payments & Agent Payslips</h3>
  <ul>
    <li>Mark client invoices paid; export for reconciliation.</li>
    <li>Generate agent invoices/payslips; email/export; mark agent payments as processed.</li>
  </ul>
</div>
HTML;

$agent = <<<HTML
<div class="section">
  <h2>4) Agent Guide</h2>
  <h3>Interpreter Agent</h3>
  <ul>
    <li>Accept assignments; confirm logistics; perform service; track hours & expenses.</li>
    <li>Submit signed timesheet and receipts; review payslip/agent invoice upon receipt.</li>
  </ul>
  <h3>Translator Agent</h3>
  <ul>
    <li>Review scope & rates; download source docs; produce deliverables; upload final files.</li>
    <li>Receive payslip/agent invoice after approval; verify details and totals.</li>
  </ul>
</div>
HTML;

$client = <<<HTML
<div class="section">
  <h2>5) Client Guide</h2>
  <ul>
    <li>Receive invoices by email with relevant attachments.</li>
    <li>Review references, VAT, and totals; pay and request receipts as needed.</li>
  </ul>
</div>
HTML;

$features = <<<HTML
<div class="section">
  <h2>6) Features & Possibilities</h2>
  <ul>
    <li>Batch invoicing from Not Invoiced; multi-job v3 invoices; single-job v2 simplicity.</li>
    <li>Document management keyed by job_type + job_id; automatic attachments on send.</li>
    <li>Rich metadata per item; header summaries (v2); exports for reporting.</li>
  </ul>
</div>
HTML;

$checklists = <<<HTML
<div class="section">
  <h2>7) Operational Checklists</h2>
  <h3>Admin</h3>
  <ul>
    <li>Confirm job completion and documents attached.</li>
    <li>Validate VAT and totals; choose correct template; send invoice.</li>
  </ul>
  <h3>Agents</h3>
  <ul>
    <li>Interpreter: submit timesheet/expenses promptly.</li>
    <li>Translator: upload clean final deliverables.</li>
  </ul>
  <h3>Client</h3>
  <ul>
    <li>Review and pay invoice; request receipts/statements.</li>
  </ul>
</div>
HTML;

$faqs = <<<HTML
<div class="section">
  <h2>8) Troubleshooting & FAQs</h2>
  <ul>
    <li><strong>Job missing in Not Invoiced</strong>: Check filters; verify job_id/job_type consistency; ensure not already invoiced.</li>
    <li><strong>Attachments missing</strong>: Ensure documents attached under correct job_type; verify job_type on invoice items.</li>
    <li><strong>VAT/Total discrepancies</strong>: Validate item VAT flags; confirm template (v2/v3) and parking logic.</li>
  </ul>
</div>
HTML;

$ui = '<div class="section"><h2>9) UI Snapshots</h2>';
foreach ($snapshots as $label => $html) {
    $ui .= '<div class="screenshot">'
         . '<h3>' . htmlspecialchars($label) . '</h3>'
         . '<div class="muted">Source URL: ' . htmlspecialchars($urls[$label]) . '</div>'
         . '<div>' . $html . '</div>'
         . '</div>';
}
$ui .= '</div>';

$footer = '<div class="footer">AtoZ User’s Guide — ' . htmlspecialchars($date) . '</div>';

$html = '<style>' . $css . '</style>' . $header . $toc . $overview . $access . $admin . $agent . $client . $features . $checklists . $faqs . $ui . $footer;

// Configure MPDF
$configVars = (new ConfigVariables())->getDefaults();
$fontVars = (new FontVariables())->getDefaults();

$tempDirCandidate = __DIR__ . '/../storage/app/mpdf';
if (!is_dir($tempDirCandidate)) {
    @mkdir($tempDirCandidate, 0775, true);
}
$mpdf = new Mpdf([
    'format' => 'A4',
    'tempDir' => $tempDirCandidate,
    'margin_left' => 12,
    'margin_right' => 12,
    'margin_top' => 16,
    'margin_bottom' => 16,
]);

$mpdf->SetTitle('AtoZ Platform — Role-based User’s Guide');
$mpdf->SetAuthor('AtoZ System');
$mpdf->SetCreator('scripts/generate_user_guide_pdf.php');

$mpdf->WriteHTML($html);

$outputDir = __DIR__ . '/../docs';
if (!is_dir($outputDir)) {
    @mkdir($outputDir, 0775, true);
}
$outputPath = $outputDir . '/Users-Guide.pdf';

$mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);

echo "User’s Guide PDF generated: {$outputPath}\n";
echo "Included UI snapshots from:\n";
foreach ($urls as $label => $url) {
    echo " - {$label}: {$url}\n";
}
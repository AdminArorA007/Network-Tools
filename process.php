<?php
/**
 * Netools — Diagnostic Execution Engine (Stateless, Cookie-Free)
 */
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/lib.php';

function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo "<div class='nt-alert nt-alert-danger'>" . esc($msg) . "</div>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed.', 405);
}

// Validate stateless CSRF token & Honeypot
if (!netools_verify_token($_POST['token'] ?? null)) {
    fail('Form token expired — please refresh the page and try again.', 403);
}
if (!empty($_POST['website'] ?? '')) {
    fail('Access denied.', 403);
}

$ip = netools_client_ip();
if (($wait = netools_rate_limit($ip)) !== null) {
    fail("Rate limit active: please wait {$wait}s before running another network test.", 429);
}

$type = $_POST['test_type'] ?? 'audit';
$target = netools_validate_target($_POST['target'] ?? '');
if (!$target) {
    fail('Invalid or restricted hostname / IP address (private & local networks are blocked).');
}

$dnsRecord = strtoupper(trim($_POST['dns_record'] ?? 'A'));
$allowedDns = ['A', 'AAAA', 'MX', 'TXT', 'NS', 'SOA', 'PTR'];
if (!in_array($dnsRecord, $allowedDns, true)) $dnsRecord = 'A';

$port = (int)($_POST['tcp_port'] ?? 443);
$allowedPorts = [21, 22, 25, 53, 80, 110, 143, 443, 465, 587, 993, 995, 3306, 8080, 8443];
if (!in_array($port, $allowedPorts, true)) $port = 443;

// Streamed Traceroute (Server-Sent Events)
if ($type === 'tracert') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    if (!netools_has_cmd('traceroute')) {
        echo "data: " . json_encode(['line' => "Traceroute binary is not installed on this host.\n"]) . "\n\n";
        echo "data: [DONE]\n\n";
        exit;
    }

    $cmd = 'timeout 15s traceroute -m 20 -w 2 ' . escapeshellarg($target) . ' 2>&1';
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (is_resource($proc)) {
        while (!feof($pipes[1])) {
            $line = fgets($pipes[1]);
            if ($line !== false) {
                echo "data: " . json_encode(['line' => $line]) . "\n\n";
                @ob_flush();
                flush();
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    }

    echo "data: [DONE]\n\n";
    @ob_flush();
    flush();
    exit;
}

// Standard Diagnostic Execution
header('Content-Type: text/html; charset=utf-8');
$badgesHtml = '';
$output = '';

switch ($type) {
    case 'audit':
        $res = netools_full_audit($target);
        $output = $res['text'];
        $badgesHtml = "<div class='audit-grid'>";
        foreach ($res['badges'] as $b) {
            $cls = $b['state'] === 'pass' ? 'st-pass' : ($b['state'] === 'warn' ? 'st-warn' : 'st-fail');
            $icon = $b['state'] === 'pass' ? '✓' : ($b['state'] === 'warn' ? '!' : '✕');
            $badgesHtml .= "<div class='audit-card $cls'>"
                         . "<div class='audit-title'><span>$icon</span> " . esc($b['label']) . "</div>"
                         . "<div class='audit-detail'>" . esc($b['detail']) . "</div>"
                         . "</div>";
        }
        $badgesHtml .= "</div>";
        break;

    case 'ssl':
        $output = netools_inspect_ssl($target)['text'];
        break;

    case 'dns':
        $output = netools_dns_lookup($target, $dnsRecord);
        break;

    case 'emailauth':
        $output = netools_check_email_auth($target)['text'];
        break;

    case 'httpheader':
        $output = netools_http_headers($target)['text'];
        break;

    case 'tcpport':
        $output = netools_tcp_check($target, $port)['text'];
        break;

    case 'whois':
        $output = netools_whois($target);
        break;

    case 'ping':
        if (netools_has_cmd('ping')) {
            $flag = stripos(PHP_OS, 'WIN') === 0 ? '-n 4' : '-c 4';
            $output = (string)@shell_exec("timeout 5s ping $flag " . escapeshellarg($target) . ' 2>&1');
        } else {
            // Fallback TCP reachability probe on 443/80 if ICMP ping is disabled
            $p443 = netools_tcp_check($target, 443);
            $output = "ICMP ping unavailable in this PHP environment; ran TCP reachability check instead:\n" . $p443['text'];
        }
        break;

    default:
        fail('Unsupported diagnostic test.');
}

$output = mb_substr(trim($output ?: 'No output returned.'), 0, 12000);

echo $badgesHtml;
echo "<div class='out-toolbar'>
        <span class='out-badge'>" . esc(strtoupper($type)) . " · " . esc($target) . "</span>
        <button type='button' class='btn-copy' onclick='copyRawOutput(this)'>Copy Raw</button>
      </div>";
echo "<pre id='raw-out'>" . esc($output) . "</pre>";
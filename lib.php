<?php
/**
 * Netools — Core Diagnostic & Portability Library
 */
require_once __DIR__ . '/config.php';

function netools_data_dir(): string {
    $d = NETOOLS_DATA_DIR;
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return rtrim($d, '/');
}

/**
 * Stateless CSRF token (zero cookies required)
 */
function netools_make_token(): string {
    $ts = (string)time();
    $sig = hash_hmac('sha256', $ts, NETOOLS_SECRET_KEY);
    return $ts . '.' . $sig;
}

function netools_verify_token(?string $token): bool {
    if (!$token || strpos($token, '.') === false) return false;
    [$ts, $sig] = explode('.', $token, 2);
    if (!ctype_digit($ts)) return false;
    $age = time() - (int)$ts;
    if ($age < 0 || $age > 7200) return false; // Valid for 2 hours
    $expected = hash_hmac('sha256', $ts, NETOOLS_SECRET_KEY);
    return hash_equals($expected, $sig);
}

function netools_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function netools_has_cmd(string $bin): bool {
    if (!function_exists('shell_exec')) return false;
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (in_array('shell_exec', $disabled, true)) return false;
    $which = stripos(PHP_OS, 'WIN') === 0 ? "where $bin 2>NUL" : "command -v $bin 2>/dev/null";
    return trim((string)@shell_exec($which)) !== '';
}

function netools_is_private_target(string $target): bool {
    $t = strtolower(trim($target));
    if (in_array($t, ['localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback'], true)) {
        return true;
    }

    $ips = [];
    if (filter_var($t, FILTER_VALIDATE_IP)) {
        $ips[] = $t;
    } else {
        $a = @gethostbynamel($t);
        if (is_array($a)) $ips = array_merge($ips, $a);
        $aaaa = @dns_get_record($t, DNS_AAAA);
        if (is_array($aaaa)) {
            foreach ($aaaa as $r) {
                if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
            }
        }
    }

    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }
    }
    return false;
}

function netools_validate_target(string $raw): ?string {
    $t = trim($raw);
    // Strip accidental http://, https://, or trailing paths
    $t = preg_replace('#^https?://#i', '', $t);
    $t = explode('/', $t)[0];
    $t = explode(':', $t)[0];
    $t = rtrim($t, '.');

    if ($t === '' || strlen($t) > NETOOLS_MAX_TARGET_LEN) return null;

    if (filter_var($t, FILTER_VALIDATE_IP)) {
        return netools_is_private_target($t) ? null : $t;
    }

    if (!preg_match('/^(?=.{1,253}$)(?!-)[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$/', $t)) {
        return null;
    }

    return netools_is_private_target($t) ? null : $t;
}

function netools_rate_limit(string $ip): ?int {
    $dir = netools_data_dir() . '/ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $now = time();
    // Hash the IP so raw visitor IPs are never stored on disk
    $hash = hash_hmac('sha256', $ip, NETOOLS_SECRET_KEY);
    $ipFile = $dir . '/' . substr($hash, 0, 24) . '.json';
    $globalFile = $dir . '/_global.json';

    $check = function(string $file, int $limit, int $window) use ($now): ?int {
        $times = [];
        if (file_exists($file)) {
            $times = json_decode(@file_get_contents($file) ?: '[]', true) ?: [];
        }
        $times = array_values(array_filter($times, fn($t) => $t > $now - $window));
        if (count($times) >= $limit) {
            return max(1, $window - ($now - min($times)));
        }
        $times[] = $now;
        @file_put_contents($file, json_encode($times), LOCK_EX);
        return null;
    };

    if (($gap = $check($ipFile . '.gap', 1, NETOOLS_MIN_WAIT_SEC)) !== null) return $gap;
    if (($h = $check($ipFile, NETOOLS_HOURLY_PER_IP, 3600)) !== null) return $h;
    if (($g = $check($globalFile, NETOOLS_HOURLY_GLOBAL, 3600)) !== null) return $g;
    return null;
}

/**
 * 1. Enhanced SSL/TLS Inspector
 */
function netools_inspect_ssl(string $target): array {
    $ctx = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $target,
        ]
    ]);
    $t0 = microtime(true);
    $client = @stream_socket_client("ssl://{$target}:443", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);
    $ms = round((microtime(true) - $t0) * 1000, 1);

    if (!$client) {
        return ['ok' => false, 'days' => -1, 'text' => "Failed to establish TLS connection on port 443: $errstr ($errno)"];
    }

    $params = stream_context_get_params($client);
    $crypto = stream_get_meta_data($client)['crypto'] ?? [];
    fclose($client);

    $cert = isset($params['options']['ssl']['peer_certificate'])
        ? openssl_x509_parse($params['options']['ssl']['peer_certificate'])
        : null;

    if (!$cert) {
        return ['ok' => false, 'days' => -1, 'text' => "Connected in {$ms} ms, but could not parse X.509 certificate."];
    }

    $validFrom = gmdate('Y-m-d H:i:s \U\T\C', $cert['validFrom_time_t']);
    $validTo   = gmdate('Y-m-d H:i:s \U\T\C', $cert['validTo_time_t']);
    $daysLeft  = (int)floor(($cert['validTo_time_t'] - time()) / 86400);
    $issuer    = $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? 'Unknown Issuer';
    $subject   = $cert['subject']['CN'] ?? $target;
    $sans      = $cert['extensions']['subjectAltName'] ?? 'None listed';
    $protocol  = $crypto['protocol'] ?? 'TLS';
    $cipher    = $crypto['cipher_name'] ?? 'Unknown';
    $status    = $daysLeft > 0 ? "VALID ($daysLeft days remaining)" : "EXPIRED (" . abs($daysLeft) . " days ago)";

    $text = "Subject CN : $subject\n"
          . "Issuer     : $issuer\n"
          . "Status     : $status\n"
          . "Valid From : $validFrom\n"
          . "Valid Until: $validTo\n"
          . "Handshake  : $protocol ($cipher) in {$ms} ms\n"
          . "SANs       : $sans";

    return ['ok' => $daysLeft > 0, 'days' => $daysLeft, 'issuer' => $issuer, 'text' => $text];
}

/**
 * 2. Portable DNS Lookup (dig + native PHP fallback)
 */
function netools_dns_lookup(string $target, string $record): string {
    $map = [
        'A' => DNS_A, 'AAAA' => DNS_AAAA, 'MX' => DNS_MX,
        'TXT' => DNS_TXT, 'NS' => DNS_NS, 'SOA' => DNS_SOA, 'PTR' => DNS_PTR
    ];

    if ($record === 'PTR' && filter_var($target, FILTER_VALIDATE_IP)) {
        $host = @gethostbyaddr($target);
        return ($host && $host !== $target) ? "$target -> $host" : "No PTR record found for $target.";
    }

    $recs = @dns_get_record($target, $map[$record] ?? DNS_A);
    if (is_array($recs) && count($recs) > 0) {
        $lines = [];
        foreach ($recs as $r) {
            $ttl = $r['ttl'] ?? '-';
            switch ($record) {
                case 'A':    $lines[] = sprintf("%-6s TTL %-6s %s", 'A', $ttl, $r['ip'] ?? ''); break;
                case 'AAAA': $lines[] = sprintf("%-6s TTL %-6s %s", 'AAAA', $ttl, $r['ipv6'] ?? ''); break;
                case 'MX':   $lines[] = sprintf("%-6s TTL %-6s [Pri %d] %s", 'MX', $ttl, $r['pri'] ?? 0, $r['target'] ?? ''); break;
                case 'TXT':  $lines[] = sprintf("%-6s TTL %-6s \"%s\"", 'TXT', $ttl, $r['txt'] ?? ''); break;
                case 'NS':   $lines[] = sprintf("%-6s TTL %-6s %s", 'NS', $ttl, $r['target'] ?? ''); break;
                case 'SOA':  $lines[] = sprintf("MNAME: %s | RNAME: %s | Serial: %s | Refresh: %s", $r['mname'] ?? '', $r['rname'] ?? '', $r['serial'] ?? '', $r['refresh'] ?? ''); break;
                default:     $lines[] = json_encode($r);
            }
        }
        return implode("\n", $lines);
    }

    if (netools_has_cmd('dig')) {
        $out = trim((string)@shell_exec('timeout 5s dig +noall +answer ' . escapeshellarg($target) . ' ' . escapeshellarg($record) . ' 2>&1'));
        if ($out !== '') return $out;
    }

    return "No $record records found for $target.";
}

/**
 * 3. Email Deliverability & Auth Check (SPF + DMARC)
 */
function netools_check_email_auth(string $target): array {
    $rootTxt = @dns_get_record($target, DNS_TXT) ?: [];
    $dmarcTxt = @dns_get_record('_dmarc.' . $target, DNS_TXT) ?: [];
    $mxRecs = @dns_get_record($target, DNS_MX) ?: [];

    $spf = [];
    foreach ($rootTxt as $r) {
        if (!empty($r['txt']) && stripos($r['txt'], 'v=spf1') === 0) {
            $spf[] = $r['txt'];
        }
    }

    $dmarc = [];
    foreach ($dmarcTxt as $r) {
        if (!empty($r['txt']) && stripos($r['txt'], 'v=DMARC1') === 0) {
            $dmarc[] = $r['txt'];
        }
    }

    $out = [];
    $out[] = "=== MX RECORDS (" . count($mxRecs) . ") ===";
    if ($mxRecs) {
        foreach ($mxRecs as $m) $out[] = "  [Pri {$m['pri']}] {$m['target']}";
    } else {
        $out[] = "  None found (domain may not receive email).";
    }

    $out[] = "\n=== SPF RECORD ===";
    if (count($spf) === 1) {
        $out[] = "  [PASS] " . $spf[0];
    } elseif (count($spf) > 1) {
        $out[] = "  [WARN] Multiple SPF records found (RFC violation — merge into one):";
        foreach ($spf as $s) $out[] = "    - $s";
    } else {
        $out[] = "  [MISSING] No v=spf1 TXT record found.";
    }

    $out[] = "\n=== DMARC RECORD (_dmarc.$target) ===";
    $dmarcPolicy = 'missing';
    if (count($dmarc) >= 1) {
        $out[] = "  [PASS] " . $dmarc[0];
        if (preg_match('/\bp=(reject|quarantine|none)\b/i', $dmarc[0], $m)) {
            $dmarcPolicy = strtolower($m[1]);
            $out[] = "  Policy Mode: p=$dmarcPolicy" . ($dmarcPolicy === 'none' ? ' (Monitoring only — does not block spoofing)' : ' (Active enforcement)');
        }
    } else {
        $out[] = "  [MISSING] No v=DMARC1 record found at _dmarc.$target.";
    }

    return [
        'has_spf' => count($spf) === 1,
        'has_dmarc' => count($dmarc) >= 1,
        'dmarc_policy' => $dmarcPolicy,
        'text' => implode("\n", $out)
    ];
}

/**
 * 4. HTTP Header & Redirect Tracer (cURL + PHP Stream fallback)
 */
function netools_http_headers(string $target): array {
    $url = "http://$target/";
    $t0 = microtime(true);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'IT-INDIA-Netools/2.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $raw = (string)curl_exec($ch);
        $err = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($raw === '' && $err) {
            return ['ok' => false, 'code' => 0, 'headers' => [], 'text' => "HTTP request failed: $err"];
        }

        $ms = round(($info['total_time'] ?? (microtime(true) - $t0)) * 1000, 1);
        $finalUrl = $info['url'] ?? $url;
        $code = (int)($info['http_code'] ?? 0);

        // Extract headers from the final response block
        $blocks = preg_split("/\r?\n\r?\n/", trim($raw));
        $lastBlock = end($blocks) ?: '';
        $parsedHeaders = [];
        foreach (explode("\n", $lastBlock) as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($k))] = trim($v);
            }
        }

        $summary = "Final URL : $finalUrl\n"
                 . "HTTP Code : $code\n"
                 . "Redirects : " . ($info['redirect_count'] ?? 0) . "\n"
                 . "Total Time: {$ms} ms\n"
                 . str_repeat('-', 45) . "\n"
                 . trim($raw);

        return ['ok' => ($code >= 200 && $code < 400), 'code' => $code, 'final_url' => $finalUrl, 'headers' => $parsedHeaders, 'text' => $summary];
    }

    // Pure-PHP Stream Fallback
    $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 6, 'follow_location' => 1, 'max_redirects' => 5, 'ignore_errors' => true]]);
    $headers = @get_headers($url, false, $ctx);
    $ms = round((microtime(true) - $t0) * 1000, 1);
    if (!$headers) {
        return ['ok' => false, 'code' => 0, 'headers' => [], 'text' => "Failed to retrieve HTTP headers from $url."];
    }
    return ['ok' => true, 'code' => 200, 'headers' => [], 'text' => "Time: {$ms} ms\n" . implode("\n", $headers)];
}

/**
 * 5. TCP Port Check (fsockopen — works on Linux & Windows identically)
 */
function netools_tcp_check(string $target, int $port): array {
    $t0 = microtime(true);
    $fp = @fsockopen($target, $port, $errno, $errstr, 3.0);
    $ms = round((microtime(true) - $t0) * 1000, 1);
    if ($fp) {
        fclose($fp);
        return ['ok' => true, 'ms' => $ms, 'text' => "OPEN — Connection to $target:$port succeeded in {$ms} ms."];
    }
    return ['ok' => false, 'ms' => $ms, 'text' => "CLOSED / FILTERED — Connection to $target:$port failed ($errstr)."];
}

/**
 * 6. WHOIS Lookup (CLI whois + Port-43 IANA fallback)
 */
function netools_whois(string $target): string {
    if (netools_has_cmd('whois')) {
        $out = trim((string)@shell_exec('timeout 6s whois ' . escapeshellarg($target) . ' 2>&1'));
        if ($out !== '') return $out;
    }

    $queryServer = function(string $server, string $q): string {
        $fp = @fsockopen($server, 43, $errno, $errstr, 5);
        if (!$fp) return '';
        fwrite($fp, $q . "\r\n");
        $resp = '';
        while (!feof($fp)) $resp .= fgets($fp, 1024);
        fclose($fp);
        return $resp;
    };

    $iana = $queryServer('whois.iana.org', $target);
    if (preg_match('/refer:\s*(\S+)/i', $iana, $m)) {
        $detail = $queryServer(trim($m[1]), $target);
        if ($detail !== '') return $detail;
    }
    return $iana ?: "WHOIS lookup unavailable for $target.";
}

/**
 * 7. All-in-One Domain Health Audit
 */
function netools_full_audit(string $target): array {
    $badges = [];
    $sections = [];

    // DNS A/AAAA
    $aRecs = @dns_get_record($target, DNS_A) ?: [];
    $ips = array_column($aRecs, 'ip');
    $badges[] = [
        'label' => 'DNS Resolution',
        'state' => count($ips) > 0 ? 'pass' : 'fail',
        'detail' => count($ips) > 0 ? implode(', ', array_slice($ips, 0, 2)) : 'No A record'
    ];
    $sections[] = "=== 1. DNS RECORDS ===\n" . netools_dns_lookup($target, 'A');

    // Port 80 & 443
    $p80 = netools_tcp_check($target, 80);
    $p443 = netools_tcp_check($target, 443);
    $badges[] = [
        'label' => 'Ports 80 / 443',
        'state' => ($p443['ok'] ? 'pass' : ($p80['ok'] ? 'warn' : 'fail')),
        'detail' => ($p80['ok'] ? '80 Open' : '80 Closed') . ' · ' . ($p443['ok'] ? "443 Open ({$p443['ms']}ms)" : '443 Closed')
    ];

    // SSL/TLS
    $ssl = netools_inspect_ssl($target);
    $sslState = !$ssl['ok'] ? 'fail' : ($ssl['days'] < 14 ? 'warn' : 'pass');
    $badges[] = [
        'label' => 'SSL / TLS',
        'state' => $sslState,
        'detail' => $ssl['ok'] ? "{$ssl['days']}d left ({$ssl['issuer']})" : 'No valid HTTPS'
    ];
    $sections[] = "=== 2. SSL / TLS CERTIFICATE ===\n" . $ssl['text'];

    // HTTP & Security Headers
    $http = netools_http_headers($target);
    $h = $http['headers'] ?? [];
    $secHeaders = [];
    if (!empty($h['strict-transport-security'])) $secHeaders[] = 'HSTS';
    if (!empty($h['content-security-policy'])) $secHeaders[] = 'CSP';
    if (!empty($h['x-frame-options'])) $secHeaders[] = 'X-Frame';
    $badges[] = [
        'label' => 'HTTP & Security',
        'state' => $http['ok'] ? (count($secHeaders) >= 2 ? 'pass' : 'warn') : 'fail',
        'detail' => "HTTP {$http['code']} · Headers: " . ($secHeaders ? implode(', ', $secHeaders) : 'None detected')
    ];
    $sections[] = "=== 3. HTTP HEADERS ===\n" . $http['text'];

    // Email Auth (SPF / DMARC)
    $email = netools_check_email_auth($target);
    $emailState = ($email['has_spf'] && $email['has_dmarc']) ? 'pass' : (($email['has_spf'] || $email['has_dmarc']) ? 'warn' : 'fail');
    $badges[] = [
        'label' => 'Email (SPF/DMARC)',
        'state' => $emailState,
        'detail' => ($email['has_spf'] ? 'SPF ✓' : 'SPF ✗') . ' · ' . ($email['has_dmarc'] ? "DMARC ({$email['dmarc_policy']})" : 'DMARC ✗')
    ];
    $sections[] = "=== 4. EMAIL AUTHENTICATION ===\n" . $email['text'];

    return [
        'badges' => $badges,
        'text' => implode("\n\n", $sections)
    ];
}
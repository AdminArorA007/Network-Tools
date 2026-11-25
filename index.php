<?php
require_once __DIR__ . '/lib.php';
$token = netools_make_token();
$visitorIp = netools_client_ip();
$visitorPtr = filter_var($visitorIp, FILTER_VALIDATE_IP) ? @gethostbyaddr($visitorIp) : $visitorIp;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Network Diagnostics Suite — IT-INDIA.org</title>
<style>
:root {
  --bg: #0b1120;
  --surface: #111827;
  --surface-2: #1e293b;
  --border: #263245;
  --text: #f1f5f9;
  --muted: #94a3b8;
  --accent: #facc15;
  --accent-text: #0b1120;
  --primary: #2563eb;
  --primary-hover: #1d4ed8;
  --pass: #10b981;
  --warn: #f59e0b;
  --fail: #ef4444;
  --pre-bg: #080d19;
}
[data-theme="light"] {
  --bg: #f4f7fb;
  --surface: #ffffff;
  --surface-2: #f1f5f9;
  --border: #dbe3ee;
  --text: #0f172a;
  --muted: #64748b;
  --accent: #2563eb;
  --accent-text: #ffffff;
  --primary: #2563eb;
  --primary-hover: #1d4ed8;
  --pre-bg: #f8fafc;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  background: var(--bg);
  color: var(--text);
  line-height: 1.5;
}
a { color: inherit; text-decoration: none; }
.topbar {
  border-bottom: 1px solid var(--border);
  background: var(--surface);
  padding: 14px 20px;
}
.topbar-inner {
  max-width: 920px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
}
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  font-size: 1.05rem;
}
.brand-badge {
  background: var(--accent);
  color: var(--accent-text);
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.85rem;
}
.top-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--surface-2);
  color: var(--text);
  font-size: 0.85rem;
  cursor: pointer;
}
.pill-btn:hover { border-color: var(--accent); }
.container {
  max-width: 920px;
  margin: 28px auto;
  padding: 0 16px;
}
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 24px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.18);
}
.header-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 20px;
}
.header-row h1 {
  margin: 0;
  font-size: 1.45rem;
  display: flex;
  align-items: center;
  gap: 10px;
}
.privacy-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.privacy-pill {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 3px 9px;
  border-radius: 999px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--muted);
}
.tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
  padding-bottom: 12px;
  flex-wrap: wrap;
}
.tab-btn {
  padding: 7px 14px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-2);
  color: var(--text);
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
}
.tab-btn.active {
  background: var(--accent);
  color: var(--accent-text);
  border-color: var(--accent);
}
.grid-2 {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 14px;
}
@media (max-width: 640px) { .grid-2 { grid-template-columns: 1fr; } }
.field { margin-bottom: 14px; }
label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 6px;
  color: var(--muted);
}
input[type="text"], select {
  width: 100%;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-2);
  color: var(--text);
  font-size: 0.95rem;
}
input:focus, select:focus {
  outline: none;
  border-color: var(--accent);
}
.quick-targets {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 6px;
}
.chip {
  font-size: 0.76rem;
  padding: 2px 9px;
  border-radius: 6px;
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--muted);
  cursor: pointer;
}
.chip:hover { color: var(--text); border-color: var(--accent); }
.btn-run {
  width: 100%;
  padding: 11px 16px;
  border: none;
  border-radius: 8px;
  background: var(--primary);
  color: #fff;
  font-size: 0.98rem;
  font-weight: 600;
  cursor: pointer;
}
.btn-run:hover { background: var(--primary-hover); }
.btn-run:disabled { opacity: 0.6; cursor: not-allowed; }
.hint {
  text-align: center;
  font-size: 0.8rem;
  color: var(--muted);
  margin-top: 8px;
}
.output-box {
  margin-top: 22px;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}
.output-header {
  background: var(--surface-2);
  padding: 10px 14px;
  font-size: 0.85rem;
  font-weight: 700;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.output-body { padding: 14px; }
.out-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}
.out-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 9px;
  border-radius: 6px;
  background: var(--primary);
  color: #fff;
}
.btn-copy {
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--surface-2);
  color: var(--text);
  font-size: 0.8rem;
  cursor: pointer;
}
pre {
  margin: 0;
  background: var(--pre-bg);
  border: 1px solid var(--border);
  padding: 14px;
  border-radius: 8px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 0.85rem;
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-word;
}
.audit-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
  margin-bottom: 14px;
}
.audit-card {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--surface-2);
}
.audit-title { font-weight: 700; font-size: 0.83rem; margin-bottom: 3px; }
.audit-detail { font-size: 0.78rem; color: var(--muted); }
.st-pass { border-left: 4px solid var(--pass); }
.st-pass .audit-title span { color: var(--pass); }
.st-warn { border-left: 4px solid var(--warn); }
.st-warn .audit-title span { color: var(--warn); }
.st-fail { border-left: 4px solid var(--fail); }
.st-fail .audit-title span { color: var(--fail); }
.nt-alert { padding: 12px; border-radius: 8px; font-size: 0.9rem; }
.nt-alert-danger { background: rgba(239,68,68,0.14); border: 1px solid var(--fail); color: #fca5a5; }
[data-theme="light"] .nt-alert-danger { color: #b91c1c; }
.nt-alert-warn { background: rgba(245,158,11,0.14); border: 1px solid var(--warn); }
.hidden { display: none !important; }
.hp { position: absolute !important; left: -9999px !important; opacity: 0 !important; height: 0; width: 0; }
footer {
  text-align: center;
  color: var(--muted);
  font-size: 0.8rem;
  margin: 26px 0;
}
</style>
</head>
<body>

<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="./">
      <span class="brand-badge">IT</span>
      <span>Netools Diagnostics</span>
    </a>
    <div class="top-actions">
      <button type="button" class="pill-btn" id="themeToggle" title="Toggle Dark/Light Theme">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <span id="themeLabel">Light</span>
      </button>
      <a class="pill-btn" href="<?= htmlspecialchars(NETOOLS_GITHUB_URL, ENT_QUOTES) ?>" target="_blank" rel="noopener">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
        <span>GitHub</span>
      </a>
    </div>
  </div>
</header>

<main class="container">
  <div class="card">
    <div class="header-row">
      <h1>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        Diagnostics Toolkit
      </h1>
      <div class="privacy-pills">
        <span class="privacy-pill">No Cookies</span>
        <span class="privacy-pill">No Tracking</span>
        <span class="privacy-pill">No Ads</span>
      </div>
    </div>

    <div class="tabs">
      <button type="button" class="tab-btn active" data-tab="panel-server">Live Server Diagnostics</button>
      <button type="button" class="tab-btn" data-tab="panel-cidr">CIDR / Subnet Calculator (Offline)</button>
      <button type="button" class="tab-btn" data-tab="panel-myip">My Connection Info</button>
    </div>

    <!-- PANEL 1: SERVER DIAGNOSTICS -->
    <div id="panel-server">
      <form id="diagnosticsForm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
        <div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>

        <div class="field">
          <label for="targetInput">Hostname or Public IP Address</label>
          <input type="text" id="targetInput" name="target" placeholder="example.com or 1.1.1.1" required maxlength="253">
          <div class="quick-targets">
            <span class="chip" onclick="setTarget('it-india.org')">it-india.org</span>
            <span class="chip" onclick="setTarget('cloudflare.com')">cloudflare.com</span>
            <span class="chip" onclick="setTarget('1.1.1.1')">1.1.1.1</span>
            <span class="chip" onclick="setTarget('8.8.8.8')">8.8.8.8</span>
          </div>
        </div>

        <div class="grid-2">
          <div class="field">
            <label for="testTypeSelect">Diagnostic Test</label>
            <select name="test_type" id="testTypeSelect" required>
              <option value="audit">Full Domain Health Audit (All-in-One)</option>
              <option value="dns">DNS Lookup</option>
              <option value="emailauth">Email Auth Check (SPF &amp; DMARC)</option>
              <option value="ssl">SSL / TLS Certificate Inspector</option>
              <option value="httpheader">HTTP Header &amp; Redirect Tracer</option>
              <option value="tcpport">TCP Port Check</option>
              <option value="ping">Ping Test</option>
              <option value="whois">WHOIS Lookup</option>
              <option value="tracert">Traceroute (Live Stream)</option>
            </select>
          </div>

          <div class="field hidden" id="dnsSubOptions">
            <label for="dnsRecordSelect">DNS Record Type</label>
            <select name="dns_record" id="dnsRecordSelect">
              <option value="A">A (IPv4)</option>
              <option value="AAAA">AAAA (IPv6)</option>
              <option value="MX">MX (Mail)</option>
              <option value="TXT">TXT (Verification/SPF)</option>
              <option value="NS">NS (Name Server)</option>
              <option value="SOA">SOA</option>
              <option value="PTR">PTR (Reverse IP)</option>
            </select>
          </div>

          <div class="field hidden" id="tcpSubOptions">
            <label for="tcpPortSelect">Target TCP Port</label>
            <select name="tcp_port" id="tcpPortSelect">
              <option value="443">443 (HTTPS)</option>
              <option value="80">80 (HTTP)</option>
              <option value="22">22 (SSH)</option>
              <option value="25">25 (SMTP)</option>
              <option value="465">465 (SMTPS)</option>
              <option value="587">587 (Mail Submission)</option>
              <option value="993">993 (IMAPS)</option>
              <option value="53">53 (DNS)</option>
              <option value="3306">3306 (MySQL)</option>
              <option value="8080">8080 (Alt HTTP)</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn-run" id="submitBtn">Execute Diagnostic</button>
        <div class="hint">5-second cooldown between tests · Private &amp; local IPs blocked</div>
      </form>

      <div class="output-box">
        <div class="output-header">
          <span>Diagnostic Output</span>
          <span id="statusHint" style="color:var(--muted);font-weight:400;">Ready</span>
        </div>
        <div class="output-body" id="result-body">
          <div style="text-align:center;color:var(--muted);padding:12px 0;">Enter a domain or IP above and click Execute Diagnostic.</div>
        </div>
      </div>
    </div>

    <!-- PANEL 2: OFFLINE CIDR CALCULATOR -->
    <div id="panel-cidr" class="hidden">
      <div class="grid-2">
        <div class="field">
          <label for="cidrInput">IPv4 Address with CIDR Prefix</label>
          <input type="text" id="cidrInput" value="192.168.1.10/24" placeholder="e.g. 10.24.0.1/22">
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <button type="button" class="btn-run" onclick="calcCidr()">Calculate Subnet</button>
        </div>
      </div>
      <div class="output-box">
        <div class="output-header"><span>Subnet Breakdown (100% Client-Side)</span></div>
        <div class="output-body">
          <pre id="cidr-out">Click "Calculate Subnet" to inspect network range, netmask, broadcast, and usable hosts.</pre>
        </div>
      </div>
    </div>

    <!-- PANEL 3: MY CONNECTION INFO -->
    <div id="panel-myip" class="hidden">
      <div class="output-box" style="margin-top:0;">
        <div class="output-header">
          <span>Your Current Connection (Not Logged)</span>
          <button type="button" class="btn-copy" onclick="copyText(document.getElementById('myip-out').innerText, this)">Copy</button>
        </div>
        <div class="output-body">
          <pre id="myip-out">Public IP Address : <?= htmlspecialchars($visitorIp, ENT_QUOTES) ?>

Reverse DNS (PTR) : <?= htmlspecialchars($visitorPtr ?: 'None', ENT_QUOTES) ?>

Browser Viewport  : <span id="vp-info">Detecting...</span>
User-Agent        : <?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', ENT_QUOTES) ?></pre>
        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  Netools Suite · Open-Source &amp; Zero-Tracking Utility for IT-INDIA.org
</footer>

<script>
// Theme toggle via localStorage (no cookies)
const root = document.documentElement;
const themeBtn = document.getElementById('themeToggle');
const themeLabel = document.getElementById('themeLabel');
const savedTheme = localStorage.getItem('netools_theme') || 'dark';
root.setAttribute('data-theme', savedTheme);
themeLabel.textContent = savedTheme === 'dark' ? 'Light' : 'Dark';

themeBtn.addEventListener('click', () => {
  const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  root.setAttribute('data-theme', next);
  localStorage.setItem('netools_theme', next);
  themeLabel.textContent = next === 'dark' ? 'Light' : 'Dark';
});

// Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    ['panel-server', 'panel-cidr', 'panel-myip'].forEach(id => {
      document.getElementById(id).classList.toggle('hidden', id !== btn.dataset.tab);
    });
    if (btn.dataset.tab === 'panel-cidr') calcCidr();
  });
});

// Dynamic sub-options
const testSelect = document.getElementById('testTypeSelect');
function syncSubOptions() {
  const v = testSelect.value;
  document.getElementById('dnsSubOptions').classList.toggle('hidden', v !== 'dns');
  document.getElementById('tcpSubOptions').classList.toggle('hidden', v !== 'tcpport');
}
testSelect.addEventListener('change', syncSubOptions);

function setTarget(val) {
  document.getElementById('targetInput').value = val;
}

// URL query pre-fill (?target=example.com&type=ssl)
const params = new URLSearchParams(window.location.search);
if (params.get('target')) document.getElementById('targetInput').value = params.get('target');
if (params.get('type')) {
  testSelect.value = params.get('type');
  syncSubOptions();
}

// Viewport info
document.getElementById('vp-info').textContent = `${window.innerWidth} × ${window.innerHeight} px (${Intl.DateTimeFormat().resolvedOptions().timeZone})`;

// Copy helpers
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const old = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(() => btn.textContent = old, 1500);
  });
}
function copyRawOutput(btn) {
  const el = document.getElementById('raw-out');
  if (el) copyText(el.innerText, btn);
}

// Form submission (Fetch + ReadableStream for SSE Traceroute)
let lastRun = 0;
document.getElementById('diagnosticsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const resBody = document.getElementById('result-body');
  const submitBtn = document.getElementById('submitBtn');
  const statusHint = document.getElementById('statusHint');

  if (Date.now() - lastRun < 5000) {
    resBody.innerHTML = "<div class='nt-alert nt-alert-warn'>Please wait 5 seconds between diagnostic requests.</div>";
    return;
  }
  lastRun = Date.now();
  submitBtn.disabled = true;
  submitBtn.textContent = 'Running Diagnostic...';
  statusHint.textContent = 'Executing...';

  const formData = new FormData(e.target);
  const isTrace = testSelect.value === 'tracert';

  try {
    const resp = await fetch('process.php', { method: 'POST', body: formData });

    if (!resp.ok) {
      const errHtml = await resp.text();
      resBody.innerHTML = errHtml;
      statusHint.textContent = 'Blocked / Error';
      return;
    }

    if (isTrace && resp.body) {
      resBody.innerHTML = "<div class='out-toolbar'><span class='out-badge'>TRACEROUTE LIVE STREAM</span><button type='button' class='btn-copy' onclick='copyRawOutput(this)'>Copy Raw</button></div><pre id='raw-out'></pre>";
      const pre = document.getElementById('raw-out');
      const reader = resp.body.getReader();
      const decoder = new TextDecoder();
      let partial = '';

      while (true) {
        const { value, done } = await reader.read();
        if (done) break;
        partial += decoder.decode(value, { stream: true });
        const events = partial.split("\n\n");
        partial = events.pop();
        for (const ev of events) {
          if (ev.startsWith('data: ') && ev !== 'data: [DONE]') {
            try {
              const obj = JSON.parse(ev.slice(6));
              pre.textContent += obj.line;
            } catch (_) {}
          }
        }
      }
      statusHint.textContent = 'Completed';
    } else {
      const html = await resp.text();
      resBody.innerHTML = html;
      statusHint.textContent = 'Completed';
    }
  } catch (err) {
    resBody.innerHTML = "<div class='nt-alert nt-alert-danger'>Network error while contacting server.</div>";
    statusHint.textContent = 'Failed';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Execute Diagnostic';
  }
});

// Offline IPv4 CIDR / Subnet Calculator
function calcCidr() {
  const raw = document.getElementById('cidrInput').value.trim();
  const out = document.getElementById('cidr-out');
  const m = raw.match(/^(\d+)\.(\d+)\.(\d+)\.(\d+)\/(\d+)$/);
  if (!m) {
    out.textContent = 'Invalid format. Use IPv4/CIDR notation, e.g. 192.168.1.10/24';
    return;
  }
  const octets = [1,2,3,4].map(i => parseInt(m[i], 10));
  const prefix = parseInt(m[5], 10);
  if (octets.some(o => o < 0 || o > 255) || prefix < 0 || prefix > 32) {
    out.textContent = 'IPv4 octets must be 0–255 and prefix must be /0 to /32.';
    return;
  }

  const ipInt = ((octets[0] << 24) | (octets[1] << 16) | (octets[2] << 8) | octets[3]) >>> 0;
  const maskInt = prefix === 0 ? 0 : (0xffffffff << (32 - prefix)) >>> 0;
  const netInt = (ipInt & maskInt) >>> 0;
  const bcastInt = (netInt | (~maskInt >>> 0)) >>> 0;
  const toIp = n => [(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255].join('.');

  const totalHosts = Math.pow(2, 32 - prefix);
  const usableHosts = prefix >= 31 ? totalHosts : Math.max(0, totalHosts - 2);
  const firstUsable = prefix >= 31 ? toIp(netInt) : toIp((netInt + 1) >>> 0);
  const lastUsable = prefix >= 31 ? toIp(bcastInt) : toIp((bcastInt - 1) >>> 0);

  out.textContent =
    `Input CIDR      : ${octets.join('.')}/${prefix}\n` +
    `Network Address : ${toIp(netInt)}\n` +
    `Subnet Mask     : ${toIp(maskInt)}\n` +
    `Wildcard Mask   : ${toIp((~maskInt) >>> 0)}\n` +
    `Broadcast Addr  : ${toIp(bcastInt)}\n` +
    `Usable Range    : ${firstUsable} — ${lastUsable}\n` +
    `Usable Hosts    : ${usableHosts.toLocaleString()} (Total IPs: ${totalHosts.toLocaleString()})`;
}
</script>
</body>
</html>
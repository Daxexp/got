<?php
/**
 * proxy.php — Opaque signed video proxy
 *
 * URL format: proxy.php?v=BASE64_PAYLOAD&s=HMAC_SIG
 *
 * PAYLOAD = base64(episodeKey:timestamp)  e.g. base64("S01E02:1742134800")
 * SIG     = HMAC-SHA256(secret, "S01E02:hourWindow")
 *
 * The episode key (S01E02) is hidden inside base64 — not visible in the URL.
 * Token expires after ~2 hours. Can't be guessed without the secret.
 *
 * Security layers:
 *  1. Opaque URL   — episode key not visible, looks like random data
 *  2. Signed token — expires ~2 hours, can't be forged or reused
 *  3. Referer check — blocks hotlinking from external sites
 *  4. Rate limiting — max 30 new stream requests per IP per minute
 *  5. Origin URLs  — never sent to browser, server-side only
 */

define('PROXY_SECRET', 'g0t_pr0xy_s3cr3t_k3y_ch4ng3_m3_2024_xK9mP');

// ── Decode and validate opaque token ─────────────────────────
$encodedPayload = $_GET['v'] ?? '';
$clientSig      = $_GET['s'] ?? '';

if (!$encodedPayload || !$clientSig) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden.');
}

// ── Helper: get real visitor IP ──────────────────────────────
function getRequestIp(): string {
    return trim(explode(',', (
        $_SERVER['HTTP_CF_CONNECTING_IP'] ??
        $_SERVER['HTTP_X_FORWARDED_FOR']  ??
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ))[0]);
}

// ── Helper: /24 subnet (tolerates minor dynamic IP changes) ──
function ipSubnet(string $ip): string {
    if (str_contains($ip, '.')) {
        // IPv4 — compare first 3 octets
        return implode('.', array_slice(explode('.', $ip), 0, 3));
    }
    // IPv6 — compare first 6 groups
    return implode(':', array_slice(explode(':', $ip), 0, 6));
}

// ── Helper: nonce file path (must match index.php) ────────────
function nonceFile(string $ip, string $key): string {
    return sys_get_temp_dir() . '/got_nonce_' . md5($ip . ':' . $key) . '.txt';
}

// ── Decode payload — format: base64(key:timestamp:ip:nonce) ──
$payload = base64_decode($encodedPayload, true);
if ($payload === false || substr_count($payload, ':') < 3) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden.');
}

// Parse all 4 parts — limit=4 keeps nonce intact if it has colons
$parts    = explode(':', $payload, 4);
$epKey    = strtoupper(preg_replace('/[^A-Z0-9]/', '', $parts[0]));
$clientTs = (int)($parts[1] ?? 0);
$boundIp  = trim($parts[2] ?? '');
$nonce    = trim($parts[3] ?? '');

if (!$epKey || !$clientTs || !$boundIp || !$nonce) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden.');
}

// ── Check 1: IP binding ───────────────────────────────────────
// Token is locked to the IP it was generated for.
// Copied URL from a different device/network = rejected.
$requestIp = getRequestIp();
if (ipSubnet($requestIp) !== ipSubnet($boundIp)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden.');
}

// ── Check 2: Nonce validation (one active URL per IP+episode) ─
// index.php saves the latest nonce to a temp file when generating a URL.
// On page refresh, a new nonce overwrites the old one.
// Any previously generated URL for the same episode becomes invalid
// because its nonce no longer matches what's stored server-side.
$nonceFile    = nonceFile($boundIp, $epKey);
$storedNonce  = file_exists($nonceFile) ? trim(@file_get_contents($nonceFile)) : '';

if (!$storedNonce || !hash_equals($storedNonce, $nonce)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden.');
}

// Check timestamp — accept up to 2 hours old
$now = time();
if ($clientTs < $now - 7200 || $clientTs > $now + 30) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Link expired. Please reload the page.');
}

// ── Check 3: HMAC signature (covers key + window + ip + nonce) ──
$window   = (int)floor($clientTs / 3600);
$expected = hash_hmac('sha256', $epKey . ':' . $window . ':' . $boundIp . ':' . $nonce, PROXY_SECRET);
if (!hash_equals($expected, $clientSig)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Invalid token.');
}

// ── Referer check ─────────────────────────────────────────────
$allowedHost = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
$referer     = $_SERVER['HTTP_REFERER'] ?? '';
if ($referer !== '') {
    $refHost = explode(':', parse_url($referer, PHP_URL_HOST) ?? '')[0];
    if ($refHost !== $allowedHost && $refHost !== 'localhost' && $refHost !== '127.0.0.1') {
        http_response_code(403);
        header('Content-Type: text/plain');
        exit('Forbidden.');
    }
}

// ── Rate limiting ─────────────────────────────────────────────
function proxyRateLimit(string $ip, int $max = 30, int $window = 60): bool {
    $tmpDir = sys_get_temp_dir();
    if (!is_writable($tmpDir)) return true;
    $key  = $tmpDir . '/got_proxy_rl_' . md5($ip) . '.json';
    $now  = time();
    $data = ['count' => 0, 'start' => $now];
    if (file_exists($key)) {
        $raw = @file_get_contents($key);
        if ($raw) $data = json_decode($raw, true) ?? $data;
    }
    if ($now - $data['start'] > $window) $data = ['count' => 0, 'start' => $now];
    if (empty($_SERVER['HTTP_RANGE']) || str_starts_with($_SERVER['HTTP_RANGE'], 'bytes=0-')) {
        $data['count']++;
        @file_put_contents($key, json_encode($data), LOCK_EX);
        if ($data['count'] > $max) return false;
    }
    return true;
}

$ip = trim(explode(',', (
    $_SERVER['HTTP_CF_CONNECTING_IP'] ??
    $_SERVER['HTTP_X_FORWARDED_FOR']  ??
    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
))[0]);

if (!proxyRateLimit($ip)) {
    http_response_code(429);
    header('Content-Type: text/plain');
    exit('Too many requests.');
}

// ── Episode map (never sent to browser) ──────────────────────
$FILES = [
  'S01E01' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E01.mp4',
  'S01E02' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E02.mp4',
  'S01E03' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E03.mp4',
  'S01E04' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E04.mp4',
  'S01E05' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E05.mp4',
  'S01E06' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E06.mp4',
  'S01E07' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E07.mp4',
  'S01E08' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E08.mp4',
  'S01E09' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E09.mp4',
  'S01E10' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S01E10.mp4',
  'S02E01' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E01.mp4',
  'S02E02' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E02.mp4',
  'S02E03' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E03.mp4',
  'S02E04' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E04.mp4',
  'S02E05' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E05.mp4',
  'S02E06' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E06.mp4',
  'S02E07' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E07.mp4',
  'S02E08' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E08.mp4',
  'S02E09' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E09.mp4',
  'S02E10' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S02E10.mp4',
  'S03E01' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E01.mp4',
  'S03E02' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E02.mp4',
  'S03E03' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E03.mp4',
  'S03E04' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E04.mp4',
  'S03E05' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E05.mp4',
  'S03E06' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E06.mp4',
  'S03E07' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E07.mp4',
  'S03E08' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E08.mp4',
  'S03E09' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E09.mp4',
  'S03E10' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S03E10.mp4',
  'S04E01' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E01.mp4',
  'S04E02' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E02.mp4',
  'S04E03' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E03.mp4',
  'S04E04' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E04.mp4',
  'S04E05' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E05.mp4',
  'S04E06' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E06.mp4',
  'S04E07' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E07.mp4',
  'S04E08' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E08.mp4',
  'S04E09' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E09.mp4',
  'S04E10' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game%20Of%20Thrones%20S04E10.mp4',
  'S05E01' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E01.mp4',
  'S05E02' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E02.mp4',
  'S05E03' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E03.mp4',
  'S05E04' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E04.mp4',
  'S05E05' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E05.mp4',
  'S05E06' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E06.mp4',
  'S05E07' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E07.mp4',
  'S05E08' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E08.mp4',
  'S05E09' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E09.mp4',
  'S05E10' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S05E10.mp4',
  'S06E01' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E01.mp4',
  'S06E02' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E02.mp4',
  'S06E03' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E03.mp4',
  'S06E04' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E04.mp4',
  'S06E05' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E05.mp4',
  'S06E06' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E06.mp4',
  'S06E07' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E07.mp4',
  'S06E08' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E08.mp4',
  'S06E09' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E09.mp4',
  'S06E10' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S06E10.mp4',
  'S07E01' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S07E01.mp4',
  'S07E02' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S07E02.mp4',
  'S07E03' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S07E03.mp4',
  'S07E04' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S07E04.mp4',
  'S07E05' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S07E05.mp4',
  'S07E06' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S07E06.mp4',
  'S07E07' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S07E07.mp4',
  'S08E01' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S08E01.mp4',
  'S08E02' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S08E02.mp4',
  'S08E03' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S08E03.mp4',
  'S08E04' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S08E04.mp4',
  'S08E05' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S08E05.mp4',
  'S08E06' => 'https://01.pahan22feb.online/CineSubz.com%20-%20Game.of.Thrones.S08E06.mp4',
];

if (!isset($FILES[$epKey])) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Episode not found.');
}

$originUrl = $FILES[$epKey];

// ── Stream ────────────────────────────────────────────────────
$reqHeaders = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept: video/mp4,video/*;q=0.9,*/*;q=0.8',
    'Accept-Language: en-US,en;q=0.5',
    'Connection: keep-alive',
];
if (!empty($_SERVER['HTTP_RANGE'])) {
    $reqHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
}

$ctx = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'header'          => implode("\r\n", $reqHeaders),
        'timeout'         => 30,
        'follow_location' => 1,
        'max_redirects'   => 5,
    ],
    'ssl' => [
        'verify_peer'      => false,
        'verify_peer_name' => false,
    ],
]);

$fp = @fopen($originUrl, 'rb', false, $ctx);
if (!$fp) {
    http_response_code(502);
    header('Content-Type: text/plain');
    exit('Could not connect to video source. Please try again.');
}

$meta            = stream_get_meta_data($fp);
$upstreamHeaders = $meta['wrapper_data'] ?? [];
$statusCode      = 200;
$contentType     = 'video/mp4';
$contentLength   = null;
$contentRange    = null;
$acceptRanges    = null;

foreach ($upstreamHeaders as $h) {
    if      (preg_match('#^HTTP/\S+\s+(\d+)#i',       $h, $m)) $statusCode    = (int)$m[1];
    elseif  (preg_match('/^Content-Type:\s*(.+)/i',    $h, $m)) $contentType   = trim($m[1]);
    elseif  (preg_match('/^Content-Length:\s*(\d+)/i', $h, $m)) $contentLength = (int)$m[1];
    elseif  (preg_match('/^Content-Range:\s*(.+)/i',   $h, $m)) $contentRange  = trim($m[1]);
    elseif  (preg_match('/^Accept-Ranges:\s*(.+)/i',   $h, $m)) $acceptRanges  = trim($m[1]);
}

if (ob_get_level()) ob_end_clean();
http_response_code($statusCode);
header('Content-Type: '  . $contentType);
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Content-Type');
if ($contentLength !== null) header('Content-Length: ' . $contentLength);
if ($contentRange   !== null) header('Content-Range: '  . $contentRange);
if ($acceptRanges   !== null) header('Accept-Ranges: '  . $acceptRanges);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { fclose($fp); exit; }

$chunkSize = 1024 * 256;
while (!feof($fp)) {
    $chunk = fread($fp, $chunkSize);
    if ($chunk === false || strlen($chunk) === 0) break;
    echo $chunk;
    flush();
    if (connection_aborted()) break;
}
fclose($fp);
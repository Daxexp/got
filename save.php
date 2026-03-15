<?php
/**
 * save.php — Watch history backend
 * Stores per-username watch history as tiny JSON files.
 * File: data/wh_{username}.json  (~1–3 KB each)
 *
 * Actions (POST):
 *   set_user      — create/confirm username, returns full history
 *   save_ep       — save episode progress
 *   get_history   — load full history for a user
 *   clear_history — wipe all history for a user
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

define('WH_DIR', __DIR__ . '/data');

if (!is_dir(WH_DIR)) mkdir(WH_DIR, 0755, true);

// ── Helpers ───────────────────────────────────────
function whFile(string $u): string {
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $u);
    return WH_DIR . '/wh_' . strtolower($safe) . '.json';
}
function loadUser(string $u): array {
    $f = whFile($u);
    if (!file_exists($f)) return ['username'=>$u,'history'=>[],'created'=>time()];
    return json_decode(file_get_contents($f), true) ?? ['username'=>$u,'history'=>[]];
}
function saveUser(string $u, array $d): void {
    file_put_contents(whFile($u), json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
}
function out(array $d): never { echo json_encode($d); exit; }
function sanitizeUser(string $u): string {
    return substr(trim(preg_replace('/[^a-zA-Z0-9_\- ]/', '', $u)), 0, 24);
}

// ── Router ────────────────────────────────────────
$action = $_POST['action'] ?? '';

switch ($action) {

    case 'set_user':
        $u = sanitizeUser($_POST['username'] ?? '');
        if (!$u) out(['error' => 'Invalid username']);
        $d = loadUser($u);
        if (empty($d['created'])) $d['created'] = time();
        $d['username'] = $u;
        saveUser($u, $d);
        out(['ok'=>true,'username'=>$u,'history'=>$d['history']]);

    case 'save_ep':
        $u   = sanitizeUser($_POST['username'] ?? '');
        $s   = max(1, min(8, (int)($_POST['season'] ?? 0)));
        $e   = max(1, (int)($_POST['ep']     ?? 0));
        $pct = max(0, min(100, (int)($_POST['pct']   ?? 0)));
        $t   = max(0, (int)($_POST['time']  ?? 0));
        $dur = max(0, (int)($_POST['dur']   ?? 0));
        $ttl = substr(strip_tags($_POST['title'] ?? ''), 0, 100);
        if (!$u || !$s || !$e) out(['error' => 'Missing params']);
        $d   = loadUser($u);
        $key = 'S'.$s.'E'.$e;
        $existing     = $d['history'][$key] ?? [];
        $existingPct  = (int)($existing['pct'] ?? 0);
        $wasCompleted = $existingPct >= 98 || !empty($existing['completed']);
        $finalPct     = $wasCompleted ? $existingPct : $pct;
        $d['history'][$key] = [
            'season'    => $s,
            'ep'        => $e,
            'title'     => $ttl,
            'href'      => '?s='.$s.'&e='.$e,
            'ts'        => time(),
            'pct'       => $finalPct,
            'time'      => $t,
            'dur'       => $dur,
            'completed' => $wasCompleted || $pct >= 98,
        ];
        if (count($d['history']) > 200) {
            uasort($d['history'], fn($a,$b) => $a['ts'] - $b['ts']);
            array_shift($d['history']);
        }
        saveUser($u, $d);
        out(['ok' => true]);

    case 'get_history':
        $u = sanitizeUser($_POST['username'] ?? '');
        if (!$u) out(['error' => 'No username']);
        $d = loadUser($u);
        out(['ok' => true, 'history' => $d['history'] ?? []]);

    case 'clear_history':
        $u = sanitizeUser($_POST['username'] ?? '');
        if (!$u) out(['error' => 'No username']);
        $d = loadUser($u);
        $d['history'] = [];
        saveUser($u, $d);
        out(['ok' => true]);

    default:
        out(['error' => 'Unknown action']);
}
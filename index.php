<?php
require __DIR__ . '/source.php';

// ── Proxy signing secret — must match proxy.php exactly ──────
define('PROXY_SECRET', 'g0t_pr0xy_s3cr3t_k3y_ch4ng3_m3_2024_xK9mP');

// ── Helper: generate a signed, time-limited proxy URL ────────
// ── Get real visitor IP (works behind Cloudflare / proxies) ──
function getVisitorIp(): string {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
       ?? $_SERVER['HTTP_X_FORWARDED_FOR']
       ?? $_SERVER['REMOTE_ADDR']
       ?? '0.0.0.0';
    return trim(explode(',', $ip)[0]);
}

// ── Nonce store: one active token per IP+episode ─────────────
// When a new token is generated, the old one is immediately invalidated.
// Refresh = old URL dies instantly, new URL takes over.
function nonceFile(string $ip, string $key): string {
    $tmpDir = sys_get_temp_dir();
    // One file per IP+episode combination
    return $tmpDir . '/got_nonce_' . md5($ip . ':' . $key) . '.txt';
}

function saveNonce(string $ip, string $key, string $nonce): void {
    @file_put_contents(nonceFile($ip, $key), $nonce, LOCK_EX);
}

function signedProxyUrl(string $key): string {
    $key    = strtoupper($key);
    $ts     = time();
    $window = (int)floor($ts / 3600);
    $ip     = getVisitorIp();

    // Generate a one-time nonce — random 16 bytes
    // Saved server-side: only the LATEST nonce for this IP+episode is valid.
    // When page refreshes, a new nonce overwrites the old one → old URL dies.
    $nonce = bin2hex(random_bytes(16));
    saveNonce($ip, $key, $nonce);

    // Payload = base64(key:timestamp:ip:nonce)
    $payload = base64_encode($key . ':' . $ts . ':' . $ip . ':' . $nonce);

    // Signature covers key + window + ip + nonce
    $sig = hash_hmac('sha256', $key . ':' . $window . ':' . $ip . ':' . $nonce, PROXY_SECRET);

    return 'proxy.php?v=' . urlencode($payload) . '&s=' . $sig;
}

// ── Resolve current episode ───────────────────────────────────
$reqSeason  = max(1, min(8, (int)($_GET['s'] ?? 1)));
$reqEpisode = max(1, (int)($_GET['e'] ?? 1));

$curIdx = 0;
foreach ($GOT_ALL_EPS as $i => $ep) {
    if ($ep['season'] === $reqSeason && $ep['ep'] === $reqEpisode) {
        $curIdx = $i; break;
    }
}
$cur    = $GOT_ALL_EPS[$curIdx];
$prevEp = $curIdx > 0               ? $GOT_ALL_EPS[$curIdx - 1] : null;
$nextEp = $curIdx < $GOT_TOTAL - 1  ? $GOT_ALL_EPS[$curIdx + 1] : null;

function epHref(array $ep): string {
    return '?s=' . $ep['season'] . '&e=' . $ep['ep'];
}

$pageTitle = sprintf('S%02dE%02d · %s', $cur['season'], $cur['ep'], $cur['title']);

// ── Build SAFE season data for JS (NO urls — fetched via API) ─
$jsSeasons = [];
foreach ($GOT_DATA as $sn => $sd) {
    $jsSeasons[$sn] = [
        'year'     => $sd['year'],
        'thumb'    => $sd['thumb'],
        'episodes' => array_map(fn($e) => [
            'ep'    => $e['ep'],
            'title' => $e['title'],
            'date'  => $e['date'],
            'thumb' => $e['thumb'],
            'url'   => $e['url'],
            'href'  => '?s='.$sn.'&e='.$e['ep'],
        ], $sd['episodes']),
    ];
}

// ── Safe current episode info for JS (NO url) ─────────────────
// Sign the current episode's proxy URL — token expires in ~10 min
parse_str(parse_url($cur['url'], PHP_URL_QUERY) ?? '', $_curQp);
$_curKey     = strtoupper($_curQp['f'] ?? '');
$_signedUrl  = $_curKey ? signedProxyUrl($_curKey) : $cur['url'];

$jsCur  = json_encode([
    'season' => $cur['season'],
    'ep'     => $cur['ep'],
    'url'    => $_signedUrl,
    'title'  => $cur['title'],
    'date'   => $cur['date'],
], JSON_UNESCAPED_SLASHES);

$jsNext = $nextEp ? json_encode(['href' => epHref($nextEp), 'season' => $nextEp['season'], 'ep' => $nextEp['ep']]) : 'null';

$jsData = json_encode($jsSeasons, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0a0a12">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($pageTitle) ?> · Game of Thrones</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

<script src="https://content.jwplatform.com/libraries/SAHhwvZq.js"></script>
<!-- Devtool disabler -->
<script src="https://cdn.jsdelivr.net/npm/disable-devtool@latest/disable-devtool.min.js"></script>

<style>
/* ══════════════════════════════════════════════════
   DESIGN TOKENS
   ══════════════════════════════════════════════════ */
:root {
  --bg:         #08080f;
  --bg2:        #0d0d18;
  --glass:      rgba(255,255,255,.055);
  --glass-md:   rgba(255,255,255,.09);
  --glass-hi:   rgba(255,255,255,.14);
  --glass-bd:   rgba(255,255,255,.10);
  --glass-bd2:  rgba(255,255,255,.18);
  --t1:   #ffffff;
  --t2:   rgba(255,255,255,.62);
  --t3:   rgba(255,255,255,.35);
  --t4:   rgba(255,255,255,.16);
  --ac:   #3a82f6;
  --ac2:  #60a5fa;
  --ac-d: rgba(58,130,246,.18);
  --green: #34d399;
  --red:   #f87171;
  --amber: #fbbf24;
  --hh:   56px;
  --sw:   320px;
  --r:    10px;
  --r-lg: 16px;
  --r-xl: 22px;
  --blur:   saturate(200%) blur(24px);
  --blur-s: saturate(180%) blur(16px);
  --shadow: 0 8px 40px rgba(0,0,0,.6);
  --shadow-s: 0 2px 16px rgba(0,0,0,.4);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
  height: 100%; display: flex; flex-direction: column;
  font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
  background: var(--bg); color: var(--t1);
  -webkit-font-smoothing: antialiased;
  overflow: hidden;
}
body::before {
  content: '';
  position: fixed; inset: 0; pointer-events: none; z-index: 0;
  background:
    radial-gradient(ellipse 70% 45% at 15% 10%, rgba(58,130,246,.07) 0%, transparent 55%),
    radial-gradient(ellipse 50% 35% at 85% 80%, rgba(139,92,246,.05) 0%, transparent 50%),
    radial-gradient(ellipse 60% 30% at 50% 50%, rgba(0,0,0,.4) 0%, transparent 70%);
}
.glass {
  background: var(--glass);
  backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur);
  border: 1px solid var(--glass-bd);
}

/* ══ HEADER ══════════════════════════════════════════════════ */
#hdr {
  position: relative; z-index: 60; flex-shrink: 0;
  height: var(--hh);
  display: flex; align-items: center; gap: 10px;
  padding: 0 16px;
  background: rgba(8,8,15,.82);
  backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur);
  border-bottom: 1px solid var(--glass-bd);
  transition: opacity .55s ease, transform .5s ease;
  will-change: opacity, transform;
}
body.ui-hidden #hdr { opacity: 0; pointer-events: none; transform: translateY(-8px); }

.hdr-banner { flex-shrink: 0; display: flex; align-items: center; }
.hdr-banner img {
  height: 32px; width: auto; max-width: 200px;
  object-fit: contain;
  filter: drop-shadow(0 1px 8px rgba(255,255,255,.12));
}
.hdr-banner-fallback {
  font-family: 'Outfit', sans-serif;
  font-size: .78rem; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase; color: var(--t2);
}
.hdr-sep { width: 1px; height: 22px; background: var(--glass-bd2); flex-shrink: 0; }

.hdr-ep-info { flex: 1; min-width: 0; }
.hdr-ep-badge {
  font-family: 'Outfit', sans-serif;
  font-size: .62rem; font-weight: 700;
  letter-spacing: .1em; text-transform: uppercase;
  color: var(--ac2); margin-bottom: 1px;
}
.hdr-ep-title {
  font-size: .84rem; font-weight: 600; color: var(--t1);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.hdr-nav { display: flex; gap: 6px; flex-shrink: 0; }
.hdr-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 32px; padding: 0 13px;
  background: var(--glass);
  backdrop-filter: var(--blur-s); -webkit-backdrop-filter: var(--blur-s);
  border: 1px solid var(--glass-bd); border-radius: 100px;
  color: var(--t2);
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: .73rem; font-weight: 600;
  cursor: pointer; white-space: nowrap; text-decoration: none;
  transition: background .15s, border-color .15s, color .15s;
  -webkit-appearance: none;
}
.hdr-btn:hover { background: var(--glass-md); border-color: var(--glass-bd2); color: var(--t1); }
.hdr-btn svg { width: 13px; height: 13px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.hdr-btn.next-btn { background: var(--ac-d); border-color: rgba(58,130,246,.3); color: var(--ac2); }
.hdr-btn.next-btn:hover { background: rgba(58,130,246,.28); color: #fff; }
.hdr-btn.disabled { opacity: .25; pointer-events: none; }

#sidebarToggle {
  display: flex; width: 32px; height: 32px;
  align-items: center; justify-content: center;
  background: var(--glass); border: 1px solid var(--glass-bd);
  border-radius: 8px; cursor: pointer; color: var(--t2);
  transition: all .15s; flex-shrink: 0;
}
#sidebarToggle:hover { background: var(--glass-md); color: var(--t1); }
#sidebarToggle svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }

/* ══ LAYOUT ══════════════════════════════════════════════════ */
#app {
  flex: 1 1 0; display: flex;
  min-height: 0; overflow: hidden;
  position: relative; z-index: 1;
}

/* ══ PLAYER ══════════════════════════════════════════════════ */
#playerCol {
  flex: 1 1 0; min-width: 0;
  display: flex; flex-direction: column;
  background: #000; overflow: hidden;
  transition: all .35s cubic-bezier(.4,0,.2,1);
}
#playerWrap {
  flex: 1 1 0; position: relative;
  background: #000; overflow: hidden;
}
#jwplayer-cont { position: absolute; inset: 0; width: 100%; height: 100%; }
#got-player, #jw-inner { width: 100%; height: 100%; }
#jwplayer-cont > div,
#jwplayer-cont .jwplayer { width: 100% !important; height: 100% !important; }
#jwplayer-cont video { width: 100% !important; height: 100% !important; object-fit: contain; }

#playerLoader {
  position: absolute; inset: 0; z-index: 5;
  display: flex; align-items: center; justify-content: center;
  background: #000; transition: opacity .4s;
}
#playerLoader.hidden { opacity: 0; pointer-events: none; }
.loader-ring {
  width: 40px; height: 40px;
  border: 3px solid rgba(255,255,255,.1);
  border-top-color: var(--ac2);
  border-radius: 50%;
  animation: spin .8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

#infoStrip {
  flex-shrink: 0; padding: 11px 16px;
  background: rgba(8,8,15,.9);
  backdrop-filter: var(--blur-s); -webkit-backdrop-filter: var(--blur-s);
  border-top: 1px solid var(--glass-bd);
  display: flex; align-items: center; gap: 12px;
  transition: opacity .55s ease, transform .5s ease;
  will-change: opacity, transform;
}
body.ui-hidden #infoStrip { opacity: 0; pointer-events: none; transform: translateY(8px); }
.is-badge {
  font-family: 'Outfit', sans-serif;
  font-size: .64rem; font-weight: 700;
  letter-spacing: .09em; text-transform: uppercase;
  color: var(--ac2); background: var(--ac-d);
  border: 1px solid rgba(58,130,246,.22);
  padding: 3px 9px; border-radius: 100px;
  white-space: nowrap; flex-shrink: 0;
}
.is-title { font-size: .9rem; font-weight: 600; color: var(--t1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.is-date  { font-size: .75rem; color: var(--t3); white-space: nowrap; flex-shrink: 0; }

/* ══ WATERMARK ═══════════════════════════════════════════════ */
#playerWatermark {
  position: absolute; bottom: 20px; left: 20px;
  z-index: 20; pointer-events: none;
  opacity: 0; transform: translateY(4px);
  transition: opacity .55s ease, transform .5s ease;
}
body.ui-hidden #playerWatermark { opacity: 1; transform: translateY(0); }
.wm-badge {
  display: block; font-family: 'Outfit', sans-serif;
  font-size: .62rem; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase;
  color: rgba(96,165,250,.7); background: rgba(8,8,15,.35);
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(96,165,250,.18);
  padding: 3px 9px; border-radius: 100px; margin-bottom: 5px;
}
.wm-title {
  display: block; font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: .78rem; font-weight: 600;
  color: rgba(255,255,255,.45); background: rgba(8,8,15,.3);
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  padding: 3px 10px; border-radius: 6px;
  max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
@media (max-width: 700px) {
  #playerWatermark { bottom: 14px; left: 14px; }
  .wm-badge { font-size: .56rem; }
  .wm-title { font-size: .70rem; max-width: 200px; }
}

/* ══ SIDEBAR ═════════════════════════════════════════════════ */
#sidebar {
  width: var(--sw); flex-shrink: 0;
  display: flex; flex-direction: column;
  background: rgba(10,10,18,.88);
  backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur);
  border-left: 1px solid var(--glass-bd);
  overflow: hidden;
  transition: width .3s cubic-bezier(.4,0,.2,1), opacity .25s;
}
#sidebar.collapsed { width: 0 !important; opacity: 0; pointer-events: none; border-left: none; }

.sb-season-tabs {
  display: flex; flex-wrap: wrap; gap: 5px;
  padding: 10px 12px 8px;
  border-bottom: 1px solid var(--glass-bd); flex-shrink: 0;
}
.sb-stab {
  font-family: 'Outfit', sans-serif;
  font-size: .68rem; font-weight: 600;
  padding: 4px 10px; border-radius: 100px;
  border: 1px solid var(--glass-bd); background: var(--glass);
  color: var(--t3); cursor: pointer; transition: all .14s; -webkit-appearance: none;
}
.sb-stab:hover { color: var(--t2); border-color: var(--glass-bd2); }
.sb-stab.active { background: var(--ac-d); border-color: rgba(58,130,246,.3); color: var(--ac2); }

#epList { flex: 1; overflow-y: auto; padding: 4px 0 8px; }
#epList::-webkit-scrollbar { width: 3px; }
#epList::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 2px; }

.ep-row {
  display: flex; align-items: center; gap: 0;
  text-decoration: none; color: inherit; padding: 0;
  transition: background .12s; position: relative;
}
.ep-row:hover { background: var(--glass); }
.ep-row.active { background: rgba(58,130,246,.1); }
.ep-row.active::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
  background: linear-gradient(180deg, var(--ac2), var(--ac));
  border-radius: 0 2px 2px 0;
}

.ep-thumb-wrap { position: relative; flex-shrink: 0; margin: 7px 0 7px 10px; }
.ep-thumb-wrap img {
  width: 88px; height: 52px; object-fit: cover;
  border-radius: 6px; border: 1px solid var(--glass-bd); display: block;
}
.ep-thumb-overlay {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  border-radius: 6px; background: rgba(0,0,0,.45);
  opacity: 0; transition: opacity .15s;
}
.ep-row:hover .ep-thumb-overlay { opacity: 1; }
.ep-row.active .ep-thumb-overlay { opacity: 1; background: rgba(58,130,246,.35); }
.ep-thumb-play {
  width: 22px; height: 22px; background: rgba(255,255,255,.92);
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
}
.ep-thumb-play svg { width: 9px; height: 9px; fill: #111; margin-left: 1px; }

.ep-info { flex: 1; min-width: 0; padding: 7px 10px 7px 8px; }
.ep-num {
  font-family: 'Outfit', sans-serif;
  font-size: .60rem; font-weight: 700;
  color: var(--t4); letter-spacing: .08em; text-transform: uppercase; margin-bottom: 2px;
}
.ep-row.active .ep-num { color: rgba(96,165,250,.7); }
.ep-title-sb {
  font-size: .78rem; font-weight: 600; color: var(--t2);
  line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  transition: color .12s;
}
.ep-row:hover .ep-title-sb { color: var(--t1); }
.ep-row.active .ep-title-sb { color: var(--t1); }
.ep-date-sb { font-size: .68rem; color: var(--t3); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.ep-playing { display: none; align-items: center; gap: 3px; margin-top: 3px; }
.ep-row.active .ep-playing { display: flex; }
.pdot { width: 3px; height: 3px; border-radius: 50%; background: var(--ac2); animation: pdot 1s ease-in-out infinite; }
.pdot:nth-child(2) { animation-delay: .18s; }
.pdot:nth-child(3) { animation-delay: .36s; }
@keyframes pdot { 0%,100%{opacity:1;transform:scaleY(1)} 50%{opacity:.3;transform:scaleY(.5)} }
.ep-playing-lbl { font-size: .60rem; font-weight: 700; color: var(--ac2); letter-spacing: .07em; }

/* ══ MOBILE SHEET ════════════════════════════════════════════ */
#mobileSheet { display: none; position: fixed; inset: 0; z-index: 200; }
#mobileSheetBg {
  position: absolute; inset: 0;
  background: rgba(0,0,0,.55);
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
}
#mobileSheetPanel {
  position: absolute; bottom: 0; left: 0; right: 0; height: 75vh;
  background: rgba(12,12,20,.96);
  backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur);
  border-top: 1px solid var(--glass-bd);
  border-radius: 18px 18px 0 0;
  display: flex; flex-direction: column;
  transform: translateY(100%);
  transition: transform .35s cubic-bezier(.4,0,.2,1);
  overflow: hidden;
}
#mobileSheetPanel.open { transform: translateY(0); }
.sheet-handle {
  width: 36px; height: 4px; background: rgba(255,255,255,.2); border-radius: 2px;
  margin: 10px auto 6px; flex-shrink: 0;
}
.sheet-hd {
  padding: 6px 14px 8px; border-bottom: 1px solid var(--glass-bd); flex-shrink: 0;
  font-family: 'Outfit', sans-serif;
  font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--t3);
}
#mobileEpList { flex: 1; overflow-y: auto; padding: 4px 0 env(safe-area-inset-bottom, 12px); }
#mobileEpList::-webkit-scrollbar { display: none; }

#mobileFab {
  display: none;
  position: fixed; bottom: calc(16px + env(safe-area-inset-bottom, 0));
  right: 16px; z-index: 100;
  width: 48px; height: 48px;
  background: var(--ac); border-radius: 50%; border: none; cursor: pointer;
  align-items: center; justify-content: center;
  box-shadow: 0 4px 20px rgba(58,130,246,.45); color: #fff;
}
#mobileFab svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* ══ MOBILE SWIPE FULLSCREEN ═════════════════════════════════ */
body.player-fullscreen { overflow: hidden; }
body.player-fullscreen #playerCol {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  z-index: 500; height: 100dvh !important; max-height: none !important;
  flex-direction: column; background: #000;
}
body.player-fullscreen #playerWrap { flex: 1 1 0; }
body.player-fullscreen #infoStrip        { display: none !important; }
body.player-fullscreen #hdr              { display: none !important; }
body.player-fullscreen #mobileFab        { display: none !important; }
body.player-fullscreen #continueBanner   { display: none !important; }
body.player-fullscreen #app > #sidebar   { display: none !important; }

#swipeHint {
  position: fixed;
  top: max(14px, env(safe-area-inset-top, 14px));
  left: 50%; transform: translateX(-50%);
  z-index: 600;
  background: rgba(255,255,255,.15);
  backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 100px; padding: 6px 16px;
  font-family: 'Outfit', sans-serif;
  font-size: .68rem; font-weight: 600; letter-spacing: .07em;
  color: rgba(255,255,255,.8);
  pointer-events: none; opacity: 0; transition: opacity .3s ease;
  white-space: nowrap; display: flex; align-items: center; gap: 6px;
}
#swipeHint.show { opacity: 1; }
#swipeHint svg { width: 11px; height: 11px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

#swipeDragIndicator {
  position: absolute; bottom: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--ac), var(--ac2));
  border-radius: 2px 2px 0 0;
  transform: scaleX(0); transform-origin: left;
  transition: transform .05s linear, opacity .2s;
  opacity: 0; z-index: 30; pointer-events: none;
}
#swipeDragIndicator.visible { opacity: 1; }

/* ══ RESPONSIVE ══════════════════════════════════════════════ */
@media (max-width: 900px) { :root { --sw: 280px; } }

@media (max-width: 700px) {
  :root { --hh: 50px; }
  body { overflow: hidden; }
  #app { flex-direction: column; overflow: hidden; }
  #playerCol {
    flex: none;
    height: clamp(180px, 56.25vw, calc(100dvh - var(--hh) - 38px - 140px));
  }
  #playerWrap { width: 100%; height: 100%; }
  #jwplayer-cont .jwplayer,
  #jwplayer-cont .jw-wrapper,
  #jwplayer-cont .jw-media,
  #jwplayer-cont video { object-fit: contain !important; }
  #sidebar { display: none !important; }
  #infoStrip { padding: 8px 12px; }
  .is-date { display: none; }
  .hdr-ep-title { font-size: .78rem; }
  .hdr-btn span { display: none; }
  .hdr-btn { padding: 0 10px; }
  #sidebarToggle { display: none; }
  #mobileSheet { display: block; }
  #mobileFab { display: flex; }
}

@media (max-width: 420px) {
  :root { --hh: 46px; }
  #hdr { padding: 0 10px; gap: 7px; }
  .hdr-banner img { height: 26px; max-width: 130px; }
  .hdr-btn { height: 28px; font-size: .68rem; }
}

@media (max-height: 500px) and (max-width: 900px) {
  #app { flex-direction: row; overflow: hidden; }
  #playerCol { flex: 1 1 0; height: auto !important; max-height: none !important; }
  #playerWrap { flex: 1 1 0; }
  #infoStrip { display: none; }
  #sidebar { display: flex !important; width: 220px !important; flex-shrink: 0; }
  #mobileSheet { display: none !important; }
  #mobileFab   { display: none !important; }
  .ep-thumb-wrap img { width: 70px; height: 42px; }
}

* { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.1) transparent; }

/* ══ USERNAME OVERLAY ════════════════════════════════════════ */
#usernameOverlay {
  position: fixed; inset: 0; z-index: 900;
  display: flex; align-items: center; justify-content: center;
  background: rgba(5,5,12,.82);
  backdrop-filter: blur(28px) saturate(180%); -webkit-backdrop-filter: blur(28px) saturate(180%);
  opacity: 0; pointer-events: none; transition: opacity .3s ease;
}
#usernameOverlay.open { opacity: 1; pointer-events: auto; }
.uname-box {
  background: rgba(16,16,28,.92);
  border: 1px solid rgba(255,255,255,.14);
  border-radius: 20px; padding: 32px 28px 28px;
  width: min(380px, 92vw);
  box-shadow: 0 24px 60px rgba(0,0,0,.7), 0 0 0 1px rgba(255,255,255,.06) inset;
  animation: popUp .3s cubic-bezier(.34,1.56,.64,1);
}
@keyframes popUp {
  from { transform: scale(.88) translateY(12px); opacity:0; }
  to   { transform: scale(1) translateY(0); opacity:1; }
}
.uname-icon {
  width: 52px; height: 52px; border-radius: 50%;
  background: linear-gradient(135deg, var(--ac-d), rgba(139,92,246,.2));
  border: 1px solid rgba(58,130,246,.3);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 18px; font-size: 1.4rem;
}
.uname-heading { font-family: 'Outfit', sans-serif; font-size: 1.1rem; font-weight: 700; text-align: center; color: var(--t1); margin-bottom: 6px; }
.uname-sub { font-size: .80rem; color: var(--t3); text-align: center; margin-bottom: 22px; line-height: 1.55; }
.uname-input {
  width: 100%; padding: 11px 14px;
  background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
  border-radius: 10px; color: var(--t1);
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: .9rem; outline: none;
  transition: border-color .15s, box-shadow .15s; margin-bottom: 12px;
}
.uname-input:focus { border-color: rgba(58,130,246,.5); box-shadow: 0 0 0 3px rgba(58,130,246,.12); }
.uname-input::placeholder { color: var(--t4); }
.uname-btn {
  width: 100%; padding: 11px; background: var(--ac);
  border: none; border-radius: 10px; color: #fff;
  font-family: 'Outfit', sans-serif; font-size: .88rem; font-weight: 700;
  cursor: pointer; letter-spacing: .03em;
  transition: opacity .15s, transform .1s;
  box-shadow: 0 4px 18px rgba(58,130,246,.35);
}
.uname-btn:hover  { opacity: .9; }
.uname-btn:active { transform: scale(.98); }
.uname-skip {
  display: block; text-align: center; font-size: .75rem; color: var(--t3);
  margin-top: 12px; cursor: pointer; transition: color .15s;
}
.uname-skip:hover { color: var(--t2); }

/* ══ PROFILE BTN + POPOVER ═══════════════════════════════════ */
#profileBtn {
  display: inline-flex; align-items: center; gap: 6px;
  height: 32px; padding: 0 10px 0 7px;
  background: var(--glass); border: 1px solid var(--glass-bd);
  border-radius: 100px; cursor: pointer;
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: .73rem; font-weight: 600;
  color: var(--t2); transition: background .15s, border-color .15s, color .15s; flex-shrink: 0;
}
#profileBtn:hover { background: var(--glass-md); border-color: var(--glass-bd2); color: var(--t1); }
.prof-av {
  width: 22px; height: 22px; border-radius: 50%;
  background: linear-gradient(135deg, var(--ac), #7c3aed);
  display: flex; align-items: center; justify-content: center;
  font-size: .65rem; font-weight: 800; color: #fff; flex-shrink: 0;
}
#profilePopover {
  position: fixed; top: calc(var(--hh) + 6px); right: 14px; z-index: 700;
  width: 260px;
  background: rgba(14,14,24,.96);
  backdrop-filter: var(--blur); -webkit-backdrop-filter: var(--blur);
  border: 1px solid rgba(255,255,255,.13); border-radius: 14px;
  box-shadow: 0 16px 48px rgba(0,0,0,.6); padding: 14px; display: none;
  animation: slideDown2 .2s cubic-bezier(.34,1.56,.64,1);
}
@keyframes slideDown2 {
  from { opacity:0; transform: translateY(-8px) scale(.96); }
  to   { opacity:1; transform: translateY(0) scale(1); }
}
#profilePopover.open { display: block; }
.pop-head { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.pop-av {
  width: 38px; height: 38px; border-radius: 50%;
  background: linear-gradient(135deg, var(--ac), #7c3aed);
  display: flex; align-items: center; justify-content: center;
  font-size: .9rem; font-weight: 800; color: #fff; flex-shrink: 0;
}
.pop-name { font-size: .9rem; font-weight: 700; color: var(--t1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pop-sub  { font-size: .70rem; color: var(--t3); margin-top: 1px; }
.pop-divider { height: 1px; background: var(--glass-bd); margin: 10px 0; }
.pop-btn {
  display: flex; align-items: center; gap: 8px;
  width: 100%; padding: 8px 10px; border: none; background: none;
  border-radius: 8px; cursor: pointer; color: var(--t2);
  font-family: inherit; font-size: .80rem; font-weight: 500; text-align: left;
  transition: background .12s, color .12s;
}
.pop-btn:hover { background: var(--glass); color: var(--t1); }
.pop-btn svg { width: 14px; height: 14px; flex-shrink: 0; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.pop-btn.danger { color: #f87171; }
.pop-btn.danger:hover { background: rgba(248,113,113,.1); }

/* ══ CONTINUE WATCHING BANNER ════════════════════════════════ */
#continueBanner {
  flex-shrink: 0; padding: 9px 16px;
  background: rgba(58,130,246,.1); border-bottom: 1px solid rgba(58,130,246,.2);
  align-items: center; gap: 10px; font-size: .80rem; z-index: 55;
  max-height: 0; overflow: hidden; display: flex; opacity: 0;
  transition: max-height .4s cubic-bezier(.4,0,.2,1), opacity .3s ease;
}
#continueBanner.show { max-height: 60px; opacity: 1; }
.cb-text { flex: 1; color: var(--t2); min-width: 0; }
.cb-text strong { color: var(--t1); }
.cb-btn {
  flex-shrink: 0; padding: 5px 13px; background: var(--ac); border: none;
  border-radius: 100px; color: #fff; font-family: 'Outfit', sans-serif;
  font-size: .72rem; font-weight: 700; letter-spacing: .04em;
  cursor: pointer; transition: opacity .15s; white-space: nowrap;
}
.cb-btn:hover { opacity: .88; }
.cb-close { background: none; border: none; cursor: pointer; color: var(--t3); font-size: 1rem; padding: 2px 4px; transition: color .12s; }
.cb-close:hover { color: var(--t1); }

/* ══ WATCH HISTORY BADGES ════════════════════════════════════ */
.ep-progress-bar {
  height: 2.5px; background: rgba(255,255,255,.08); border-radius: 2px;
  margin-top: 5px; overflow: hidden; opacity: 0; transition: opacity .2s;
}
.ep-row.ep-started  .ep-progress-bar { opacity: 1; }
.ep-row.ep-finished .ep-progress-bar { opacity: 0; }
.ep-progress-fill {
  height: 100%; background: linear-gradient(90deg, var(--ac), var(--ac2));
  border-radius: 2px; transition: width .4s ease;
}
.ep-tick {
  display: none; width: 16px; height: 16px; border-radius: 50%;
  background: var(--green); align-items: center; justify-content: center;
  flex-shrink: 0; box-shadow: 0 0 8px rgba(52,211,153,.4);
}
.ep-tick svg { width: 9px; height: 9px; fill: none; stroke: #fff; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.ep-row.ep-finished .ep-tick { display: flex; }
.ep-row.ep-finished .ep-thumb-wrap img { filter: brightness(.75) saturate(.7); }
.ep-row.ep-finished .ep-thumb-overlay { opacity: 1 !important; background: rgba(52,211,153,.18) !important; }
.ep-row.ep-finished .ep-title-sb { color: var(--t3); }
.ep-row.ep-started  .ep-title-sb { color: var(--t2); }
</style>
</head>
<body>

<!-- SWIPE HINT -->
<div id="swipeHint">
  <svg viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>
  Swipe up to exit fullscreen
</div>

<!-- USERNAME OVERLAY -->
<div id="usernameOverlay">
  <div class="uname-box">
    <div class="uname-icon">👤</div>
    <div class="uname-heading">Who's watching?</div>
    <div class="uname-sub">Enter a username so we can remember<br>where you left off across sessions.</div>
    <input class="uname-input" id="unameInput" type="text"
           placeholder="e.g. Jon, Daenerys, Tyrion…"
           maxlength="24" autocomplete="off" spellcheck="false">
    <button class="uname-btn" id="unameConfirm">Start Watching</button>
    <span class="uname-skip" id="unameSkip">Skip for now</span>
  </div>
</div>

<!-- CONTINUE WATCHING BANNER -->
<div id="continueBanner">
  <span class="cb-text" id="cbText"></span>
  <button class="cb-btn" id="cbResume">Resume</button>
  <button class="cb-close" id="cbClose">✕</button>
</div>

<!-- HEADER -->
<header id="hdr">
  <div class="hdr-banner">
    <img src="https://www.craveyoutv.com/wp-content/uploads/2020/01/GoTTitle.png"
         alt="Game of Thrones"
         onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
    <span class="hdr-banner-fallback" style="display:none">GOT</span>
  </div>
  <div class="hdr-sep"></div>
  <div class="hdr-ep-info">
    <div class="hdr-ep-badge" id="hdrBadge">S<?= sprintf('%02d',$cur['season']) ?>E<?= sprintf('%02d',$cur['ep']) ?></div>
    <div class="hdr-ep-title" id="hdrTitle"><?= htmlspecialchars($cur['title']) ?></div>
  </div>
  <nav class="hdr-nav">
    <a id="btnPrev" class="hdr-btn<?= !$prevEp ? ' disabled' : '' ?>"
       href="<?= $prevEp ? htmlspecialchars(epHref($prevEp)) : '#' ?>">
      <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      <span>Prev</span>
    </a>
    <a id="btnNext" class="hdr-btn next-btn<?= !$nextEp ? ' disabled' : '' ?>"
       href="<?= $nextEp ? htmlspecialchars(epHref($nextEp)) : '#' ?>">
      <span>Next</span>
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <div id="profileBtn" onclick="toggleProfilePopover()" title="Profile">
      <div class="prof-av" id="profAv">?</div>
      <span id="profName" style="max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Guest</span>
    </div>
    <div id="sidebarToggle" onclick="toggleSidebar()" title="Episodes">
      <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    </div>
  </nav>
</header>

<!-- APP -->
<div id="app">
  <div id="playerCol">
    <div id="playerWrap">
      <div id="jwplayer-cont"><div id="got-player"></div></div>
      <div id="playerLoader"><div class="loader-ring"></div></div>
      <div id="swipeDragIndicator"></div>
      <div id="playerWatermark">
        <span class="wm-badge" id="wmBadge"></span>
        <span class="wm-title" id="wmTitle"></span>
      </div>
    </div>
    <div id="infoStrip">
      <span class="is-badge" id="isBadge">S<?= sprintf('%02d',$cur['season']) ?>E<?= sprintf('%02d',$cur['ep']) ?></span>
      <span class="is-title" id="isTitle"><?= htmlspecialchars($cur['title']) ?></span>
      <span class="is-date"  id="isDate"><?= htmlspecialchars($cur['date']) ?></span>
    </div>
  </div>
  <aside id="sidebar">
    <div class="sb-season-tabs" id="sbSeasonTabs"></div>
    <div id="epList"></div>
  </aside>
</div>

<!-- MOBILE SHEET -->
<div id="mobileSheet">
  <div id="mobileSheetBg" onclick="closeMobileSheet()"></div>
  <div id="mobileSheetPanel">
    <div class="sheet-handle"></div>
    <div class="sheet-hd">Episodes</div>
    <div class="sb-season-tabs" id="mobileSeasonTabs" style="padding:8px 12px 6px"></div>
    <div id="mobileEpList"></div>
  </div>
</div>

<button id="mobileFab" onclick="openMobileSheet()" aria-label="Episode list">
  <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>
</button>

<script>
/* ── DATA (no video URLs exposed) ────────────────── */
const SEASONS     = <?= $jsData ?>;
const CUR         = <?= $jsCur ?>;
const NEXT        = <?= $jsNext ?>;
const SEASON_KEYS = Object.keys(SEASONS).map(Number);


/* ── JW PLAYER ───────────────────────────────────── */
let jw = null;

function initPlayer(url) {
  const loader = document.getElementById('playerLoader');
  loader.classList.remove('hidden');
  if (jw) {
    try { jw.load([{ file: url, type: 'mp4' }]); jw.play(); } catch(e) {}
    return;
  }
  document.getElementById('got-player').innerHTML = '';
  const div = document.createElement('div');
  div.id = 'jw-inner';
  document.getElementById('got-player').appendChild(div);

  const _wrap = document.getElementById('playerWrap');
  const _pw   = _wrap ? _wrap.offsetWidth  : window.innerWidth;
  const _ph   = _wrap ? _wrap.offsetHeight : window.innerHeight;

  jw = jwplayer('jw-inner').setup({
    file:       url,
    type:       'mp4',
    width:      _pw,
    height:     _ph,
    autostart:  true,
    mute:       false,
    controls:   true,
    stretching: 'uniform',
    cast:       {},
    skin:       { name: 'netflix' },
  });
  jw.on('ready',      () => { loader.classList.add('hidden'); jwResize(); });
  jw.on('play',       () => { loader.classList.add('hidden'); _isPlaying = true; showUI(); });
  jw.on('pause',      () => { _isPlaying = false; showUI(); clearTimeout(_uiTimer); });
  jw.on('idle',       () => { _isPlaying = false; showUI(); clearTimeout(_uiTimer); });
  jw.on('bufferFull', () => loader.classList.add('hidden'));
  jw.on('error',      () => { loader.classList.add('hidden'); _isPlaying = false; showUI(); });
  jw.on('complete',   () => { if (NEXT) window.location.href = NEXT.href; });
}



/* ── UI AUTO-HIDE ────────────────────────────────── */
let _uiTimer   = null;
let _isPlaying = false;
const UI_HIDE_DELAY = 3500;

function showUI() {
  document.body.classList.remove('ui-hidden');
  clearTimeout(_uiTimer);
  if (_isPlaying) _uiTimer = setTimeout(hideUI, UI_HIDE_DELAY);
}
function hideUI() {
  if (!_isPlaying) return;
  document.body.classList.add('ui-hidden');
}
function resetIdleTimer() { showUI(); }
['mousemove','mousedown','touchstart','keydown'].forEach(evt => {
  document.addEventListener(evt, resetIdleTimer, { passive: true });
});

function updateWatermark(badge, title) {
  const wb = document.getElementById('wmBadge');
  const wt = document.getElementById('wmTitle');
  if (wb) wb.textContent = badge;
  if (wt) wt.textContent = title;
}

/* ── JW RESIZE ───────────────────────────────────── */
function jwResize() {
  if (!jw) return;
  const wrap = document.getElementById('playerWrap');
  if (!wrap) return;
  const w = wrap.offsetWidth, h = wrap.offsetHeight;
  if (w > 0 && h > 0) try { jw.resize(w, h); } catch(e) {}
}
window.addEventListener('resize', jwResize);
window.addEventListener('orientationchange', () => setTimeout(jwResize, 200));

/* ── SIDEBAR ─────────────────────────────────────── */
let sidebarOpen     = true;
let activeSeason    = CUR.season;
let mobileSheetOpen = false;

function toggleSidebar() {
  sidebarOpen = !sidebarOpen;
  document.getElementById('sidebar').classList.toggle('collapsed', !sidebarOpen);
  setTimeout(jwResize, 320);
}

/* ── EPISODE ROW BUILDER ─────────────────────────── */
function buildEpRow(ep, sNum) {
  const isActive = (sNum === CUR.season && ep.ep === CUR.ep);
  const a = document.createElement('a');
  a.className = 'ep-row' + (isActive ? ' active' : '');
  a.href = ep.href;
  a.innerHTML = `
    <div class="ep-thumb-wrap">
      <img src="${ep.thumb}" alt="" loading="lazy"
           onerror="this.src='https://upload.wikimedia.org/wikipedia/en/d/d8/Game_of_Thrones_title_card.jpg'">
      <div class="ep-thumb-overlay">
        <div class="ep-thumb-play"><svg viewBox="0 0 24 24"><path d="M5 3l14 9-14 9V3z"/></svg></div>
      </div>
    </div>
    <div class="ep-info">
      <div class="ep-num">S${String(sNum).padStart(2,'0')}E${String(ep.ep).padStart(2,'0')}</div>
      <div class="ep-title-sb">${escHtml(ep.title)}</div>
      <div class="ep-date-sb">${escHtml(ep.date)}</div>
      <div class="ep-playing">
        <div class="pdot"></div><div class="pdot"></div><div class="pdot"></div>
        <span class="ep-playing-lbl">NOW PLAYING</span>
      </div>
    </div>`;
  return a;
}

/* ── RENDER SEASON LIST ──────────────────────────── */
function renderSeasonList(container, sNum) {
  container.innerHTML = '';
  const season = SEASONS[sNum];
  if (!season) return;
  season.episodes.forEach(ep => container.appendChild(buildEpRow(ep, sNum)));
  const active = container.querySelector('.active');
  if (active) setTimeout(() => active.scrollIntoView({ block: 'nearest', behavior: 'smooth' }), 80);
}

/* ── RENDER SEASON TABS ──────────────────────────── */
function renderSeasonTabs(tabsEl, listEl) {
  tabsEl.innerHTML = '';
  SEASON_KEYS.forEach(sNum => {
    const btn = document.createElement('button');
    btn.className = 'sb-stab' + (sNum === activeSeason ? ' active' : '');
    btn.textContent = 'S' + sNum;
    btn.onclick = () => {
      activeSeason = sNum;
      tabsEl.querySelectorAll('.sb-stab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      syncSeasonTabs(sNum);
      renderSeasonList(listEl, sNum);
    };
    tabsEl.appendChild(btn);
  });
  renderSeasonList(listEl, activeSeason);
}

function syncSeasonTabs(sNum) {
  ['sbSeasonTabs','mobileSeasonTabs'].forEach(id => {
    const bar = document.getElementById(id);
    if (!bar) return;
    bar.querySelectorAll('.sb-stab').forEach((b, i) => {
      b.classList.toggle('active', SEASON_KEYS[i] === sNum);
    });
  });
}

/* ── MOBILE SHEET ────────────────────────────────── */
function openMobileSheet() {
  mobileSheetOpen = true;
  document.getElementById('mobileSheet').style.display = 'block';
  setTimeout(() => document.getElementById('mobileSheetPanel').classList.add('open'), 10);
  const active = document.getElementById('mobileEpList').querySelector('.active');
  if (active) setTimeout(() => active.scrollIntoView({ block: 'nearest', behavior: 'smooth' }), 200);
}
function closeMobileSheet() {
  document.getElementById('mobileSheetPanel').classList.remove('open');
  setTimeout(() => { document.getElementById('mobileSheet').style.display = 'none'; mobileSheetOpen = false; }, 340);
}

/* ── ESCAPE HTML ─────────────────────────────────── */
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── KEYBOARD NAVIGATION ─────────────────────────── */
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
  if (e.altKey && e.key === 'ArrowRight') {
    const nb = document.getElementById('btnNext');
    if (nb && !nb.classList.contains('disabled')) window.location.href = nb.href;
  }
  if (e.altKey && e.key === 'ArrowLeft') {
    const pb = document.getElementById('btnPrev');
    if (pb && !pb.classList.contains('disabled')) window.location.href = pb.href;
  }
  if (e.key === 'Escape') {
    if (mobileSheetOpen) closeMobileSheet();
    if (_playerFullscreen) exitPlayerFullscreen();
  }
});

/* ══════════════════════════════════════════════════
   MOBILE SWIPE GESTURE
   ══════════════════════════════════════════════════ */
let _playerFullscreen = false;
let _swipeTouchStartX = 0;
let _swipeTouchStartY = 0;
let _swipeDragging    = false;
let _swipeLastDy      = 0;

const SWIPE_THRESHOLD  = 52;
const SWIPE_AXIS_RATIO = 1.5;
const MAX_MOBILE_W     = 900;

function isMobileSwipe() { return window.innerWidth <= MAX_MOBILE_W; }

function enterPlayerFullscreen() {
  if (_playerFullscreen) return;
  _playerFullscreen = true;
  document.body.classList.add('player-fullscreen');
  setTimeout(jwResize, 50);
  setTimeout(jwResize, 380);
  const hint = document.getElementById('swipeHint');
  if (hint) { hint.classList.add('show'); setTimeout(() => hint.classList.remove('show'), 2400); }
}

function exitPlayerFullscreen() {
  if (!_playerFullscreen) return;
  _playerFullscreen = false;
  document.body.classList.remove('player-fullscreen');
  setTimeout(jwResize, 50);
  setTimeout(jwResize, 380);
}

window.addEventListener('orientationchange', () => {
  setTimeout(() => {
    if (_playerFullscreen) {
      const isLandscape = window.screen.orientation
        ? window.screen.orientation.type.startsWith('landscape')
        : window.innerWidth > window.innerHeight;
      if (isLandscape) exitPlayerFullscreen();
    }
  }, 300);
});

(function attachSwipeGesture() {
  const playerWrap = document.getElementById('playerWrap');
  const dragBar    = document.getElementById('swipeDragIndicator');
  if (!playerWrap) return;

  playerWrap.addEventListener('touchstart', function(e) {
    if (!isMobileSwipe() || mobileSheetOpen) return;
    const t = e.touches[0];
    _swipeTouchStartX = t.clientX;
    _swipeTouchStartY = t.clientY;
    _swipeDragging = false; _swipeLastDy = 0;
  }, { passive: true });

  playerWrap.addEventListener('touchmove', function(e) {
    if (!isMobileSwipe() || mobileSheetOpen) return;
    const t   = e.touches[0];
    const dx  = t.clientX - _swipeTouchStartX;
    const dy  = t.clientY - _swipeTouchStartY;
    const absDx = Math.abs(dx), absDy = Math.abs(dy);
    _swipeLastDy = dy;
    if (absDy < 8) return;
    if (absDx > absDy / SWIPE_AXIS_RATIO) return;
    _swipeDragging = true;
    if (dragBar) {
      const progress = Math.min(Math.abs(dy) / SWIPE_THRESHOLD, 1);
      dragBar.style.transform = `scaleX(${progress})`;
      dragBar.style.opacity   = String(progress * 0.9);
      dragBar.classList.add('visible');
    }
  }, { passive: true });

  playerWrap.addEventListener('touchend', function() {
    if (!isMobileSwipe() || mobileSheetOpen) return;
    if (dragBar) {
      dragBar.classList.remove('visible');
      dragBar.style.transform = 'scaleX(0)';
      dragBar.style.opacity   = '0';
    }
    if (!_swipeDragging) return;
    if (Math.abs(_swipeLastDy) < SWIPE_THRESHOLD) return;
    if (_swipeLastDy > 0 && !_playerFullscreen) enterPlayerFullscreen();
    else if (_swipeLastDy < 0 && _playerFullscreen) exitPlayerFullscreen();
    _swipeDragging = false; _swipeLastDy = 0;
  }, { passive: true });

  playerWrap.addEventListener('touchcancel', function() {
    _swipeDragging = false; _swipeLastDy = 0;
    if (dragBar) {
      dragBar.classList.remove('visible');
      dragBar.style.transform = 'scaleX(0)';
      dragBar.style.opacity   = '0';
    }
  }, { passive: true });
})();

/* ══════════════════════════════════════════════════
   WATCH HISTORY SYSTEM
   ══════════════════════════════════════════════════ */
const LS_KEY = 'got_wh_user';
let _history = {};

function getUsername() { return localStorage.getItem(LS_KEY) || ''; }
function getHistory()  { return _history; }

async function apiPost(data) {
  const fd = new FormData();
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  try {
    const r = await fetch('save.php', { method:'POST', body: fd });
    return await r.json();
  } catch(e) { return { error: 'network' }; }
}

async function setUsername(name) {
  name = name.trim();
  localStorage.setItem(LS_KEY, name);
  const d = await apiPost({ action:'set_user', username: name });
  if (d.ok) _history = d.history || {};
  refreshProfileUI();
  refreshEpRowHistory();
  return d;
}

let _saveTimer    = null;
let _lastSaveData = null;

function saveEpisode(season, ep, pct, timePos, duration) {
  const u = getUsername(); if (!u) return;
  const key          = 'S' + season + 'E' + ep;
  const existing     = _history[key];
  const wasCompleted = existing && (existing.pct || 0) >= 98;
  const finalPct     = wasCompleted ? existing.pct : Math.round(pct);
  const entry = {
    season, ep, title: CUR.title,
    href:      '?s=' + season + '&e=' + ep,
    ts:        Date.now() / 1000,
    pct:       finalPct,
    time:      Math.round(timePos || 0),
    dur:       Math.round(duration || 0),
    completed: wasCompleted || Math.round(pct) >= 98,
  };
  _history[key]  = entry;
  _lastSaveData  = { u, season, ep, pct: entry.pct, time: entry.time, dur: entry.dur };
  clearTimeout(_saveTimer);
  _saveTimer = setTimeout(() => { _flushSave(); }, 8000);
}

function _flushSave() {
  if (!_lastSaveData) return;
  const d = _lastSaveData; _lastSaveData = null;
  apiPost({ action:'save_ep', username: d.u, season: d.season, ep: d.ep, pct: d.pct, time: d.time, dur: d.dur, title: CUR.title });
  refreshEpRowHistory();
}

window.addEventListener('pagehide',         () => { clearTimeout(_saveTimer); _flushSave(); });
window.addEventListener('beforeunload',     () => { clearTimeout(_saveTimer); _flushSave(); });
window.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') { clearTimeout(_saveTimer); _flushSave(); } });

function getLastWatched() {
  const entries = Object.values(_history);
  if (!entries.length) return null;
  entries.sort((a,b) => (b.ts||0) - (a.ts||0));
  return entries[0];
}

async function loadHistoryFromServer() {
  const u = getUsername(); if (!u) return;
  const d = await apiPost({ action:'get_history', username: u });
  if (d.ok) { _history = d.history || {}; refreshEpRowHistory(); refreshProfileUI(); }
}

function resolveNextAction() {
  const entries = Object.values(_history);
  if (!entries.length) return { nothing: true };
  entries.sort((a, b) => (b.ts || 0) - (a.ts || 0));
  const latest = entries[0];
  const pct    = latest.pct || 0;
  if (pct >= 2 && pct < 98) {
    const onPage = (latest.season === CUR.season && latest.ep === CUR.ep);
    return onPage ? { seek: true, time: latest.time || 0 } : { redirect: true, href: latest.href };
  }
  if (pct >= 98) {
    const allEps = [];
    Object.keys(SEASONS).map(Number).sort((a,b) => a-b).forEach(sn => {
      SEASONS[sn].episodes.forEach(ep => allEps.push({ season: sn, ep: ep.ep, href: ep.href }));
    });
    const idx  = allEps.findIndex(e => e.season === latest.season && e.ep === latest.ep);
    const next = idx >= 0 && idx + 1 < allEps.length ? allEps[idx + 1] : allEps[0];
    const onPage = (next.season === CUR.season && next.ep === CUR.ep);
    return onPage ? { nothing: true } : { redirect: true, href: next.href };
  }
  return { nothing: true };
}

/* ── Profile UI ──────────────────────────────────── */
function refreshProfileUI() {
  const name    = getUsername();
  const display = name || 'Guest';
  const initial = display[0].toUpperCase();
  ['profAv','popAv'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = initial; });
  const pn = document.getElementById('profName'); if (pn) pn.textContent = display;
  const popName = document.getElementById('popName'); if (popName) popName.textContent = display;
  const popSub  = document.getElementById('popSub');
  if (popSub) {
    const last = getLastWatched();
    if (!last) { popSub.textContent = 'No history yet'; }
    else if ((last.pct||0) >= 98) { popSub.textContent = 'Last: S' + String(last.season).padStart(2,'0') + 'E' + String(last.ep).padStart(2,'0') + ' · Finished'; }
    else { popSub.textContent = 'Watching: S' + String(last.season).padStart(2,'0') + 'E' + String(last.ep).padStart(2,'0') + ' · ' + (last.pct||0) + '%'; }
  }
}

/* ── Username overlay ────────────────────────────── */
function openUsernamePrompt() {
  const ov = document.getElementById('usernameOverlay');
  if (ov) { ov.classList.add('open'); setTimeout(() => document.getElementById('unameInput').focus(), 100); }
}
function closeUsernamePrompt() {
  const ov = document.getElementById('usernameOverlay');
  if (ov) ov.classList.remove('open');
}
async function confirmUsername() {
  const val = document.getElementById('unameInput').value.trim();
  if (!val) {
    const inp = document.getElementById('unameInput');
    inp.focus(); inp.style.borderColor = 'rgba(248,113,113,.5)';
    setTimeout(() => inp.style.borderColor = '', 1200);
    return;
  }
  const btn = document.getElementById('unameConfirm');
  btn.textContent = 'Loading…'; btn.disabled = true;
  await setUsername(val);
  btn.textContent = 'Start Watching'; btn.disabled = false;
  closeUsernamePrompt();
  const action = resolveNextAction();
  if (action.redirect) {
    const dest = action.href + (action.href.includes('?') ? '&' : '?') + 'resume=1';
    window.location.replace(dest);
  } else if (action.seek && action.time > 0) {
    _pendingSeek = action.time; _seekConfirmed = false;
    if (jw) try { jw.seek(action.time); } catch(e) {}
  }
}
function openChangeUsername() {
  closeProfilePopover();
  const input = document.getElementById('unameInput');
  const btn   = document.getElementById('unameConfirm');
  input.value = getUsername();
  btn.textContent = 'Save Username';
  document.getElementById('usernameOverlay').classList.add('open');
  setTimeout(() => { input.focus(); input.select(); }, 100);
}

/* ── Continue banner ─────────────────────────────── */
function showContinueBanner() {
  if (!getUsername()) return;
  const last = getLastWatched(); if (!last) return;
  const curKey  = 'S' + CUR.season + 'E' + CUR.ep;
  const lastKey = 'S' + last.season + 'E' + last.ep;
  if (curKey === lastKey && (last.pct||0) >= 95) return;
  const banner = document.getElementById('continueBanner');
  const text   = document.getElementById('cbText');
  if (!banner || !text) return;
  const badge = 'S' + String(last.season).padStart(2,'0') + 'E' + String(last.ep).padStart(2,'0');
  if (curKey === lastKey) {
    text.innerHTML = 'Welcome back <strong>' + escHtml(getUsername()) + '</strong> — resume <strong>' + badge + ' · ' + escHtml(last.title) + '</strong> at ' + (last.pct||0) + '%';
  } else {
    text.innerHTML = 'Welcome back <strong>' + escHtml(getUsername()) + '</strong> — continue <strong>' + badge + ' · ' + escHtml(last.title) + '</strong>';
  }
  document.getElementById('cbResume').onclick = () => {
    banner.classList.remove('show');
    if (curKey !== lastKey) { window.location.href = last.href; }
    else if (last.time > 0) {
      _pendingSeek = last.time; _seekConfirmed = false;
      if (jw) {
        try { jw.seek(last.time); } catch(e) {}
        setTimeout(() => { if (!_seekConfirmed && jw) try { jw.seek(last.time); } catch(e) {} }, 1500);
      }
    }
  };
  document.getElementById('cbClose').onclick = () => banner.classList.remove('show');
  banner.classList.add('show');
  setTimeout(() => banner.classList.remove('show'), 8000);
}

function goToLastWatched() {
  closeProfilePopover();
  const last = getLastWatched(); if (!last) return;
  const curKey  = 'S' + CUR.season + 'E' + CUR.ep;
  const lastKey = 'S' + last.season + 'E' + last.ep;
  if (curKey !== lastKey) { window.location.href = last.href; }
  else if (last.time > 0) {
    _pendingSeek = last.time; _seekConfirmed = false;
    if (jw) {
      try { jw.seek(last.time); } catch(e) {}
      setTimeout(() => { if (!_seekConfirmed && jw) try { jw.seek(last.time); } catch(e) {} }, 1500);
    }
  }
}

async function clearHistory() {
  closeProfilePopover();
  if (!confirm('Clear your entire watch history?\nThis cannot be undone.')) return;
  const u = getUsername();
  _history = {};
  if (u) await apiPost({ action:'clear_history', username: u });
  refreshProfileUI();
  renderSeasonList(document.getElementById('epList'), activeSeason);
  renderSeasonList(document.getElementById('mobileEpList'), activeSeason);
}

/* ── Profile popover ─────────────────────────────── */
let popoverOpen = false;
function toggleProfilePopover() {
  popoverOpen = !popoverOpen;
  const pop = document.getElementById('profilePopover');
  if (!pop) return;
  pop.classList.toggle('open', popoverOpen);
  if (popoverOpen) refreshProfileUI();
}
function closeProfilePopover() {
  popoverOpen = false;
  const pop = document.getElementById('profilePopover');
  if (pop) pop.classList.remove('open');
}
document.addEventListener('click', e => {
  if (!document.getElementById('profilePopover')?.contains(e.target) &&
      !document.getElementById('profileBtn')?.contains(e.target)) closeProfilePopover();
});

/* ── JW progress tracking ────────────────────────── */
let _pendingSeek   = 0;
let _seekConfirmed = false;

function hookProgressTracking() {
  if (!jw) return;

  jw.on('time', data => {
    const pos = data.position || 0;
    const dur = data.duration || 0;
    if (dur <= 0 || !getUsername()) return;
    saveEpisode(CUR.season, CUR.ep, (pos / dur) * 100, pos, dur);
    if (_pendingSeek > 0 && !_seekConfirmed && pos > 2) {
      if (Math.abs(pos - _pendingSeek) < 10) { _seekConfirmed = true; _pendingSeek = 0; }
    }
  });

  jw.on('complete', () => {
    if (!getUsername()) return;
    _pendingSeek = 0; _seekConfirmed = true;
    const u   = getUsername();
    const key = 'S' + CUR.season + 'E' + CUR.ep;
    _history[key] = Object.assign(_history[key] || {}, { pct: 100, time: 0, ts: Date.now() / 1000 });
    _lastSaveData = { u, season: CUR.season, ep: CUR.ep, pct: 100, time: 0, dur: 0 };
    clearTimeout(_saveTimer); _flushSave(); refreshEpRowHistory();
  });

  jw.on('bufferFull', () => {
    if (_pendingSeek > 0 && !_seekConfirmed) try { jw.seek(_pendingSeek); } catch(e) {}
  });

  let _firstPlay = true;
  jw.on('play', () => {
    if (_firstPlay && _pendingSeek > 0 && !_seekConfirmed) {
      _firstPlay = false;
      setTimeout(() => { if (!_seekConfirmed) try { jw.seek(_pendingSeek); } catch(e) {} }, 800);
    } else { _firstPlay = false; }
  });
}

function refreshEpRowHistory() {
  document.querySelectorAll('.ep-row').forEach(row => {
    applyHistoryToRow(row, parseInt(row.dataset.season), parseInt(row.dataset.ep));
  });
}

function applyHistoryToRow(row, s, e) {
  if (!s || !e) return;
  const data = _history['S' + s + 'E' + e];
  const pct  = data ? (data.pct || 0) : 0;
  const done    = pct >= 98 || !!(data && data.completed);
  const started = !done && pct >= 2;
  row.classList.toggle('ep-finished', done);
  row.classList.toggle('ep-started',  started);
  const pf = row.querySelector('.ep-progress-fill');
  if (pf) pf.style.width = started ? pct + '%' : '0%';
}

const _origBuildEpRow = buildEpRow;
buildEpRow = function(ep, sNum) {
  const a = _origBuildEpRow(ep, sNum);
  a.dataset.season = sNum;
  a.dataset.ep     = ep.ep;
  const info = a.querySelector('.ep-info');
  if (info) {
    const thumbWrap = a.querySelector('.ep-thumb-wrap');
    if (thumbWrap) {
      const tick = document.createElement('div');
      tick.className = 'ep-tick';
      tick.innerHTML = '<svg viewBox="0 0 12 12"><polyline points="2,6 5,9 10,3"/></svg>';
      tick.style.cssText = 'position:absolute;top:4px;right:4px;z-index:2;';
      thumbWrap.style.position = 'relative';
      thumbWrap.appendChild(tick);
    }
    const bar = document.createElement('div');
    bar.className = 'ep-progress-bar';
    bar.innerHTML = '<div class="ep-progress-fill" style="width:0%"></div>';
    info.appendChild(bar);
    applyHistoryToRow(a, sNum, ep.ep);
  }
  return a;
};

/* ── INIT ────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  renderSeasonTabs(document.getElementById('sbSeasonTabs'),     document.getElementById('epList'));
  renderSeasonTabs(document.getElementById('mobileSeasonTabs'), document.getElementById('mobileEpList'));

  initPlayer(CUR.url);

  // Disable devtools
  DisableDevtool({
    disableMenu:   true,
    clearLog:      true,
    disableSelect: true,
    disableCopy:   true,
    disableCut:    true,
    disablePaste:  true,
    interval:      200,
    disableMobile: true,
    ondevtoolopen: () => { window.location.href = 'about:blank'; },
  });

  const storedName = getUsername();
  if (!storedName) {
    setTimeout(openUsernamePrompt, 900);
  } else {
    refreshProfileUI();
    const params    = new URLSearchParams(window.location.search);
    const userChose = params.has('s') && params.has('e') && !params.has('resume');
    loadHistoryFromServer().then(() => {
      if (userChose) {
        const key = 'S' + CUR.season + 'E' + CUR.ep;
        const ep  = _history[key];
        if (ep && ep.time > 10 && ep.pct >= 2 && ep.pct < 98) { _pendingSeek = ep.time; _seekConfirmed = false; }
        return;
      }
      const action = resolveNextAction();
      if (action.redirect) {
        const dest = action.href + (action.href.includes('?') ? '&' : '?') + 'resume=1';
        window.location.replace(dest);
      } else if (action.seek && action.time > 0) {
        _pendingSeek = action.time; _seekConfirmed = false;
      }
    });
  }

  document.getElementById('unameConfirm').addEventListener('click', confirmUsername);
  document.getElementById('unameSkip').addEventListener('click', () => {
    closeUsernamePrompt();
    document.getElementById('continueBanner').classList.remove('show');
  });
  document.getElementById('unameInput').addEventListener('keydown', e => { if (e.key === 'Enter') confirmUsername(); });

  const _hookInterval = setInterval(() => { if (jw) { hookProgressTracking(); clearInterval(_hookInterval); } }, 300);
});
</script>

<!-- PROFILE POPOVER -->
<div id="profilePopover">
  <div class="pop-head">
    <div class="pop-av" id="popAv">?</div>
    <div>
      <div class="pop-name" id="popName">Guest</div>
      <div class="pop-sub"  id="popSub">No history yet</div>
    </div>
  </div>
  <div class="pop-divider"></div>
  <button class="pop-btn" onclick="goToLastWatched()">
    <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
    Continue Watching
  </button>
  <button class="pop-btn" onclick="openChangeUsername()">
    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    Change Username
  </button>
  <div class="pop-divider"></div>
  <button class="pop-btn danger" onclick="clearHistory()">
    <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
    Clear Watch History
  </button>
</div>
</body>
</html>
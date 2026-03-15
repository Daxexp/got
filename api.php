<?php
/**
 * api.php — reserved for future use / optional enhanced security
 *
 * Video URLs are now served directly from index.php via PHP (server-side).
 * The page source shows proxy.php?f=S01E01 keys only — never the origin server.
 * The actual origin URLs (01.pahan22feb.online/...) are only in proxy.php
 * which is pure PHP and never sent to the browser.
 *
 * This file is kept for rate-limiting or future token-gating if needed.
 */
http_response_code(404);
header('Content-Type: text/plain');
exit('Not found.');
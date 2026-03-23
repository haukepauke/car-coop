#!/usr/bin/env php
<?php
/**
 * CalDAV connection debugger
 *
 * Usage:
 *   php bin/caldav-test.php <email> <password> [base-url]
 *
 * Example:
 *   php bin/caldav-test.php user@example.com secret http://localhost:8080
 */

$email    = $argv[1] ?? null;
$password = $argv[2] ?? null;
$baseUrl  = rtrim($argv[3] ?? 'http://localhost:8080', '/');

if (!$email || !$password) {
    echo "Usage: php bin/caldav-test.php <email> <password> [base-url]\n";
    exit(1);
}

// ── helpers ──────────────────────────────────────────────────────────────────

function h(string $title): void
{
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "  $title\n";
    echo str_repeat('─', 60) . "\n";
}

function request(
    string $method,
    string $url,
    array  $headers = [],
    ?string $body = null,
    bool $followRedirects = false,
    ?string $user = null,
    ?string $pass = null,
): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER,         true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
    curl_setopt($ch, CURLOPT_VERBOSE,        false);

    if ($user !== null) {
        curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    }

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $defaultHeaders = ['Connection: close'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $headers));

    $raw      = curl_exec($ch);
    $info     = curl_getinfo($ch);
    $error    = curl_error($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr($raw, 0, $headerSize);
    $rawBody    = substr($raw, $headerSize);

    return [
        'status'   => $info['http_code'],
        'headers'  => $rawHeaders,
        'body'     => $rawBody,
        'url'      => $info['url'],
        'error'    => $error,
        'redirect' => $info['redirect_url'] ?? '',
    ];
}

function show(array $r, bool $showBody = true): void
{
    $status = $r['status'];
    $icon   = match(true) {
        $status >= 200 && $status < 300 => '✓',
        $status >= 300 && $status < 400 => '→',
        $status === 401                 => '🔑',
        default                         => '✗',
    };

    echo "  $icon  HTTP $status";
    if ($r['redirect']) {
        echo "  →  " . $r['redirect'];
    }
    echo "\n";

    if ($r['error']) {
        echo "  cURL error: " . $r['error'] . "\n";
        return;
    }

    // Print interesting response headers
    $interestingHeaders = [
        'www-authenticate', 'location', 'content-type',
        'dav', 'allow', 'x-sabre-version',
    ];
    foreach (explode("\n", $r['headers']) as $line) {
        $line = trim($line);
        if (!$line || str_starts_with($line, 'HTTP/')) continue;
        [$name] = explode(':', $line, 2) + ['', ''];
        if (in_array(strtolower(trim($name)), $interestingHeaders)) {
            echo "  < $line\n";
        }
    }

    if ($showBody && trim($r['body']) !== '') {
        $body = trim($r['body']);
        if (strlen($body) > 800) {
            $body = substr($body, 0, 800) . "\n  … (truncated)";
        }
        echo "\n";
        foreach (explode("\n", $body) as $line) {
            echo "    $line\n";
        }
    }
}

// ── 1. Service discovery ──────────────────────────────────────────────────────

h("1. .well-known/caldav redirect (no auth)");
$url = "$baseUrl/.well-known/caldav";
echo "  GET $url\n";
$r = request('GET', $url, followRedirects: false);
show($r, showBody: false);

h("2. .well-known/caldav — follow redirect");
$r = request('GET', $url, followRedirects: true);
show($r, showBody: false);

// ── 2. OPTIONS ───────────────────────────────────────────────────────────────

h("3. OPTIONS /caldav/ (no auth) — check DAV capabilities");
$url = "$baseUrl/caldav/";
echo "  OPTIONS $url\n";
$r = request('OPTIONS', $url);
show($r, showBody: false);

// ── 3. PROPFIND without auth — expect 401 ────────────────────────────────────

h("4. PROPFIND /caldav/ without credentials — expect 401");
$url = "$baseUrl/caldav/";
echo "  PROPFIND $url\n";
$r = request('PROPFIND', $url, ['Depth: 0', 'Content-Type: application/xml']);
show($r);

// ── 4. PROPFIND with wrong credentials — expect 401 ──────────────────────────

h("5. PROPFIND /caldav/ with wrong password — expect 401");
$url = "$baseUrl/caldav/";
echo "  PROPFIND $url  (user: $email, pass: wrong)\n";
$r = request('PROPFIND', $url, ['Depth: 0', 'Content-Type: application/xml'],
    body: null, followRedirects: false, user: $email, pass: 'wrong_password');
show($r);

// ── 5. PROPFIND with correct credentials ─────────────────────────────────────

h("6. PROPFIND /caldav/ with correct credentials — expect 207");
$url = "$baseUrl/caldav/";
echo "  PROPFIND $url  (user: $email)\n";
$propfindBody = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:">
  <d:prop>
    <d:displayname/>
    <d:resourcetype/>
  </d:prop>
</d:propfind>
XML;
$r = request('PROPFIND', $url,
    ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'],
    body: $propfindBody, user: $email, pass: $password);
show($r);

// ── 6. PROPFIND on user's principal ──────────────────────────────────────────

$encodedEmail = rawurlencode($email);
h("7. PROPFIND /caldav/principals/$encodedEmail — user principal");
$url = "$baseUrl/caldav/principals/$encodedEmail";
echo "  PROPFIND $url\n";
$r = request('PROPFIND', $url,
    ['Depth: 0', 'Content-Type: application/xml; charset=utf-8'],
    body: $propfindBody, user: $email, pass: $password);
show($r);

// ── 7. PROPFIND on calendars ──────────────────────────────────────────────────

h("8. PROPFIND /caldav/calendars/$encodedEmail — calendar list");
$url = "$baseUrl/caldav/calendars/$encodedEmail";
echo "  PROPFIND $url\n";
$r = request('PROPFIND', $url,
    ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'],
    body: $propfindBody, user: $email, pass: $password);
show($r);

// ── 9. Discover calendar-home-set (as DAVx5 does) ────────────────────────────

h("9. PROPFIND /caldav/principals/$encodedEmail — calendar-home-set discovery");
$url = "$baseUrl/caldav/principals/$encodedEmail";
echo "  PROPFIND $url\n";
$homeSetBody = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <d:displayname/>
    <c:calendar-home-set/>
    <d:current-user-principal/>
  </d:prop>
</d:propfind>
XML;
$r = request('PROPFIND', $url,
    ['Depth: 0', 'Content-Type: application/xml; charset=utf-8'],
    body: $homeSetBody, user: $email, pass: $password);
show($r);

// Extract first car calendar URI from step 8 response to use in next steps
preg_match('#/caldav/calendars/[^/]+/(car-\d+)/#', $r['body'], $calMatch);
$calSlug = $calMatch[1] ?? null;

// Re-fetch calendar list to find a calendar URI
$calListUrl  = "$baseUrl/caldav/calendars/$encodedEmail";
$calListResp = request('PROPFIND', $calListUrl,
    ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'],
    body: $propfindBody, user: $email, pass: $password);
preg_match('#<d:href>(/caldav/calendars/[^/]+/car-\d+/)</d:href>#', $calListResp['body'], $m);
$calPath = $m[1] ?? null;

// ── 10. sync-collection REPORT (what DAVx5 uses for incremental sync) ────────

if ($calPath) {
    h("10. REPORT sync-collection on $calPath");
    $url = "$baseUrl$calPath";
    echo "  REPORT $url\n";
    $syncReport = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<d:sync-collection xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:sync-token/>
  <d:sync-level>1</d:sync-level>
  <d:prop>
    <d:getetag/>
    <c:calendar-data/>
  </d:prop>
</d:sync-collection>
XML;
    $r = request('REPORT', $url,
        ['Content-Type: application/xml; charset=utf-8'],
        body: $syncReport, user: $email, pass: $password);
    show($r);

    // ── 11. calendar-query REPORT (fallback used by some clients) ────────────

    h("11. REPORT calendar-query on $calPath");
    echo "  REPORT $url\n";
    $queryReport = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:prop>
    <d:getetag/>
    <c:calendar-data/>
  </d:prop>
  <c:filter>
    <c:comp-filter name="VCALENDAR"/>
  </c:filter>
</c:calendar-query>
XML;
    $r = request('REPORT', $url,
        ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'],
        body: $queryReport, user: $email, pass: $password);
    show($r);

    // ── 12. GET first .ics object if any were listed ──────────────────────────

    preg_match('#<d:href>(/caldav/calendars/[^"<>]+\.ics)</d:href>#', $r['body'], $icsMatch);
    if ($icsMatch) {
        h("12. GET first calendar object: " . $icsMatch[1]);
        $getUrl = "$baseUrl" . $icsMatch[1];
        echo "  GET $getUrl\n";
        $r = request('GET', $getUrl, [], user: $email, pass: $password);
        show($r);
    } else {
        h("12. GET .ics — skipped (no objects found in calendar)");
        echo "  (No .ics objects returned in calendar-query)\n";
    }
    // ── 13. PUT — create a new calendar object ────────────────────────────────

    $testUid  = 'test-caldav-script-' . time();
    $testUri  = "$baseUrl{$calPath}{$testUid}.ics";
    $today    = date('Ymd');
    $tomorrow = date('Ymd', strtotime('+1 day'));
    $dtstamp  = gmdate('Ymd\THis\Z');

    h("13. PUT new calendar object — create booking");
    echo "  PUT $testUri\n";
    $icsBody = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//caldav-test.php//EN
BEGIN:VEVENT
UID:{$testUid}@car-coop
DTSTAMP:{$dtstamp}
DTSTART;VALUE=DATE:{$today}
DTEND;VALUE=DATE:{$tomorrow}
SUMMARY:caldav-test.php test entry
END:VEVENT
END:VCALENDAR
ICS;
    $r = request('PUT', $testUri,
        ['Content-Type: text/calendar; charset=utf-8'],
        body: $icsBody, user: $email, pass: $password);
    show($r, showBody: false);

    // ── 14. Re-sync to find canonical URI assigned by server ──────────────────
    // The server stores the booking as booking-{id}.ics, not the client URI.
    // DAVx5 handles this by re-syncing after creation; we do the same here.

    h("14. REPORT sync-collection — find canonical URI of the created booking");
    $syncR = request('REPORT', "$baseUrl$calPath",
        ['Content-Type: application/xml; charset=utf-8'],
        body: $syncReport, user: $email, pass: $password);
    show($syncR, showBody: false);

    preg_match_all('#<d:href>(/caldav/calendars/[^<>]+\.ics)</d:href>#', $syncR['body'], $allIcs);
    // Find the booking we just created by matching the SUMMARY in the calendar-data
    $canonicalPath = null;
    foreach ($allIcs[1] as $icsPath) {
        $getR = request('GET', "$baseUrl$icsPath", [], user: $email, pass: $password);
        if (str_contains($getR['body'], 'caldav-test.php test entry')) {
            $canonicalPath = $icsPath;
            break;
        }
    }

    // ── 15. GET — verify the created object is readable at canonical URI ──────

    if ($canonicalPath) {
        h("15. GET canonical object: $canonicalPath");
        echo "  GET $baseUrl$canonicalPath\n";
        $r = request('GET', "$baseUrl$canonicalPath", [], user: $email, pass: $password);
        show($r);

        // ── 16. DELETE — clean up the test object ─────────────────────────────

        h("16. DELETE the created object — clean up");
        echo "  DELETE $baseUrl$canonicalPath\n";
        $r = request('DELETE', "$baseUrl$canonicalPath", [], user: $email, pass: $password);
        show($r, showBody: false);
    } else {
        h("15–16. GET / DELETE — skipped (created booking not found in re-sync)");
        echo "  (Booking with SUMMARY 'caldav-test.php test entry' not found in sync response)\n";
    }

} else {
    h("10–15. REPORT / GET / PUT / DELETE — skipped (could not determine calendar path)");
    echo "  (Calendar path not found in step 8 response)\n";
}

echo "\n" . str_repeat('─', 60) . "\n";
echo "  Done.\n";
echo str_repeat('─', 60) . "\n\n";

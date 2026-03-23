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

echo "\n" . str_repeat('─', 60) . "\n";
echo "  Done.\n";
echo str_repeat('─', 60) . "\n\n";

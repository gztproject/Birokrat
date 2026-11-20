<?php

/**
 * Router for PHP's built-in server. Records every request, then returns a 1x1 PNG.
 * The PDF test points image and stylesheet URLs here.
 */
$log = getenv('PROBE_LOG');
if (is_string($log) && $log !== '') {
    $line = ($_SERVER['REQUEST_METHOD'] ?? '?').' '.($_SERVER['REQUEST_URI'] ?? '/')."\n";
    file_put_contents($log, $line, FILE_APPEND);
}

$body = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
header('Content-Type: image/png');
header('Content-Length: '.strlen($body));
echo $body;

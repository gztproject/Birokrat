<?php

/**
 * Accepts TCP connections and records them. Used to see https attempts,
 * which send a handshake even when this process does not speak TLS.
 */
$log = $argv[1] ?? '';
$port = (int) ($argv[2] ?? 0);
$socket = stream_socket_server('tcp://127.0.0.1:'.$port, $errno, $error);
if ($socket === false) {
    fwrite(STDERR, $error.PHP_EOL);
    exit(1);
}

while (is_resource($socket)) {
    $connection = @stream_socket_accept($socket, 5);
    if (!is_resource($connection)) {
        continue;
    }
    stream_set_timeout($connection, 2);
    $data = fread($connection, 32);
    file_put_contents($log, 'CONNECT '.strlen((string) $data).PHP_EOL, FILE_APPEND);
    fclose($connection);
}

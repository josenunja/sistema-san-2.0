<?php

// CLI-only authenticated streaming encryption; the key is stored separately from the encrypted backup.
if (PHP_SAPI !== 'cli' || count($argv) !== 5) {
    fwrite(STDERR, "Usage: php backup-crypt.php encrypt|decrypt input output keyfile\n");
    exit(1);
}
[$script, $mode, $input, $output, $keyfile] = $argv;
if (! in_array($mode, ['encrypt', 'decrypt'], true) || file_exists($output)) {
    fwrite(STDERR, "Invalid mode or output already exists.\n");
    exit(1);
}
if (! file_exists($keyfile) && $mode === 'encrypt') {
    file_put_contents($keyfile, sodium_crypto_secretstream_xchacha20poly1305_keygen(), LOCK_EX);
    chmod($keyfile, 0600);
}
$key = file_get_contents($keyfile);
if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
    fwrite(STDERR, "Invalid backup key.\n");
    exit(1);
}
$in = fopen($input, 'rb');
$out = fopen($output, 'xb');
if (! $in || ! $out) {
    fwrite(STDERR, "Cannot open backup streams.\n");
    exit(1);
}
try {
    if ($mode === 'encrypt') {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        fwrite($out, "SSBK1\n".$header);
        do {
            $chunk = fread($in, 1048576);
            if ($chunk === false) {
                throw new RuntimeException('Cannot read input.');
            }
            $final = feof($in);
            $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '', $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
            fwrite($out, pack('N', strlen($cipher)).$cipher);
        } while (! $final);
    } else {
        if (fread($in, 6) !== "SSBK1\n") {
            throw new RuntimeException('Invalid backup format.');
        }
        $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if (strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
            throw new RuntimeException('Truncated header.');
        }
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        do {
            $length = fread($in, 4);
            if ($length === false || strlen($length) !== 4) {
                throw new RuntimeException('Truncated backup.');
            }
            $size = unpack('Nsize', $length)['size'];
            if ($size < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $size > 1048576 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                throw new RuntimeException('Invalid encrypted chunk.');
            }
            $cipher = '';
            while (strlen($cipher) < $size && ! feof($in)) {
                $part = fread($in, $size - strlen($cipher));
                if ($part === false) {
                    throw new RuntimeException('Read failed.');
                } $cipher .= $part;
            }
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
            if ($result === false) {
                throw new RuntimeException('Backup authentication failed.');
            }
            [$plain, $tag] = $result;
            fwrite($out, $plain);
        } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        if (! feof($in) && fread($in, 1) !== '') {
            throw new RuntimeException('Unexpected trailing data.');
        }
    }
    fclose($in);
    fclose($out);
    sodium_memzero($key);
    echo json_encode(['status' => 'ok', 'mode' => $mode], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    fclose($in);
    fclose($out);
    unlink($output);
    sodium_memzero($key);
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}

<?php

namespace App\Services\Files;

use Throwable;

/**
 * Streams a file to a ClamAV (clamd) daemon over TCP using the INSTREAM
 * protocol. No PHP extension is required; only a reachable clamd service.
 */
class ClamAvFileScanner implements FileScanner
{
    /**
     * @param  array{host?: string, port?: int, timeout?: int, chunk_size?: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function scan(string $absolutePath, string $originalName): ScanResult
    {
        $host = (string) ($this->config['host'] ?? 'clamav');
        $port = (int) ($this->config['port'] ?? 3310);
        $timeout = (int) ($this->config['timeout'] ?? 10);
        $chunkSize = (int) ($this->config['chunk_size'] ?? 8192);

        if (! is_file($absolutePath)) {
            return ScanResult::error('Uploaded file is not readable.');
        }

        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);

        if ($socket === false) {
            return ScanResult::error("Unable to reach the antivirus scanner: {$errstr} ({$errno}).");
        }

        try {
            stream_set_timeout($socket, $timeout);
            fwrite($socket, "zINSTREAM\0");

            $handle = fopen($absolutePath, 'rb');

            if ($handle === false) {
                return ScanResult::error('Uploaded file is not readable.');
            }

            while (! feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }

            fclose($handle);

            fwrite($socket, pack('N', 0));

            $response = stream_get_contents($socket);
        } catch (Throwable $exception) {
            return ScanResult::error('Antivirus scan failed: '.$exception->getMessage());
        } finally {
            fclose($socket);
        }

        if ($response === false || $response === '') {
            return ScanResult::error('Antivirus scanner returned no response.');
        }

        $response = trim($response);

        if (str_contains($response, 'FOUND')) {
            $signature = trim(str_replace(['FOUND', ':'], '', $response));

            return ScanResult::infected($signature === '' ? $response : $signature);
        }

        if (str_contains($response, 'OK')) {
            return ScanResult::clean();
        }

        return ScanResult::error('Antivirus scanner error: '.$response);
    }
}

<?php

namespace App\Services\Files;

/**
 * Inspects a video file's actual container and codec signatures.
 *
 * The upload's client-supplied MIME type and filename are not trusted. MP4/MOV
 * files are walked as ISO base media boxes until the sample descriptions
 * (stsd) expose the real codec fourcc. WebM files are checked for an EBML
 * header with the "webm" document type and a supported codec ID.
 */
class VideoInspector
{
    private const MAX_EBML_SCAN_BYTES = 8 * 1024 * 1024;

    /**
     * @return array{container: string, codecs: array<int, string>}|null
     *   Container is "mp4", "mov" or "webm"; null when the file is not a
     *   recognised MP4/MOV/WebM video.
     */
    public function inspect(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $size = filesize($path);

        if ($size === false || $size < 12) {
            return null;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $header = fread($handle, 12);

            if ($header === false || strlen($header) < 12) {
                return null;
            }

            if (substr($header, 0, 4) === "\x1A\x45\xDF\xA3") {
                return $this->inspectWebm($handle);
            }

            if (substr($header, 4, 4) === 'ftyp') {
                return $this->inspectIsoBmff($handle, $size, substr($header, 8, 4));
            }

            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{container: string, codecs: array<int, string>}|null
     */
    private function inspectIsoBmff($handle, int $fileSize, string $majorBrand): ?array
    {
        $codecs = [];

        $this->walkBoxes($handle, 0, $fileSize, $codecs, 0);

        if ($codecs === []) {
            return null;
        }

        $brand = strtolower(rtrim($majorBrand, "\0 "));

        return [
            'container' => $brand === 'qt' ? 'mov' : 'mp4',
            'codecs' => array_values(array_unique($codecs)),
        ];
    }

    /**
     * @param  resource  $handle
     * @param  array<int, string>  $codecs
     */
    private function walkBoxes($handle, int $start, int $end, array &$codecs, int $depth): void
    {
        if ($depth > 12) {
            return;
        }

        $position = $start;

        while ($position + 8 <= $end) {
            fseek($handle, $position);

            $header = fread($handle, 8);

            if ($header === false || strlen($header) < 8) {
                return;
            }

            $size = unpack('N', substr($header, 0, 4))[1] ?? 0;
            $type = substr($header, 4, 4);
            $headerSize = 8;

            if ($size === 1) {
                $extended = fread($handle, 8);

                if ($extended === false || strlen($extended) < 8) {
                    return;
                }

                $size = unpack('J', $extended)[1] ?? 0;
                $headerSize = 16;
            } elseif ($size === 0) {
                $size = $end - $position;
            }

            if ($size < $headerSize) {
                return;
            }

            $boxEnd = min($position + $size, $end);

            if ($boxEnd <= $position) {
                return;
            }

            if ($type === 'stsd') {
                $this->readSampleDescriptions($handle, $position + $headerSize, $boxEnd, $codecs);
            } elseif (in_array($type, ['moov', 'trak', 'mdia', 'minf', 'stbl'], true)) {
                $this->walkBoxes($handle, $position + $headerSize, $boxEnd, $codecs, $depth + 1);
            }

            $position = $boxEnd;
        }
    }

    /**
     * Read the sample-entry fourcc values from an stsd full box.
     *
     * @param  resource  $handle
     * @param  array<int, string>  $codecs
     */
    private function readSampleDescriptions($handle, int $start, int $end, array &$codecs): void
    {
        // stsd payload starts with version/flags (4 bytes) and entry_count (4).
        $position = $start + 8;

        while ($position + 8 <= $end) {
            fseek($handle, $position);

            $header = fread($handle, 8);

            if ($header === false || strlen($header) < 8) {
                return;
            }

            $size = unpack('N', substr($header, 0, 4))[1] ?? 0;
            $format = substr($header, 4, 4);

            if ($size < 8) {
                return;
            }

            $codecs[] = $format;
            $position += $size;
        }
    }

    /**
     * @return array{container: string, codecs: array<int, string>}|null
     */
    private function inspectWebm($handle): ?array
    {
        rewind($handle);

        $data = '';

        while (! feof($handle) && strlen($data) < self::MAX_EBML_SCAN_BYTES) {
            $chunk = fread($handle, min(1024 * 1024, self::MAX_EBML_SCAN_BYTES - strlen($data)));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $data .= $chunk;
        }

        // EBML DocType lives in the first few bytes. Matroska files use the
        // same magic but are not accepted by the WebM player contract.
        if (! str_contains(substr($data, 0, 64), 'webm')) {
            return null;
        }

        $codecs = [];

        foreach (['V_VP8', 'V_VP9', 'V_AV1'] as $codec) {
            if (str_contains($data, $codec)) {
                $codecs[] = $codec;
            }
        }

        if ($codecs === []) {
            return null;
        }

        return [
            'container' => 'webm',
            'codecs' => $codecs,
        ];
    }
}

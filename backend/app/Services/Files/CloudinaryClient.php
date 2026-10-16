<?php

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Cloudinary REST client. Uploads are signed with HMAC-SHA1 as
 * required by Cloudinary; the API secret is required and is never logged.
 */
class CloudinaryClient
{
    /**
     * @param  array{cloud_name?: ?string, api_key?: ?string, api_secret?: ?string, folder?: ?string, secure?: bool}  $config
     */
    public function __construct(private readonly array $config) {}

    public function isConfigured(): bool
    {
        return ! empty($this->config['cloud_name'])
            && ! empty($this->config['api_key'])
            && ! empty($this->config['api_secret']);
    }

    /**
     * Upload a file and return its secure delivery URL.
     */
    public function upload(UploadedFile $file, string $folder, string $publicId): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudinary is not fully configured (cloud name, API key and API secret are required).');
        }

        $timestamp = time();
        $resource = $this->resourceType($file);

        $params = array_filter([
            'folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ], static fn ($value) => $value !== null && $value !== '');

        $params['signature'] = $this->signature($params, (string) $this->config['api_secret']);
        $params['api_key'] = (string) $this->config['api_key'];

        $response = Http::asMultipart()
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post($this->endpoint($resource), $params);

        if (! $response->successful()) {
            throw new RuntimeException('Cloudinary upload failed: '.$response->body());
        }

        return (string) $response->json('secure_url');
    }

    public function destroy(string $publicId): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudinary is not fully configured.');
        }

        $timestamp = time();

        $params = array_filter([
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ], static fn ($value) => $value !== null && $value !== '');

        $params['signature'] = $this->signature($params, (string) $this->config['api_secret']);
        $params['api_key'] = (string) $this->config['api_key'];

        Http::asForm()->post(
            'https://api.cloudinary.com/v1_1/'.$this->config['cloud_name'].'/image/destroy',
            $params,
        );
    }

    private function endpoint(string $resource): string
    {
        return 'https://api.cloudinary.com/v1_1/'.$this->config['cloud_name'].'/'.$resource.'/upload';
    }

    private function resourceType(UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();

        return str_starts_with($mime, 'image/') ? 'image' : 'raw';
    }

    /**
     * @param  array<string, scalar>  $params
     */
    private function signature(array $params, string $secret): string
    {
        ksort($params);

        $pairs = [];

        foreach ($params as $key => $value) {
            if ($key === 'signature' || $key === 'api_key' || $value === null || $value === '') {
                continue;
            }
            $pairs[] = $key.'='.$value;
        }

        return sha1(implode('&', $pairs).$secret);
    }
}

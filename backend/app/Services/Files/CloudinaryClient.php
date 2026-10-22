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
     * Upload a file and return its delivery URL plus the identifiers needed for
     * a later permanent deletion.
     */
    public function upload(UploadedFile $file, string $folder, string $publicId): StoredFile
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudinary is not fully configured (cloud name, API key and API secret are required).');
        }

        $timestamp = time();
        $resourceType = $this->resourceType($file);

        $params = array_filter([
            'folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ], static fn ($value) => $value !== null && $value !== '');

        $params['signature'] = $this->signature($params, (string) $this->config['api_secret']);
        $params['api_key'] = (string) $this->config['api_key'];

        $response = Http::asMultipart()
            ->connectTimeout(5)
            ->timeout(30)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post($this->endpoint($resourceType, 'upload'), $params);

        if (! $response->successful()) {
            throw new RuntimeException('Cloudinary upload failed: '.$response->body());
        }

        $secureUrl = (string) $response->json('secure_url');
        $storedPublicId = (string) ($response->json('public_id') ?: $publicId);
        $storedResourceType = (string) ($response->json('resource_type') ?: $resourceType);

        if ($secureUrl === '' || $storedPublicId === '') {
            throw new RuntimeException('Cloudinary did not return the uploaded asset identifiers.');
        }

        return new StoredFile($secureUrl, $storedPublicId, $storedResourceType);
    }

    /**
     * Permanently delete a Cloudinary asset. "not found" is treated as success
     * so a retry after a prior partial failure remains safe.
     */
    public function destroy(string $publicId, string $resourceType = 'image'): bool
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Cloudinary is not fully configured.');
        }

        if ($publicId === '' || ! in_array($resourceType, ['image', 'raw', 'video'], true)) {
            throw new RuntimeException('The Cloudinary asset identifier is invalid.');
        }

        $timestamp = time();

        $params = array_filter([
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ], static fn ($value) => $value !== null && $value !== '');

        $params['signature'] = $this->signature($params, (string) $this->config['api_secret']);
        $params['api_key'] = (string) $this->config['api_key'];

        $response = Http::asForm()
            ->connectTimeout(5)
            ->timeout(15)
            ->post(
                $this->endpoint($resourceType, 'destroy'),
                $params,
            );

        if (! $response->successful()) {
            throw new RuntimeException('Cloudinary deletion failed with status '.$response->status().'.');
        }

        $result = (string) $response->json('result');

        if ($result === 'not found') {
            return false;
        }

        if ($result !== 'ok') {
            throw new RuntimeException('Cloudinary did not confirm the asset deletion.');
        }

        return true;
    }

    private function endpoint(string $resourceType, string $action): string
    {
        return 'https://api.cloudinary.com/v1_1/'.$this->config['cloud_name'].'/'.$resourceType.'/'.$action;
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

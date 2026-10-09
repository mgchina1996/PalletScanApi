<?php

namespace App\Services;

use App\Exceptions\InvalidImageTokenException;
use App\Exceptions\OssConfigurationException;
use App\Exceptions\OssStorageException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;
use OSS\OssClient;
use Throwable;

class OssImageStorage
{
    public function __construct(
        private readonly string $accessKeyId,
        private readonly string $accessKeySecret,
        private readonly string $endpoint,
        private readonly string $bucket,
    ) {}

    public function uploadTemporary(string $imageBytes, string $contentType): string
    {
        $extension = match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $object = sprintf(
            'tmp/scans/%s/%s.%s',
            now('UTC')->format('Y/m/d'),
            Str::uuid()->toString(),
            $extension,
        );

        try {
            $this->client()->putObject($this->bucket, $object, $imageBytes, [
                OssClient::OSS_CONTENT_TYPE => $contentType,
            ]);
        } catch (OssConfigurationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OssStorageException('Unable to upload the image to OSS.', previous: $exception);
        }

        try {
            $payload = json_encode([
                'object' => $object,
                'expires_at' => now('UTC')->addDay()->timestamp,
            ], JSON_THROW_ON_ERROR);

            return Crypt::encryptString($payload);
        } catch (Throwable $exception) {
            $this->deleteQuietly($object);

            throw new OssStorageException('Unable to create the image token.', previous: $exception);
        }
    }

    public function promote(string $token, int $entryId): string
    {
        $temporaryObject = $this->objectFromToken($token);
        $entryObject = sprintf(
            'entries/%s/%d/%s',
            now('UTC')->format('Y/m/d'),
            $entryId,
            basename($temporaryObject),
        );

        try {
            $client = $this->client();
            $client->copyObject($this->bucket, $temporaryObject, $this->bucket, $entryObject);

            try {
                $client->deleteObject($this->bucket, $temporaryObject);
            } catch (Throwable $exception) {
                report($exception);
            }
        } catch (OssConfigurationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OssStorageException('Unable to save the image in OSS.', previous: $exception);
        }

        return $entryObject;
    }

    public function deleteQuietly(?string $object): void
    {
        if ($object === null || $object === '') {
            return;
        }

        try {
            $this->client()->deleteObject($this->bucket, $object);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function objectFromToken(string $token): string
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            throw new InvalidImageTokenException('The uploaded image token is invalid.', previous: $exception);
        }

        $object = is_array($payload) ? ($payload['object'] ?? null) : null;
        $expiresAt = is_array($payload) ? ($payload['expires_at'] ?? null) : null;

        if (
            ! is_array($payload)
            || ! is_string($object)
            || ! str_starts_with($object, 'tmp/scans/')
            || ! in_array(pathinfo($object, PATHINFO_EXTENSION), ['jpg', 'png', 'webp'], true)
            || ! is_int($expiresAt)
            || $expiresAt < now('UTC')->timestamp
        ) {
            throw new InvalidImageTokenException('The uploaded image token is invalid or expired.');
        }

        return $object;
    }

    private function client(): OssClient
    {
        if (
            $this->accessKeyId === ''
            || $this->accessKeySecret === ''
            || $this->endpoint === ''
            || $this->bucket === ''
        ) {
            throw new OssConfigurationException('OSS image storage is not configured.');
        }

        return new OssClient($this->accessKeyId, $this->accessKeySecret, $this->endpoint);
    }
}

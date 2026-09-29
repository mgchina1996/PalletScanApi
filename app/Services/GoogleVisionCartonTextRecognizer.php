<?php

namespace App\Services;

use App\Contracts\CartonTextRecognizer;
use App\Exceptions\OcrConfigurationException;
use App\Exceptions\OcrRecognitionException;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;
use JsonException;
use Throwable;

class GoogleVisionCartonTextRecognizer implements CartonTextRecognizer
{
    public function __construct(private readonly string $credentialsPath) {}

    public function recognize(string $imageBytes): string
    {
        if ($this->credentialsPath === '' || ! is_file($this->credentialsPath) || ! is_readable($this->credentialsPath)) {
            throw new OcrConfigurationException('Google Vision credential file is missing or unreadable.');
        }

        $credentialsJson = file_get_contents($this->credentialsPath);

        if ($credentialsJson === false) {
            throw new OcrConfigurationException('Google Vision credential file could not be read.');
        }

        try {
            $credentialsConfig = json_decode($credentialsJson, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new OcrConfigurationException('Google Vision credential file contains invalid JSON.', previous: $exception);
        }

        if (
            ! is_array($credentialsConfig)
            || ($credentialsConfig['type'] ?? null) !== 'service_account'
            || ! is_string($credentialsConfig['client_email'] ?? null)
            || ! is_string($credentialsConfig['private_key'] ?? null)
        ) {
            throw new OcrConfigurationException('Google Vision credential file is not a valid service account key.');
        }

        $client = null;

        try {
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/cloud-platform'],
                $credentialsConfig,
            );
            $client = new ImageAnnotatorClient(['credentials' => $credentials]);
            $batchResponse = $client->batchAnnotateImages(BatchAnnotateImagesRequest::build([
                new AnnotateImageRequest([
                    'image' => new Image(['content' => $imageBytes]),
                    'features' => [new Feature(['type' => Type::TEXT_DETECTION])],
                ]),
            ]));
            $responses = $batchResponse->getResponses();
            $response = count($responses) > 0 ? $responses[0] : null;

            if ($response === null) {
                return '';
            }

            if ($response->hasError()) {
                throw new OcrRecognitionException($response->getError()->getMessage());
            }

            $annotations = $response->getTextAnnotations();

            return count($annotations) > 0
                ? trim($annotations[0]->getDescription())
                : '';
        } catch (OcrRecognitionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new OcrRecognitionException('Google Vision OCR request failed.', previous: $exception);
        } finally {
            $client?->close();
        }
    }
}

<?php

namespace App\Services;

use AlibabaCloud\Credentials\Credential;
use AlibabaCloud\Dara\Exception\DaraRespException;
use AlibabaCloud\Dara\File;
use AlibabaCloud\Dara\Models\RuntimeOptions;
use AlibabaCloud\SDK\Ocrapi\V20210707\Models\RecognizeAdvancedRequest;
use AlibabaCloud\SDK\Ocrapi\V20210707\Ocrapi;
use App\Contracts\OcrRecognizer;
use App\Exceptions\OcrRecognitionException;
use Darabonba\OpenApi\Models\Config;
use Illuminate\Http\UploadedFile;
use JsonException;

class AlibabaCloudOcrRecognizer implements OcrRecognizer
{
    public function __construct(
        private readonly string $accessKeyId,
        private readonly string $accessKeySecret,
        private readonly string $endpoint,
    ) {}

    /** @return array{code: string|null, message: string|null, request_id: string|null, result: mixed} */
    public function recognize(UploadedFile $image): array
    {
        try {
            $response = $this->client()->recognizeAdvancedWithOptions(
                new RecognizeAdvancedRequest([
                    'body' => File::createReadStream($image->getPathname()),
                    'outputCharInfo' => true,
                    'needRotate' => true,
                    'outputTable' => true,
                    'needSortPage' => true,
                    'outputFigure' => true,
                    'noStamp' => true,
                    'paragraph' => true,
                    'row' => true,
                ]),
                new RuntimeOptions([]),
            );

            $body = $response->body;

            return [
                'code' => $body?->code,
                'message' => $body?->message,
                'request_id' => $body?->requestId,
                'result' => $this->decodeResult($body?->data),
            ];
        } catch (DaraRespException|JsonException $exception) {
            throw new OcrRecognitionException('Alibaba Cloud OCR request failed.', previous: $exception);
        }
    }

    private function client(): Ocrapi
    {
        $credentials = new Credential(new Config([
            'type' => 'access_key',
            'accessKeyId' => $this->accessKeyId,
            'accessKeySecret' => $this->accessKeySecret,
        ]));

        $config = new Config([
            'credential' => $credentials,
            'endpoint' => $this->endpoint,
        ]);

        return new Ocrapi($config);
    }

    /** @throws JsonException */
    private function decodeResult(?string $data): mixed
    {
        if ($data === null || $data === '') {
            return null;
        }

        return json_decode($data, true, flags: JSON_THROW_ON_ERROR);
    }
}

<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\CartonLookup;
use App\Contracts\OcrRecognizer;
use App\Exceptions\OcrRecognitionException;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;

class ImageRecognitionControllerTest extends TestCase
{
    public function test_returns_recognition_result_for_a_valid_image(): void
    {
        $this->mock(OcrRecognizer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('recognize')
                ->once()
                ->withArgs(fn (UploadedFile $image): bool => $image->getClientOriginalName() === 'label.jpg')
                ->andReturn([
                    'code' => null,
                    'message' => null,
                    'request_id' => 'ocr-request-id',
                    'result' => ['content' => "Header\nEN8ZTQCWL\nnot-a-tpin\nCTN1LMRAYWQ\nEN8ZTQCWL\n8UVYSEBNL"],
                ]);
        });

        $response = $this->postJson(route('api.v1.ocr-recognitions.store'), [
            'type' => 'tpin',
            'image' => UploadedFile::fake()->image('label.jpg'),
        ]);

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'type' => 'tpin',
                    'values' => [
                        'EN8ZTQCWL',
                        '8UVYSEBNL',
                    ],
                    'request_id' => 'ocr-request-id',
                ],
            ]);
    }

    public function test_returns_only_carton_numbers_for_carton_type(): void
    {
        $this->mock(OcrRecognizer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('recognize')
                ->once()
                ->andReturn([
                    'code' => null,
                    'message' => null,
                    'request_id' => 'carton-request-id',
                    'result' => ['content' => "EN8ZTQCWL\nCTN1LMRAYWQ\n507200\n12345\n1234\nNoise\nCTN8ABC1234"],
                ]);
        });
        $this->mock(CartonLookup::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findByRecognizedValues')
                ->once()
                ->with(['CTN1LMRAYWQ', '507200', '12345', 'CTN8ABC1234'])
                ->andReturn([
                    [
                        'cartonID' => 507200,
                        'cartonNumber' => 'CTN1LMRAYWQ',
                        'products' => [
                            ['itemID' => 101, 'tpin' => 'EN8ZTQCWL'],
                        ],
                    ],
                ]);
        });

        $response = $this->postJson(route('api.v1.ocr-recognitions.store'), [
            'type' => 'carton',
            'image' => UploadedFile::fake()->image('cartons.jpg'),
        ]);

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'type' => 'carton',
                    'cartons' => [
                        [
                            'cartonID' => 507200,
                            'cartonNumber' => 'CTN1LMRAYWQ',
                            'products' => [
                                ['itemID' => 101, 'tpin' => 'EN8ZTQCWL'],
                            ],
                        ],
                    ],
                    'request_id' => 'carton-request-id',
                ],
            ]);
    }

    public function test_returns_422_when_image_is_missing(): void
    {
        $response = $this->postJson(route('api.v1.ocr-recognitions.store'), [
            'type' => 'tpin',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_returns_422_when_recognition_type_is_unsupported(): void
    {
        $response = $this->postJson(route('api.v1.ocr-recognitions.store'), [
            'type' => 'sku',
            'image' => UploadedFile::fake()->image('label.jpg'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_returns_422_when_upload_is_not_an_image(): void
    {
        $response = $this->postJson(route('api.v1.ocr-recognitions.store'), [
            'type' => 'tpin',
            'image' => UploadedFile::fake()->create('label.txt', 1, 'text/plain'),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_returns_422_when_image_exceeds_ten_megabytes(): void
    {
        $response = $this->postJson(route('api.v1.ocr-recognitions.store'), [
            'type' => 'tpin',
            'image' => UploadedFile::fake()->image('large.jpg')->size(10241),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_returns_502_when_recognition_service_fails(): void
    {
        $this->mock(OcrRecognizer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('recognize')
                ->once()
                ->andThrow(new OcrRecognitionException('Alibaba Cloud OCR request failed.'));
        });

        $response = $this->postJson(route('api.v1.ocr-recognitions.store'), [
            'type' => 'tpin',
            'image' => UploadedFile::fake()->image('label.jpg'),
        ]);

        $response->assertStatus(502)
            ->assertExactJson([
                'message' => 'Image recognition service is unavailable.',
            ]);
    }
}

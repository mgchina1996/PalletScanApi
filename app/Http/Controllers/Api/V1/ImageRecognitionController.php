<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\CartonLookup;
use App\Contracts\OcrRecognizer;
use App\Exceptions\OcrRecognitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecognizeImageRequest;
use App\Services\OcrCodeExtractor;
use Illuminate\Http\JsonResponse;

class ImageRecognitionController extends Controller
{
    public function __invoke(
        RecognizeImageRequest $request,
        OcrRecognizer $recognizer,
        OcrCodeExtractor $extractor,
        CartonLookup $cartonLookup,
    ): JsonResponse {
        try {
            $result = $recognizer->recognize($request->file('image'));
        } catch (OcrRecognitionException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Image recognition service is unavailable.',
            ], 502);
        }

        $type = $request->string('type')->toString();
        $values = $extractor->extract($type, $result);

        return response()->json([
            'data' => [
                'type' => $type,
                ...$type === 'carton'
                    ? ['cartons' => $cartonLookup->findByRecognizedValues($values)]
                    : ['values' => $values],
                'request_id' => $result['request_id'],
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\CartonTextRecognizer;
use App\Exceptions\OcrConfigurationException;
use App\Exceptions\OcrRecognitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecognizeCartonImageRequest;
use Illuminate\Http\JsonResponse;

class CartonOcrController extends Controller
{
    public function __invoke(
        RecognizeCartonImageRequest $request,
        CartonTextRecognizer $recognizer,
    ): JsonResponse {
        $path = $request->file('image')->getRealPath();
        $imageBytes = $path === false ? false : file_get_contents($path);

        if ($imageBytes === false) {
            return response()->json(['message' => 'Unable to read the uploaded image.'], 422);
        }

        try {
            $text = $recognizer->recognize($imageBytes);
        } catch (OcrConfigurationException $exception) {
            report($exception);

            return response()->json(['message' => 'OCR service is not configured.'], 503);
        } catch (OcrRecognitionException $exception) {
            report($exception);

            return response()->json(['message' => 'OCR service is temporarily unavailable.'], 502);
        }

        preg_match_all('/(?<![A-Z0-9])CTN[-\s]?[A-Z0-9]+(?![A-Z0-9])/i', $text, $matches);
        $candidates = array_values(array_unique(array_map(
            static fn (string $value): string => strtoupper((string) preg_replace('/\s+/', '', $value)),
            $matches[0],
        )));

        return response()->json([
            'data' => [
                'text' => $text,
                'carton_candidates' => $candidates,
                'needs_confirmation' => true,
            ],
        ]);
    }
}

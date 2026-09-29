<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\CartonTextRecognizer;
use App\Exceptions\OcrConfigurationException;
use App\Exceptions\OcrRecognitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecognizeCartonImageRequest;
use App\Services\PortalCartonAvailabilityLookup;
use Illuminate\Http\JsonResponse;

class CartonOcrController extends Controller
{
    public function __invoke(
        RecognizeCartonImageRequest $request,
        CartonTextRecognizer $recognizer,
        PortalCartonAvailabilityLookup $cartonLookup,
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

        $normalizedText = strtoupper($text);

        preg_match_all(
            '/(?<![A-Z0-9])(?:CTN[\s-]*)+([A-Z0-9]+)(?![A-Z0-9])/',
            $normalizedText,
            $cartonNumberMatches,
        );
        $cartonNumbers = array_values(array_unique(array_map(
            static fn (string $value): string => 'CTN'.$value,
            $cartonNumberMatches[1],
        )));

        preg_match_all('/(?<![A-Z0-9])[0-9]{5,6}(?![A-Z0-9])/', $normalizedText, $cartonIdMatches);
        $numericCartonNumbers = array_values(array_filter(array_map(
            static fn (string $value): string => substr($value, 3),
            $cartonNumbers,
        ), ctype_digit(...)));
        $cartonIds = array_values(array_unique(array_map(
            intval(...),
            array_values(array_diff($cartonIdMatches[0], $numericCartonNumbers)),
        )));
        $availableCartons = $cartonLookup->find($cartonIds, $cartonNumbers);

        return response()->json([
            'data' => [
                'cartons' => $availableCartons,
                'total' => count($availableCartons),
            ],
        ]);
    }
}

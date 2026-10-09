<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidImageTokenException;
use App\Exceptions\OssConfigurationException;
use App\Exceptions\OssStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductEntriesRequest;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use App\Services\OssImageStorage;
use App\Services\PortalCartonCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProductEntryController extends Controller
{
    public function store(
        StoreProductEntriesRequest $request,
        string $type,
        OssImageStorage $imageStorage,
        PortalCartonCreator $cartonCreator,
    ): JsonResponse {
        /** @var array{locationCode: string, tpin: string, quantity: int, imageToken?: string|null} $data */
        $data = $request->validated();
        $imagePath = null;

        try {
            $entry = DB::transaction(function () use ($data, $type, $imageStorage, $cartonCreator, &$imagePath): Entry {
                $entry = Entry::create([
                    'location_code' => $data['locationCode'],
                    'type' => $type,
                    'code' => $data['tpin'],
                    'quantity' => $data['quantity'],
                ]);

                if (! empty($data['imageToken'])) {
                    $imagePath = $imageStorage->promote($data['imageToken'], $entry->id);
                    $entry->update(['image_path' => $imagePath]);
                }

                if ($type === Entry::TYPE_TPIN) {
                    $carton = $cartonCreator->create();
                    $entry->carton_id = (int) $carton->CartonID;
                    $entry->save();
                    $entry->setAttribute('carton_number', (string) $carton->CartonNumber);
                }

                return $entry;
            });
        } catch (Throwable $exception) {
            $imageStorage->deleteQuietly($imagePath);

            return $this->imageFailureResponse($exception);
        }

        return (new EntryResource($entry))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function imageFailureResponse(Throwable $exception): JsonResponse
    {
        if ($exception instanceof InvalidImageTokenException) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['imageToken' => [$exception->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($exception instanceof OssConfigurationException) {
            report($exception);

            return response()->json(['message' => 'Image storage is not configured.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($exception instanceof OssStorageException) {
            report($exception);

            return response()->json(['message' => 'Unable to save the uploaded image.'], Response::HTTP_BAD_GATEWAY);
        }

        throw $exception;
    }
}

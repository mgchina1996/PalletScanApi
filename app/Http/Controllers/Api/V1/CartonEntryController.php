<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidImageTokenException;
use App\Exceptions\OssConfigurationException;
use App\Exceptions\OssStorageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FindPendingCartonEntryRequest;
use App\Http\Requests\StoreCartonEntriesRequest;
use App\Http\Resources\EntryDetailResource;
use App\Http\Resources\EntryResource;
use App\Models\Entry;
use App\Services\OssImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CartonEntryController extends Controller
{
    public function pending(FindPendingCartonEntryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $entry = Entry::query()
            ->where('location_code', $data['locationCode'])
            ->where('type', Entry::TYPE_CARTON)
            ->where('code', $data['cartonNumber'])
            ->where('stock_generated', false)
            ->latest('id')
            ->first();

        if ($entry === null) {
            return response()->json(['data' => null]);
        }

        return (new EntryDetailResource($entry->load('products')))->response();
    }

    public function store(StoreCartonEntriesRequest $request, OssImageStorage $imageStorage): JsonResponse
    {
        /** @var array{locationCode: string, cartonNumber: int|string, products: list<array{tpin: string, quantity: int}>, imageToken?: string|null, overwriteExisting?: bool} $data */
        $data = $request->validated();
        $imagePath = null;
        $replacedImagePath = null;

        try {
            $entry = DB::transaction(function () use ($data, $imageStorage, &$imagePath, &$replacedImagePath): Entry {
                $cartonNumber = strtoupper((string) $data['cartonNumber']);
                $entry = null;

                if ($data['overwriteExisting'] ?? true) {
                    $entry = Entry::query()
                        ->where('location_code', $data['locationCode'])
                        ->where('type', Entry::TYPE_CARTON)
                        ->where('code', $cartonNumber)
                        ->where('stock_generated', false)
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();
                }

                if ($entry === null) {
                    $entry = Entry::create([
                        'location_code' => $data['locationCode'],
                        'type' => Entry::TYPE_CARTON,
                        'code' => $cartonNumber,
                        'quantity' => null,
                    ]);
                } else {
                    $entry->products()->delete();
                    $entry->quantity = null;
                    $entry->error = null;
                    $entry->carton_id = null;
                    $entry->save();
                }

                $entry->products()->createMany($data['products']);

                if (! empty($data['imageToken'])) {
                    $replacedImagePath = $entry->image_path;
                    $imagePath = $imageStorage->promote($data['imageToken'], $entry->id);
                    $entry->update(['image_path' => $imagePath]);
                }

                return $entry->load('products');
            });
        } catch (Throwable $exception) {
            $imageStorage->deleteQuietly($imagePath);

            return $this->imageFailureResponse($exception);
        }

        if ($replacedImagePath !== null && $replacedImagePath !== $imagePath) {
            $imageStorage->deleteQuietly($replacedImagePath);
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

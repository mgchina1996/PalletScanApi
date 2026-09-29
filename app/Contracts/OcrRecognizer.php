<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface OcrRecognizer
{
    /** @return array{code: string|null, message: string|null, request_id: string|null, result: mixed} */
    public function recognize(UploadedFile $image): array;
}

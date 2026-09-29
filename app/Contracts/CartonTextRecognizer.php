<?php

namespace App\Contracts;

interface CartonTextRecognizer
{
    public function recognize(string $imageBytes): string;
}

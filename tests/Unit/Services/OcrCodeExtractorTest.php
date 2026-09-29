<?php

namespace Tests\Unit\Services;

use App\Services\OcrCodeExtractor;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OcrCodeExtractorTest extends TestCase
{
    #[TestWith(['carton', "CTN1LMRAYWQ\n507200\n12345\n1234\n1234567\nEN8ZTQCWL\nCTN1LMRAYWQ", ['CTN1LMRAYWQ', '507200', '12345']])]
    #[TestWith(['tpin', "EN8ZTQCWL\nCTN1LMRAYWQ\n8UVYSEBNL\nEN8ZTQCWL", ['EN8ZTQCWL', '8UVYSEBNL']])]
    public function test_returns_unique_values_matching_the_requested_type(
        string $type,
        string $content,
        array $expected,
    ): void {
        $extractor = new OcrCodeExtractor;

        $values = $extractor->extract($type, [
            'result' => ['content' => $content],
        ]);

        $this->assertSame($expected, $values);
    }

    public function test_returns_empty_array_when_ocr_content_is_missing(): void
    {
        $extractor = new OcrCodeExtractor;

        $values = $extractor->extract('tpin', ['result' => null]);

        $this->assertSame([], $values);
    }
}

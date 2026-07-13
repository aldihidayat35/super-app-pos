<?php

namespace Tests\Unit\Support;

use App\Support\DocumentNumber;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    #[Test]
    public function it_formats_a_document_number_consistently(): void
    {
        $number = DocumentNumber::format('po', 42, new DateTimeImmutable('2026-07-14 10:00:00'));

        $this->assertSame('PO/20260714/000042', $number);
    }

    #[Test]
    public function it_rejects_an_invalid_sequence(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DocumentNumber::format('PO', 0);
    }
}

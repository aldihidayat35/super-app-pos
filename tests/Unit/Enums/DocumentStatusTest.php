<?php

namespace Tests\Unit\Enums;

use App\Enums\DocumentStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DocumentStatusTest extends TestCase
{
    #[Test]
    public function it_exposes_indonesian_labels_and_final_state(): void
    {
        $this->assertSame('Selesai', DocumentStatus::Completed->label());
        $this->assertTrue(DocumentStatus::Completed->isFinal());
        $this->assertFalse(DocumentStatus::Draft->isFinal());
    }
}

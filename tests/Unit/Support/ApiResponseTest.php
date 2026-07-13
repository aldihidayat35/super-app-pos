<?php

namespace Tests\Unit\Support;

use App\Support\ApiResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    #[Test]
    public function it_builds_a_consistent_success_response(): void
    {
        $response = ApiResponse::success('Berhasil.', ['id' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'message' => 'Berhasil.',
            'data' => ['id' => 1],
        ], $response->getData(true));
    }
}

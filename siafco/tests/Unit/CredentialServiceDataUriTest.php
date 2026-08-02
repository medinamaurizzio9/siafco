<?php

namespace Tests\Unit;

use App\Services\CredentialService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CredentialServiceDataUriTest extends TestCase
{
    public function test_optional_image_source_can_be_null(): void
    {
        $service = (new ReflectionClass(CredentialService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(CredentialService::class, 'dataUri');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($service, null));
    }
}

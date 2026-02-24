<?php

namespace Tests\Unit\Casts;

use App\Casts\IntCast;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class IntCastTest extends TestCase
{
    private ?IntCast $cast;

    private ?MockObject $property;
    private ?MockObject $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new IntCast();
        $this->property = $this->createMock(DataProperty::class);
        $this->context = $this->createMock(CreationContext::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cast = null;
        $this->property = null;
        $this->context = null;
    }

    public function test_casts_numeric_string_to_int(): void
    {
        $result = $this->cast->cast($this->property, '42', [], $this->context);
        $this->assertSame(42, $result);
    }

    public function test_casts_integer_to_int(): void
    {
        $result = $this->cast->cast($this->property, 10, [], $this->context);
        $this->assertSame(10, $result);
    }

    public function test_returns_non_numeric_string_as_is(): void
    {
        $result = $this->cast->cast($this->property, 'abc', [], $this->context);
        $this->assertSame('abc', $result);
    }
}

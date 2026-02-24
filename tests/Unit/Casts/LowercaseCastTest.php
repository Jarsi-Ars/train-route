<?php

namespace Tests\Unit\Casts;

use App\Casts\LowercaseCast;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class LowercaseCastTest extends TestCase
{
    private ?LowercaseCast $cast;
    private ?MockObject $property;
    private ?MockObject $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new LowercaseCast();
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

    public function test_trims_whitespace(): void
    {
        $result = $this->cast->cast($this->property, '  Москва  ', [], $this->context);
        $this->assertSame('москва', $result);
    }

    public function test_handles_mixed_case_with_latin_and_cyrillic(): void
    {
        $result = $this->cast->cast($this->property, 'Санкт-Петербург', [], $this->context);
        $this->assertSame('санкт-петербург', $result);
    }

    public function test_already_lowercase_string_unchanged(): void
    {
        $result = $this->cast->cast($this->property, 'москва', [], $this->context);
        $this->assertSame('москва', $result);
    }
}

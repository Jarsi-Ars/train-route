<?php

namespace Tests\Unit\Services;

use App\Services\RedisCacheService;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisCacheServiceTest extends TestCase
{
    private ?RedisCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RedisCacheService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->service = null;
    }

    private function requestData(): array
    {
        return ['016А', 'москва', 'санкт-петербург', 15, 3];
    }

    private function expectedKey(): string
    {
        return 'train_path:016А:москва:санкт-петербург:15:3';
    }

    public function test_get_cache_returns_null_on_cache_miss(): void
    {
        Redis::shouldReceive('get')
            ->once()
            ->with($this->expectedKey())
            ->andReturn(null);

        $result = $this->service->getCache($this->requestData());

        $this->assertNull($result);
    }

    public function test_get_cache_returns_decoded_array_on_cache_hit(): void
    {
        $stations = [
            'indexItem' => 1,
            'station' => 'МОСКВА',
            'arrival_time' => '08:15',
            'departure_time' => '09:00',
            'stop_time' => 45,
        ];

        Redis::shouldReceive('get')
            ->once()
            ->with($this->expectedKey())
            ->andReturn(json_encode($stations, JSON_UNESCAPED_UNICODE));

        $result = $this->service->getCache($this->requestData());

        $this->assertIsArray($result);
        $this->assertSame('МОСКВА', $result['station']);
        $this->assertSame('08:15', $result['arrival_time']);
        $this->assertSame('09:00', $result['departure_time']);
        $this->assertSame(45, $result['stop_time']);
    }

    public function test_set_cache_stores_data_with_correct_key_and_ttl(): void
    {
        $ttlCache = 60 * 5;

        $stations = [
            ['indexItem' => 1, 'station' => 'МОСКВА'],
        ];

        Redis::shouldReceive('setex')
            ->once()
            ->with(
                $this->expectedKey(),
                $ttlCache,
                json_encode($stations, JSON_UNESCAPED_UNICODE)
            );

        $this->service->setCache($this->requestData(), $stations);

        $this->assertTrue(true);
    }

    public function test_cache_key_formed_correctly(): void
    {
        $capturedKey = null;

        Redis::shouldReceive('get')
            ->once()
            ->withArgs(function ($key) use (&$capturedKey) {
                $capturedKey = $key;
                return true;
            })
            ->andReturn(null);

        $this->service->getCache($this->requestData());

        $this->assertSame($this->expectedKey(), $capturedKey);
    }
}

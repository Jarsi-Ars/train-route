<?php

namespace Tests\Feature;

use App\Services\PathFindService;
use App\Services\RedisCacheService;
use App\Services\SoapRequestService;
use RuntimeException;
use Tests\TestCase;

class TrainPathControllerTest extends TestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'train_number'      => '016А',
            'departure_station' => 'Москва',
            'arrival_station'   => 'Санкт-Петербург',
            'day'               => 15,
            'month'             => 3,
        ], $overrides);
    }

    private function sampleStations(): array
    {
        return [
            ['indexItem' => 1, 'station' => 'МОСКВА', 'arrival_time' => '', 'departure_time' => '09:00', 'stop_time' => 0],
            ['indexItem' => 2, 'station' => 'САНКТ-ПЕТЕРБУРГ', 'arrival_time' => '13:00', 'departure_time' => '', 'stop_time' => 0],
        ];
    }

    public function test_index_returns_200_view(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('index');
    }

    public function test_search_returns_cached_stations_when_cache_found(): void
    {
        $stations = $this->sampleStations();

        $cache = $this->createMock(RedisCacheService::class);
        $cache->expects($this->once())
            ->method('getCache')
            ->willReturn($stations);

        $this->instance(RedisCacheService::class, $cache);

        $response = $this->postJson('/search', $this->validPayload());

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'stations' => $stations]);
    }

    public function test_search_does_not_call_soap_service_on_cache_found(): void
    {
        $cache = $this->createMock(RedisCacheService::class);
        $cache->expects($this->once())
            ->method('getCache')
            ->willReturn($this->sampleStations());

        $soap = $this->createMock(SoapRequestService::class);
        $soap->expects($this->never())
            ->method('fetchSoap');

        $this->instance(RedisCacheService::class, $cache);
        $this->instance(SoapRequestService::class, $soap);

        $this->postJson('/search', $this->validPayload());
    }

    public function test_search_calls_soap_and_returns_stations_on_cache_miss(): void
    {
        $stations = $this->sampleStations();

        $soapResponse = [
            'train_description' => [],
            'route_list' => [
                'stop_list' => []
            ]
        ];

        $cache = $this->createMock(RedisCacheService::class);
        $cache->expects($this->once())
            ->method('setCache');

        $cache->expects($this->once())
            ->method('getCache')
            ->willReturn(null);

        $soap = $this->createMock(SoapRequestService::class);
        $soap->expects($this->once())
            ->method('fetchSoap');

        $soap->expects($this->once())
            ->method('getResponse')
            ->willReturn($soapResponse);

        $pathFind = $this->createMock(PathFindService::class);
        $pathFind->expects($this->once())
            ->method('find')
            ->willReturn($stations);

        $this->instance(RedisCacheService::class, $cache);
        $this->instance(SoapRequestService::class, $soap);
        $this->instance(PathFindService::class, $pathFind);

        $response = $this->postJson('/search', $this->validPayload());

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'stations' => $stations]);
    }

    public function test_search_stores_result_in_cache_after_soap_success(): void
    {
        $stations = $this->sampleStations();

        $cache = $this->createMock(RedisCacheService::class);
        $cache->expects($this->once())
            ->method('getCache')
            ->willReturn(null);

        $cache->expects($this->once())
            ->method('setCache')
            ->with(
                $this->anything(),
                $this->callback(fn ($data) => $data === $stations)
            );

        $soap = $this->createMock(SoapRequestService::class);
        $soap->expects($this->once())
            ->method('fetchSoap');

        $soap->expects($this->once())
            ->method('getResponse')
            ->willReturn([]);

        $pathFind = $this->createMock(PathFindService::class);
        $pathFind->expects($this->once())
            ->method('find')
            ->willReturn($stations);

        $this->instance(RedisCacheService::class, $cache);
        $this->instance(SoapRequestService::class, $soap);
        $this->instance(PathFindService::class, $pathFind);

        $this->postJson('/search', $this->validPayload());
    }

    public function test_search_returns_500_on_runtime_exception_from_soap(): void
    {
        $cache = $this->createMock(RedisCacheService::class);
        $cache->expects($this->once())
            ->method('getCache')
            ->willReturn(null);

        $soap = $this->createMock(SoapRequestService::class);
        $soap->expects($this->once())
            ->method('fetchSoap')
            ->willThrowException(new RuntimeException('Ошибка SOAP при отправке запроса'));

        $this->instance(RedisCacheService::class, $cache);
        $this->instance(SoapRequestService::class, $soap);

        $response = $this->postJson('/search', $this->validPayload());

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
        $response->assertJsonStructure(['success', 'message']);
    }

    public function test_search_does_not_cache_on_error(): void
    {
        $cache = $this->createMock(RedisCacheService::class);
        $cache->expects($this->once())
            ->method('getCache')
            ->willReturn(null);
        $cache->expects($this->never())
            ->method('setCache');

        $soap = $this->createMock(SoapRequestService::class);
        $soap->expects($this->once())
            ->method('fetchSoap')
            ->willThrowException(new RuntimeException('Ошибка'));

        $this->instance(RedisCacheService::class, $cache);
        $this->instance(SoapRequestService::class, $soap);

        $this->postJson('/search', $this->validPayload());
    }

    public function test_search_response_contains_error_message_on_failure(): void
    {
        $errorMessage = 'Ошибка SOAP ответа: нет данных маршрута (route_list).';

        $cache = $this->createMock(RedisCacheService::class);
        $cache->expects($this->once())
            ->method('getCache')
            ->willReturn(null);

        $soap = $this->createMock(SoapRequestService::class);
        $soap->expects($this->once())
            ->method('fetchSoap')
            ->willThrowException(new RuntimeException($errorMessage));

        $this->instance(RedisCacheService::class, $cache);
        $this->instance(SoapRequestService::class, $soap);

        $response = $this->postJson('/search', $this->validPayload());

        $response->assertJson(['success' => false, 'message' => $errorMessage]);
    }
}

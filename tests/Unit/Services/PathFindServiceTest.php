<?php

namespace Tests\Unit\Services;

use App\Services\PathFindService;
use Tests\TestCase;

class PathFindServiceTest extends TestCase
{
    private PathFindService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PathFindService();
    }

    private function buildSoapResponse(array $stops): array
    {
        return [
            'route_list' => [
                'stop_list' => $stops,
            ],
        ];
    }

    private function makeStopStation(
        string $name,
        string $arrival = '',
        string $departure = '',
        int $stopTime = 0
    ): array
    {
        return [
            'stop'           => $name,
            'arrival_time'   => $arrival,
            'departure_time' => $departure,
            'stop_time'      => $stopTime,
        ];
    }

    public function test_returns_empty_array_when_stop_list_is_empty(): void
    {
        $response = $this->buildSoapResponse([]);

        $result = $this->service->find($response, 'москва', 'санкт-петербург');

        $this->assertSame([], $result);
    }

    public function test_returns_stations_between_departure_and_arrival(): void
    {
        $stops = [
            $this->makeStopStation('МОСКВА', '', '09:00', 0),
            $this->makeStopStation('ТВЕРь', '11:00', '11:05', 5),
            $this->makeStopStation('САНКТ-ПЕТЕРБУРГ', '13:00', '', 0),
        ];

        $result = $this->service->find(
            $this->buildSoapResponse($stops), 'москва', 'санкт-петербург'
        );

        $this->assertCount(3, $result);
        $this->assertSame(1, $result[0]['indexItem']);
        $this->assertSame('МОСКВА', $result[0]['station']);
        $this->assertSame('', $result[0]['arrival_time']);
        $this->assertSame('09:00', $result[0]['departure_time']);
        $this->assertSame(0, $result[0]['stop_time']);

        $this->assertSame(2, $result[1]['indexItem']);
        $this->assertSame('ТВЕРь', $result[1]['station']);
        $this->assertSame('11:00', $result[1]['arrival_time']);
        $this->assertSame('11:05', $result[1]['departure_time']);
        $this->assertSame(5, $result[1]['stop_time']);

        $this->assertSame(3, $result[2]['indexItem']);
        $this->assertSame('САНКТ-ПЕТЕРБУРГ', $result[2]['station']);
        $this->assertSame('13:00', $result[2]['arrival_time']);
        $this->assertSame('', $result[2]['departure_time']);
        $this->assertSame(0, $result[2]['stop_time']);
    }

    public function test_excludes_stations_before_departure(): void
    {
        $stops = [
            $this->makeStopStation('Нижний Омск'),
            $this->makeStopStation('Омск'),
            $this->makeStopStation('Москва', '', '09:00'),
            $this->makeStopStation('Санкт-Петербург', '13:00'),
        ];

        $result = $this->service->find(
            $this->buildSoapResponse($stops), 'москва', 'санкт-петербург'
        );

        $this->assertCount(2, $result);
        $this->assertSame('Москва', $result[0]['station']);
        $this->assertSame('Санкт-Петербург', $result[1]['station']);
    }

    public function test_excludes_stations_after_arrival(): void
    {
        $stops = [
            $this->makeStopStation('Москва'),
            $this->makeStopStation('Санкт-Петербург'),
            $this->makeStopStation('Омск'),
            $this->makeStopStation('Нижний Омск'),
        ];

        $result = $this->service->find(
            $this->buildSoapResponse($stops), 'москва', 'Санкт-Петербург'
        );

        $this->assertCount(2, $result);
        $this->assertSame('Санкт-Петербург', $result[1]['station']);
    }

    public function test_uses_case_insensitive_fuzzy_matching(): void
    {
        $stops = [
            $this->makeStopStation('МОСКВА'),
            $this->makeStopStation('САНКТ-Петербург'),
        ];

        $result = $this->service->find(
            $this->buildSoapResponse($stops), 'москва', 'санкт-Петербург'
        );

        $this->assertCount(2, $result);
    }

    public function test_uses_partial_name_matching_search_contains_station(): void
    {
        $stops = [
            $this->makeStopStation('МОСКВА ПАССАЖИРСКАЯ'),
            $this->makeStopStation('САНКТ-ПЕТЕРБУРГ ГЛАВНЫЙ'),
        ];

        // Поиск по подстроке: 'москва' содержится в 'москва пассажирская'
        $result = $this->service->find(
            $this->buildSoapResponse($stops), 'москва', 'санкт-петербург'
        );

        $this->assertCount(2, $result);
    }

    public function test_returns_empty_when_departure_station_not_found(): void
    {
        $stops = [
            $this->makeStopStation('Омск'),
            $this->makeStopStation('Санкт-Петербург'),
        ];

        $result = $this->service->find(
            $this->buildSoapResponse($stops), 'москва', 'санкт-Петербург'
        );

        $this->assertSame([], $result);
    }
}

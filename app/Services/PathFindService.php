<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Извлекает список остановок маршрута между станциями отправления и прибытия из SOAP-ответа */
class PathFindService
{
    /**
     * @throws RuntimeException
     */
    public function find(array $descriptionTrainPath, string $departureStation, string $arrivalStation): array
    {
        $stopList = $descriptionTrainPath['route_list']['stop_list'] ?? [];
        if (empty($stopList)) {
            Log::debug('stoplist.empty', ['from' => $departureStation, 'to' => $arrivalStation]);

            return [];
        }

        Log::debug('stoplist.processing', [
            'from' => $departureStation,
            'to' => $arrivalStation,
            'total_stops' => count($stopList),
        ]);

        $resultStations = [];
        $isCollecting = false;
        $indexStation = 1;

        foreach ($stopList as $stopStation) {
            $stationName = $stopStation['stop'];

            if (! $isCollecting && $this->stationMatches($stationName, $departureStation)) {
                $isCollecting = true;
            }

            if ($isCollecting) {
                $resultStations[] = $this->collectStation($stopStation, $indexStation++);
                if ($this->stationMatches($stationName, $arrivalStation)) {
                    break;
                }
            }
        }

        Log::debug('stoplist.result', [
            'from' => $departureStation,
            'to' => $arrivalStation,
            'stations_found' => count($resultStations),
        ]);

        return $resultStations;
    }

    private function collectStation(array $stopStation, int $indexStation): array
    {
        return [
            'indexItem' => $indexStation,
            'station' => $stopStation['stop'],
            'arrival_time' => $stopStation['arrival_time'] ?? '',
            'departure_time' => $stopStation['departure_time'] ?? '',
            'stop_time' => $stopStation['stop_time'] ?? 0,
        ];
    }

    /** Проверяем искомый город в название и наоборот (нечёткое совпадение) */
    private function stationMatches(string $stationName, string $searchName): bool
    {
        $stationNorm = mb_strtolower(trim($stationName));
        $searchNorm = mb_strtolower(trim($searchName));

        return str_contains($stationNorm, $searchNorm)
            || str_contains($searchNorm, $stationNorm);
    }
}

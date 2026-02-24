<?php

namespace App\Http\Controllers;

use App\DTO\TrainPathRequestDTO;
use App\Services\PathFindService;
use App\Services\RedisCacheService;
use App\Services\SoapRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class TrainPathController extends Controller
{
    public function __construct(
        private readonly RedisCacheService  $cacheService,
        private readonly SoapRequestService $soapService,
        private readonly PathFindService    $stopListService,
    ) { }

    public function index(): View
    {
        return view('index');
    }

    /** Выполняет поиск маршрута поезда */
    public function search(Request $request): JsonResponse
    {
        $trainPathRequestDTO = TrainPathRequestDTO::from($request);
        $logContext = [
            'train_number' => $trainPathRequestDTO->train_number,
            'departure_station' => $trainPathRequestDTO->departure_station,
            'arrival_station' => $trainPathRequestDTO->arrival_station,
            'day' => $trainPathRequestDTO->day,
            'month' => $trainPathRequestDTO->month,
        ];

        Log::info('train.search.requested', $logContext);

        $cached = $this->cacheService->getCache(
            $trainPathRequestDTO->getArray()
        );

        if ($cached !== null) {
            Log::info('train.search.cache_found', $logContext);

            return response()->json([
                'success' => true,
                'stations' => $cached,
            ]);
        }

        Log::info('train.search.cache_not_found', $logContext);

        try {
            $this->soapService->fetchSoap($trainPathRequestDTO);

            $resultStations = $this->stopListService->find(
                $this->soapService->getResponse(),
                $trainPathRequestDTO->departure_station,
                $trainPathRequestDTO->arrival_station,
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            Log::error('train.search.failed', array_merge($logContext, [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]));

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        $this->cacheService->setCache(
            $trainPathRequestDTO->getArray(),
            $resultStations,
        );

        Log::info('train.search.completed', array_merge($logContext, [
            'stations_count' => count($resultStations),
        ]));

        return response()->json([
            'success' => true,
            'stations' => $resultStations,
        ]);
    }
}

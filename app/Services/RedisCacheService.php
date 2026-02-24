<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisCacheService
{
    private const TTL_SECONDS = 60 * 5; // 5 минут

    private const KEY_PREFIX = 'train_path';

    /**
     * Формирует строковый ключ кеша из параметров запроса
     */
    private function buildKey(array $requestData): string
    {
        return implode(':', [self::KEY_PREFIX, ...$requestData]);
    }

    public function setCache(array $requestData, array $responseData): void
    {
        $keyCache = $this->buildKey($requestData);

        Redis::setex($keyCache, self::TTL_SECONDS, json_encode($responseData, JSON_UNESCAPED_UNICODE));

        Log::debug('cache.set', [
            'key' => $keyCache,
            'ttl_seconds' => self::TTL_SECONDS,
            'items_stored' => count($responseData),
        ]);
    }

    public function getCache(array $requestData): ?array
    {
        $keyCache = $this->buildKey($requestData);

        $cached = Redis::get($keyCache);

        if ($cached === null) {
            Log::debug('cache.not_found', ['key' => $keyCache]);

            return null;
        }

        Log::debug('cache.found', ['key' => $keyCache]);

        return json_decode($cached, true);
    }
}

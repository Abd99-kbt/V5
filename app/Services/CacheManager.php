<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheManager
{
    const CACHE_TTL = 3600; // 1 hour

    /**
     * Cache expensive queries with automatic invalidation
     */
    public static function remember(string $key, $callback, int $ttl = null)
    {
        $ttl = $ttl ?? self::CACHE_TTL;

        return Cache::remember($key, $ttl, function () use ($callback, $key) {
            Log::debug("Cache miss for key: {$key}");
            return $callback();
        });
    }

    /**
     * Cache model relationships
     */
    public static function rememberRelation($model, string $relation, int $ttl = null)
    {
        $key = get_class($model) . ":{$model->id}:{$relation}";
        return self::remember($key, fn() => $model->$relation, $ttl);
    }

    /**
     * Invalidate cache patterns
     */
    public static function invalidatePattern(string $pattern)
    {
        // For now, we'll use a simpler approach that works with all cache drivers
        // In production, you might want to implement a more sophisticated solution
        try {
            // This is a basic implementation - in production you'd want to track cache keys
            Log::info("Cache pattern invalidation requested", [
                'pattern' => $pattern,
                'driver' => config('cache.default')
            ]);

            // For file cache, we can't easily invalidate patterns
            // For Redis, we'd need a more complex implementation
            // For now, we'll just log the request

        } catch (\Exception $e) {
            Log::warning("Could not invalidate cache pattern: {$pattern}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cache with tags for better invalidation
     */
    public static function rememberWithTags(array $tags, string $key, $callback, int $ttl = null)
    {
        $ttl = $ttl ?? self::CACHE_TTL;

        return Cache::tags($tags)->remember($key, $ttl, function () use ($callback, $key) {
            Log::debug("Cache miss for tagged key: {$key}");
            return $callback();
        });
    }

    /**
     * Invalidate cache by tags
     */
    public static function invalidateTags(array $tags)
    {
        try {
            Cache::tags($tags)->flush();
        } catch (\Exception $e) {
            Log::warning("Could not invalidate cache tags", [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);
        }
    }
}
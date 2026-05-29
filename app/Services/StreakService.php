<?php

namespace App\Services;

use App\Models\LoginStreak;
use App\Models\User;
use App\Repositories\LoginStreakRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StreakService
{
    private const CACHE_VERSION = 'v1';
    private const CACHE_TTL_SECONDS = 3600;
    private const LOCK_SECONDS = 10;
    private const LOCK_WAIT_SECONDS = 3;

    public function __construct(
        private readonly LoginStreakRepository $repository,
    ) {}

    public function record(User $user): LoginStreak
    {
        $today = Carbon::today();

        $streak = $this->repository->getOrCreateForUser($user);
        $lastLoginDate = $streak->last_login_date;

        if ($lastLoginDate?->isSameDay($today)) {
            $streak->last_login_date = $today;
        } elseif ($lastLoginDate?->isSameDay($today->copy()->subDay())) {
            $streak->current_streak += 1;
            $streak->longest_streak = max($streak->longest_streak, $streak->current_streak);
            $streak->last_login_date = $today;
            $streak->streak_broken_at = null;
        } else {
            $wasBroken = $lastLoginDate !== null;

            $streak->current_streak = 1;
            $streak->longest_streak = max($streak->longest_streak, 1);
            $streak->last_login_date = $today;
            $streak->streak_broken_at = $wasBroken ? $today : null;
        }

        $saved = $this->repository->save($streak);

        Cache::forget($this->cacheKey($user->id));

        return $saved->fresh();
    }

    public function get(User $user): LoginStreak
    {
        $key = $this->cacheKey($user->id);

        $cached = Cache::get($key);

        if ($cached instanceof LoginStreak) {
            return $cached;
        }

        return Cache::lock($this->lockKey($user->id), self::LOCK_SECONDS)
            ->block(self::LOCK_WAIT_SECONDS, function () use ($user, $key) {
                $cached = Cache::get($key);

                if ($cached instanceof LoginStreak) {
                    return $cached;
                }

                $streak = $this->repository->getOrCreateForUser($user);

                Cache::put($key, $streak, self::CACHE_TTL_SECONDS);

                return $streak;
            });
    }

    public function leaderboard(int $limit = 10): Collection
    {
        return $this->repository->leaderboard($limit);
    }

    private function cacheKey(int $userId): string
    {
        return 'streak.' . self::CACHE_VERSION . ".{$userId}";
    }

    private function lockKey(int $userId): string
    {
        return 'streak-lock.' . self::CACHE_VERSION . ".{$userId}";
    }
}

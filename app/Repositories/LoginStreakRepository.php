<?php

namespace App\Repositories;

use App\Models\LoginStreak;
use App\Models\User;
use Illuminate\Support\Collection;

class LoginStreakRepository
{
    public function getOrCreateForUser(User $user): LoginStreak
    {
        return LoginStreak::firstOrCreate(
            ['user_id' => $user->id],
            [
                'current_streak'  => 0,
                'longest_streak'  => 0,
                'last_login_date' => null,
                'streak_broken_at' => null,
            ]
        );
    }

    public function save(LoginStreak $streak): LoginStreak
    {
        $streak->save();

        return $streak;
    }

    public function leaderboard(int $limit = 10): Collection
    {
        return LoginStreak::with('user:id,name,email')
            ->orderByDesc('longest_streak')
            ->orderByDesc('current_streak')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();
    }
}

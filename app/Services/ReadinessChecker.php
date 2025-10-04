<?php

namespace App\Services;

use App\Contracts\ReadinessCheckerInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

readonly class ReadinessChecker implements ReadinessCheckerInterface
{
    public function __construct()
    {
    }

    public function check(): array
    {
        $ok      = true;
        $reasons = [];
        $details = [];

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $details['db'] = ['status' => 'up'];
        } catch (Throwable $e) {
            $ok            = false;
            $reasons['db'] = 'database unavailable';
            $details['db'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        try {
            /** @var \Redis $redisClient */
            $redisClient = Redis::connection()->client();
            if ($redisClient->ping()) {
                $details['redis'] = ['status' => 'up'];
            } else {
                throw new \RuntimeException('Unexpected PING response');
            }
        } catch (Throwable $e) {
            $ok               = false;
            $reasons['redis'] = 'redis unavailable';
            $details['redis'] = ['status' => 'down', 'error' => $e->getMessage()];
        }

        return [
            'ok'      => $ok,
            'reasons' => $reasons,
            'details' => $details,
        ];
    }
}

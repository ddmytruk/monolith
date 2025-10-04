<?php

namespace App\Http\Controllers;

use App\Contracts\ReadinessCheckerInterface;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'version' => config('app_meta.version'),
            'commit'  => config('app_meta.commit'),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public function ready(ReadinessCheckerInterface $checker): JsonResponse
    {
        $result = $checker->check();

        if ($result['ok']) {
            return response()->json([
                'status'  => 'ok',
                'details' => $result['details'],
            ], 200, ['Cache-Control' => 'no-store']);
        }

        return response()->json([
            'status'  => 'error',
            'reason'  => implode('; ', array_values($result['reasons'])),
            'details' => $result['details'],
        ], 503, ['Cache-Control' => 'no-store']);
    }

    public function live(): JsonResponse
    {
        return response()->json(['status' => 'alive'], 200, ['Cache-Control' => 'no-store']);
    }
}

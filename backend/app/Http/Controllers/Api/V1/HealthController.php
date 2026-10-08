<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $database = 'ok';
        } catch (Throwable $e) {
            report($e);
            $database = 'erreur';
        }

        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'version' => config('app.version'),
            'database' => $database,
            'time' => now()->toIso8601String(),
        ], $database === 'ok' ? 200 : 503);
    }
}

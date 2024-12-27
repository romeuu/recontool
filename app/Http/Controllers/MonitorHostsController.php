<?php

namespace App\Http\Controllers;

use App\Console\Commands\MonitorHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class MonitorHostsController extends Controller
{
    public function index(Request $request)
    {
        try {
            Artisan::call('app:monitor-hosts');
            $output = Artisan::output();

            // Devolver la respuesta
            return response()->json([
                'status' => 'success',
                'output' => $output,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

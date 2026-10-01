<?php

namespace AuraTech\SmartDashboard\Http\Controllers;

use AuraTech\SmartDashboard\DashboardService;
use AuraTech\SmartDashboard\Http\Controllers\Concerns\ResolvesUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    use ResolvesUser;

    public function __construct(private DashboardService $sd) {}

    public function index(Request $request)
    {
        return view('smart-dashboard::dashboard', [
            'boot' => $this->sd->dashboardBootstrap($this->sdUser($request)),
            'urls' => $this->urls(),
        ]);
    }

    /** POST {items:[{id,key,options}], period, from, to, user_id, warehouse_id, fresh} */
    public function data(Request $request): JsonResponse
    {
        $params = $request->only(['period', 'from', 'to', 'user_id', 'warehouse_id', 'fresh']);
        return response()->json($this->sd->runBatch((array) $request->input('items', []), $params, $this->sdUser($request)));
    }

    public static function urls(): array
    {
        $p = trim(config('smart-dashboard.route_prefix', 'smart-dashboard'), '/');
        return [
            'dashboard' => url($p), 'editor' => url("$p/editor"), 'api' => url("$p/api"), 'assets' => url("$p/assets"),
            'csrf' => csrf_token(), 'base' => rtrim(url('/'), '/'),
        ];
    }
}

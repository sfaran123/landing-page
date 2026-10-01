<?php

namespace AuraTech\SmartDashboard\Http\Controllers;

use AuraTech\SmartDashboard\AccessDenied;
use AuraTech\SmartDashboard\DashboardService;
use AuraTech\SmartDashboard\Http\Controllers\Concerns\ResolvesUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class EditorController extends Controller
{
    use ResolvesUser;

    public function __construct(private DashboardService $sd) {}

    public function index(Request $request)
    {
        $user = $this->sdUser($request);
        abort_unless($this->sd->canEditPersonal($user), 403);
        return view('smart-dashboard::editor', ['boot' => $this->sd->editorBootstrap($user), 'urls' => DashboardController::urls()]);
    }

    public function show(Request $request): JsonResponse
    {
        return $this->guard(fn () => $this->sd->getLayout((string) $request->query('scope'), (string) $request->query('id')));
    }

    public function save(Request $request): JsonResponse
    {
        return $this->guard(fn () => $this->sd->saveLayout((string) $request->input('scope'), (string) $request->input('id'),
            (array) $request->input('layout', []), $this->sdUser($request)));
    }

    public function reset(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $this->sd->resetLayout((string) $request->input('scope'), (string) $request->input('id'), $this->sdUser($request));
            return $this->sd->getLayout((string) $request->input('scope'), (string) $request->input('id'));
        });
    }

    public function resetPersonal(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) { $this->sd->resetAllPersonal($this->sdUser($request)); return ['ok' => true]; });
    }

    public function build(Request $request): JsonResponse
    {
        return $this->guard(fn () => $this->sd->buildLayout((string) $request->input('business_type'), (array) $request->input('activities', [])));
    }

    public function detect(Request $request): JsonResponse
    {
        return $this->guard(fn () => $this->sd->detectActivities($this->sdUser($request)));
    }

    public function settings(Request $request): JsonResponse
    {
        return $this->guard(fn () => $this->sd->saveSettings((array) $request->input('settings', []), $this->sdUser($request)));
    }

    private function guard(callable $f): JsonResponse
    {
        try {
            return response()->json($f());
        } catch (AccessDenied $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}

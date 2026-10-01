<?php

namespace AuraTech\SmartDashboard\Http\Controllers;

use Illuminate\Routing\Controller;

/** Serves the module's CSS/JS straight from the package – no `vendor:publish` step needed. */
class AssetController extends Controller
{
    private const TYPES = ['css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8', 'svg' => 'image/svg+xml'];

    public function __invoke(string $file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $path = realpath(__DIR__ . '/../../../resources/assets/' . basename($file));
        abort_unless($path && isset(self::TYPES[$ext]), 404);
        return response()->file($path, [
            'Content-Type' => self::TYPES[$ext],
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}

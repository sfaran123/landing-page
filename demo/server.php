<?php

/**
 * Standalone demo – runs the real module (same service, same assets) on a seeded
 * SQLite database with ~13 months of invented data. No Laravel required.
 *
 *   php -S localhost:8080 demo/server.php
 *   open http://localhost:8080          (add ?as=cashier to see a non-admin user)
 */

use AuraTech\SmartDashboard\AccessDenied;
use AuraTech\SmartDashboard\DashboardService;
use AuraTech\SmartDashboard\Support\PdoConnection;
use AuraTech\SmartDashboard\Tests\Seeder;

require __DIR__ . '/../tests/bootstrap.php';
date_default_timezone_set('UTC');   // like most servers – the module must not depend on it

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = dirname(__DIR__);

// ---- static assets ------------------------------------------------------
if (preg_match('#^/assets/([A-Za-z0-9._-]+)$#', $path, $m)) {
    $file = "$root/resources/assets/{$m[1]}";
    if (!is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/javascript') . '; charset=utf-8');
    readfile($file);
    exit;
}

// ---- database (re-seeded once a day so "today" always has data) ----------
$dbFile = __DIR__ . '/demo.sqlite';
if (!is_file($dbFile) || date('Y-m-d', filemtime($dbFile)) !== date('Y-m-d') || isset($_GET['reseed'])) {
    @unlink($dbFile);
    $pdo = new PDO('sqlite:' . $dbFile);
    (new Seeder($pdo))->seed(new DateTimeImmutable('now', new DateTimeZone('Asia/Jerusalem')));
    $pdo->exec("CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT)");
    $pdo->exec("INSERT INTO roles VALUES (1,'מנהל'),(4,'קופאי'),(5,'שירות עצמי')");
}
$pdo = new PDO('sqlite:' . $dbFile);
$db = new PdoConnection($pdo);

$config = sd_config();
$config['debug'] = true;
$config['cache_ttl'] = 0;
$sd = new DashboardService($db, $config);

session_start();
if (isset($_GET['as'])) $_SESSION['as'] = $_GET['as'];
$as = $_SESSION['as'] ?? 'admin';
$user = $as === 'cashier'
    ? ['id' => 2, 'role_id' => 4, 'name' => 'קופה 1']
    : ['id' => 1, 'role_id' => 1, 'name' => 'שרבל פארן'];

$urls = ['dashboard' => '/', 'editor' => '/editor', 'api' => '/api', 'assets' => '/assets', 'csrf' => '', 'base' => ''];

$json = function ($data, int $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];

try {
    switch ($path) {
        case '/':
        case '/editor':
            $page = $path === '/' ? 'dashboard' : 'editor';
            $boot = $page === 'dashboard' ? $sd->dashboardBootstrap($user) : $sd->editorBootstrap($user);
            echo '<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">',
                '<title>', $page === 'dashboard' ? 'לוח בקרה' : 'התאמת לוח הבקרה', ' · AuraTech (דמו)</title>',
                '<style>html,body{margin:0;background:#f3f5fa}</style></head><body>';
            include "$root/resources/views/page.php";
            echo '</body></html>';
            exit;
        case '/api/data':
            $json($sd->runBatch($in['items'] ?? [], $in, $user));
        case '/api/layout':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') $json($sd->getLayout($_GET['scope'] ?? '', $_GET['id'] ?? ''));
            $json($sd->saveLayout($in['scope'] ?? '', (string) ($in['id'] ?? ''), $in['layout'] ?? [], $user));
        case '/api/layout/reset':
            $sd->resetLayout($in['scope'] ?? '', (string) ($in['id'] ?? ''), $user);
            $json($sd->getLayout($in['scope'] ?? '', (string) ($in['id'] ?? '')));
        case '/api/layout/reset-personal':
            $sd->resetAllPersonal($user);
            $json(['ok' => true]);
        case '/api/build':
            $json($sd->buildLayout((string) ($in['business_type'] ?? ''), (array) ($in['activities'] ?? [])));
        case '/api/detect':
            $json($sd->detectActivities($user));
        case '/api/settings':
            $json($sd->saveSettings((array) ($in['settings'] ?? []), $user));
    }
    http_response_code(404);
    echo 'not found';
} catch (AccessDenied $e) {
    $json(['error' => $e->getMessage()], 403);
} catch (InvalidArgumentException $e) {
    $json(['error' => $e->getMessage()], 422);
}

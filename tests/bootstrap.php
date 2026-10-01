<?php

// Framework-free bootstrap: PSR-4 autoload for the module + a stub env() so the config file loads.
spl_autoload_register(function ($class) {
    $prefix = 'AuraTech\\SmartDashboard\\';
    if (str_starts_with($class, $prefix)) {
        $rel = substr($class, strlen($prefix));
        if (str_starts_with($rel, 'Tests\\')) {
            $file = __DIR__ . '/' . str_replace('\\', '/', substr($rel, 6)) . '.php';
        } else {
            $file = __DIR__ . '/../src/' . str_replace('\\', '/', $rel) . '.php';
        }
        if (is_file($file)) require $file;
    }
});

if (!function_exists('env')) {
    function env($key, $default = null) { return $default; }
}

use AuraTech\SmartDashboard\Support\PdoConnection;
use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Support\Schema;
use AuraTech\SmartDashboard\Tests\Seeder;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

function sd_config(): array
{
    return require __DIR__ . '/../config/smart-dashboard.php';
}

function sd_demo_db(DateTimeImmutable $now, string $file = ':memory:'): PdoConnection
{
    if ($file !== ':memory:' && is_file($file)) unlink($file);
    $pdo = new PDO('sqlite:' . $file);
    (new Seeder($pdo))->seed($now);
    return new PdoConnection($pdo);
}

function sd_ctx(PdoConnection $db, array $cfg, string $preset, DateTimeImmutable $now, array $options = [], array $settings = [], ?int $userId = null): WidgetContext
{
    return new WidgetContext(
        $db, new Schema($cfg['schema'], $db), Period::make($preset, null, null, $cfg['timezone'], $now),
        $userId, null, $options, $settings, $cfg
    );
}

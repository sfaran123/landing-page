<?php

namespace AuraTech\SmartDashboard\Layouts;

/** Layout JSON helpers: {version, items:[{id,key,x,y,w,h,title?,options{}}]} */
class Layout
{
    public static function sanitize(array $layout, ?array $knownKeys = null): array
    {
        $items = [];
        $seen = [];
        foreach (($layout['items'] ?? []) as $i) {
            $key = (string) ($i['key'] ?? '');
            if ($key === '' || ($knownKeys !== null && !in_array($key, $knownKeys, true))) continue;
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($i['id'] ?? '')) ?: $key . '_' . count($items);
            if (isset($seen[$id])) $id .= '_' . count($items);
            $seen[$id] = true;
            $opts = is_array($i['options'] ?? null) ? $i['options'] : [];
            array_walk_recursive($opts, function (&$v) { if (is_string($v)) $v = mb_substr(strip_tags($v), 0, 300); });
            $items[] = [
                'id' => $id, 'key' => $key,
                'x' => max(0, min(11, (int) ($i['x'] ?? 0))), 'y' => max(0, (int) ($i['y'] ?? 0)),
                'w' => max(1, min(12, (int) ($i['w'] ?? 4))), 'h' => max(1, min(12, (int) ($i['h'] ?? 4))),
                'title' => isset($i['title']) && $i['title'] !== '' ? mb_substr(strip_tags((string) $i['title']), 0, 80) : null,
                'options' => $opts,
            ];
        }
        return ['version' => 1, 'items' => $items];
    }

    /** Flow-pack [key, w, h, options?, title?] rows into x/y positions on a 12-column grid. */
    public static function pack(array $specs, bool $fill = false): array
    {
        // 1. split into rows of <= 12 columns
        $rows = [[]]; $used = 0;
        foreach ($specs as $s) {
            $w = max(1, min(12, (int) $s[1]));
            if ($used + $w > 12 && $rows[array_key_last($rows)]) { $rows[] = []; $used = 0; }
            $rows[array_key_last($rows)][] = $s; $used += $w;
        }
        // 2. optionally widen items so no row leaves an empty gap (after widgets were removed)
        $items = []; $y = 0; $count = [];
        foreach ($rows as $row) {
            if (!$row) continue;
            if ($fill) {
                $gap = 12 - array_sum(array_map(fn ($s) => (int) $s[1], $row));
                for ($i = 0; $gap > 0; $i = ($i + 1) % count($row), $gap--) $row[$i][1]++;
            }
            $x = 0; $rowH = 0;
            foreach ($row as $s) {
                [$key, $w, $h] = $s;
                $count[$key] = ($count[$key] ?? 0) + 1;
                $items[] = ['id' => $key . ($count[$key] > 1 ? '_' . $count[$key] : ''), 'key' => $key, 'x' => $x, 'y' => $y, 'w' => (int) $w, 'h' => (int) $h,
                    'title' => $s[4] ?? null, 'options' => $s[3] ?? []];
                $x += $w; $rowH = max($rowH, $h);
            }
            $y += $rowH;
        }
        return ['version' => 1, 'items' => $items];
    }
}

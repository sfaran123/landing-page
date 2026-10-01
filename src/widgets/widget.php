<?php

namespace AuraTech\SmartDashboard\Widgets;

/**
 * Base class for every dashboard widget.
 *
 * A widget = metadata (shown in the editor library) + data() (JSON for the UI).
 * The front-end renders by `type`: kpi | chart | table | tabs-table | list |
 * insights | actions | progress | heatmap | aging.
 */
abstract class Widget
{
    use Queries;

    /** Unique key, e.g. 'kpi_revenue'. */
    abstract public static function key(): string;

    /** @return array{title:string,description:string,category:string,type:string,size:array} */
    abstract public function meta(): array;

    abstract public function data(WidgetContext $ctx): array;

    /** Entities/columns this widget needs. Missing => hidden from the library, never errors. */
    public function requires(): array
    {
        return [];
    }

    public function isAvailable(WidgetContext $ctx): bool
    {
        foreach ($this->requires() as $entity => $cols) {
            if (!$ctx->schema->available($entity, $cols)) {
                return false;
            }
        }
        return true;
    }

    /** Full metadata with defaults applied. */
    public function describe(): array
    {
        $m = $this->meta();
        return array_merge([
            'key' => static::key(),
            'description' => '',
            'category' => 'general',
            'icon' => 'chart',
            'size' => ['w' => 4, 'h' => 4],
            'min' => ['w' => 2, 'h' => 2],
            'business_types' => [],   // recommended for; empty = universal
            'permission' => null,
            'uses_period' => true,
            'link' => null,
            'options' => [],
            'multiple' => false,      // may appear more than once (with different options)
        ], $m, ['key' => static::key()]);
    }

    /** Options merged with their declared defaults. */
    protected function opts(WidgetContext $ctx): array
    {
        $out = [];
        foreach ($this->describe()['options'] as $name => $def) {
            $out[$name] = $ctx->options[$name] ?? ($def['default'] ?? null);
        }
        return $out + $ctx->options;
    }

    public static function periodOption(string $default = 'inherit'): array
    {
        return [
            'type' => 'select', 'label' => 'תקופה', 'default' => $default,
            'choices' => ['inherit' => 'לפי הסינון הראשי', 'today' => 'היום', '7d' => '7 ימים', 'month' => 'החודש',
                'last_month' => 'חודש קודם', 'quarter' => 'רבעון', 'year' => 'השנה'],
        ];
    }

    public static function limitOption(int $default = 5): array
    {
        return ['type' => 'select', 'label' => 'מספר שורות', 'default' => $default,
            'choices' => [5 => '5', 10 => '10', 15 => '15', 20 => '20']];
    }
}

<?php

namespace AuraTech\SmartDashboard\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * A reporting window. `to` is exclusive and never later than "now", so the
 * comparison period is always like-for-like (month-to-date vs last month-to-date).
 */
class Period
{
    public const PRESETS = [
        'today' => 'היום', 'yesterday' => 'אתמול', '7d' => '7 ימים', '30d' => '30 ימים',
        'month' => 'החודש', 'last_month' => 'חודש קודם', 'quarter' => 'רבעון', 'year' => 'השנה', 'custom' => 'מותאם',
    ];

    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly string $preset,
        public readonly DateTimeImmutable $now,
    ) {}

    public static function make(string $preset = 'month', ?string $from = null, ?string $to = null, string $tz = 'Asia/Jerusalem', ?DateTimeImmutable $now = null): self
    {
        $zone = new DateTimeZone($tz);
        $now = $now ? $now->setTimezone($zone) : new DateTimeImmutable('now', $zone);
        $today = $now->setTime(0, 0);
        $preset = array_key_exists($preset, self::PRESETS) ? $preset : 'month';

        [$f, $t] = match ($preset) {
            'today'      => [$today, $today->modify('+1 day')],
            'yesterday'  => [$today->modify('-1 day'), $today],
            '7d'         => [$today->modify('-6 days'), $today->modify('+1 day')],
            '30d'        => [$today->modify('-29 days'), $today->modify('+1 day')],
            'month'      => [$today->modify('first day of this month'), $today->modify('first day of next month')],
            'last_month' => [$today->modify('first day of last month'), $today->modify('first day of this month')],
            'quarter'    => (function () use ($today) {
                $q = intdiv((int) $today->format('n') - 1, 3) * 3 + 1;
                $s = $today->setDate((int) $today->format('Y'), $q, 1);
                return [$s, $s->modify('+3 months')];
            })(),
            'year'       => [$today->setDate((int) $today->format('Y'), 1, 1), $today->setDate((int) $today->format('Y') + 1, 1, 1)],
            'custom'     => (function () use ($from, $to, $zone, $today) {
                $f = $from ? DateTimeImmutable::createFromFormat('!Y-m-d', $from, $zone) : false;
                $t = $to ? DateTimeImmutable::createFromFormat('!Y-m-d', $to, $zone) : false;
                $f = $f ?: $today->modify('-29 days');
                $t = ($t ?: $today)->modify('+1 day');
                return $f < $t ? [$f, $t] : [$t->modify('-1 day'), $f->modify('+1 day')];
            })(),
        };

        // never look into the future
        $effectiveTo = $t > $now ? $now : $t;
        if ($effectiveTo <= $f) {
            $effectiveTo = $f->modify('+1 second');
        }
        return new self($f, $effectiveTo, $preset, $now);
    }

    /** Same-length window immediately comparable to this one. */
    public function previous(): self
    {
        $shift = match ($this->preset) {
            'today', 'yesterday' => '-1 day',
            '7d'   => '-7 days',
            '30d'  => '-30 days',
            'month', 'last_month' => '-1 month',
            'quarter' => '-3 months',
            'year' => '-1 year',
            default => null,
        };
        if ($shift === null) {
            $len = $this->to->getTimestamp() - $this->from->getTimestamp();
            $f = $this->from->sub(new DateInterval('PT' . max(1, $len) . 'S'));
            return new self($f, $this->from, 'custom', $this->now);
        }
        return new self($this->from->modify($shift), $this->to->modify($shift), $this->preset, $this->now);
    }

    public function granularity(): string
    {
        $days = ($this->to->getTimestamp() - $this->from->getTimestamp()) / 86400;
        return $days <= 1.01 ? 'hour' : ($days <= 92 ? 'day' : 'month');
    }

    /** Ordered list of bucket keys covering the full nominal period (for zero-filled charts). */
    public function buckets(?string $granularity = null, bool $full = true): array
    {
        $g = $granularity ?? $this->granularity();
        $end = $full ? $this->nominalEnd() : $this->to;
        $out = [];
        if ($g === 'hour') {
            for ($h = 0; $h < 24; $h++) $out[] = (string) $h;
            return $out;
        }
        $step = $g === 'month' ? '+1 month' : '+1 day';
        $fmt = $g === 'month' ? 'Y-m' : 'Y-m-d';
        $cur = $g === 'month' ? $this->from->modify('first day of this month') : $this->from;
        while ($cur < $end && count($out) < 400) {
            $out[] = $cur->format($fmt);
            $cur = $cur->modify($step);
        }
        return $out;
    }

    /** End of the period as selected (may be in the future). */
    public function nominalEnd(): DateTimeImmutable
    {
        return $this->guessNominalEnd();
    }

    private function guessNominalEnd(): DateTimeImmutable
    {
        return match ($this->preset) {
            'today', 'yesterday' => $this->from->modify('+1 day'),
            'month', 'last_month' => $this->from->modify('first day of next month'),
            'quarter' => $this->from->modify('+3 months'),
            'year' => $this->from->modify('+1 year'),
            default => $this->to,
        };
    }

    /** Fraction of the nominal period already elapsed (for run-rate forecasts). */
    public function elapsedRatio(): float
    {
        $total = $this->guessNominalEnd()->getTimestamp() - $this->from->getTimestamp();
        $done = $this->to->getTimestamp() - $this->from->getTimestamp();
        return $total > 0 ? max(0.0, min(1.0, $done / $total)) : 1.0;
    }

    public function fromSql(): string
    {
        return $this->from->format('Y-m-d H:i:s');
    }

    public function toSql(): string
    {
        return $this->to->format('Y-m-d H:i:s');
    }

    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'label' => self::PRESETS[$this->preset] ?? $this->preset,
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->modify('-1 second')->format('Y-m-d'),
            'granularity' => $this->granularity(),
        ];
    }
}

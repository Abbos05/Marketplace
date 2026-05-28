<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueChartQueryService
{
    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    public function resolveRange(Request $request): array
    {
        $period = $request->input('period');
        $today = Carbon::today();

        if ($period) {
            return match ($period) {
                '7d' => [$today->copy()->subDays(6), $today->copy(), '7 дней'],
                '30d' => [$today->copy()->subDays(29), $today->copy(), '30 дней'],
                'month' => [$today->copy()->startOfMonth(), $today->copy(), 'текущий месяц'],
                'year' => [$today->copy()->startOfYear(), $today->copy(), (string) $today->year],
                'all' => $this->allTimeRange($today),
                default => $this->rangeFromInputs($request),
            };
        }

        return $this->rangeFromInputs($request);
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function rangeFromInputs(Request $request): array
    {
        $from = $request->input('from');
        $to = $request->input('to');
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : Carbon::today()->copy()->subDays(29);
        $toDate = $to ? Carbon::parse($to)->startOfDay() : Carbon::today();
        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }
        $label = $fromDate->eq($toDate)
            ? $fromDate->format('d.m.Y')
            : $fromDate->format('d.m.Y').' — '.$toDate->format('d.m.Y');

        return [$fromDate, $toDate, $label];
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function allTimeRange(Carbon $today): array
    {
        $first = Order::query()
            ->whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED])
            ->min('created_at');

        $from = $first
            ? Carbon::parse($first)->startOfDay()
            : $today->copy()->subDays(29);

        return [$from, $today->copy(), 'всё время'];
    }

    /**
     * @return array{data: list<array{date: string, label: string, revenue: float, count: int}>, granularity: string, period_label: string}
     */
    public function getChartPayload(Carbon $from, Carbon $to, ?string $period = null): array
    {
        $granularity = $this->resolveGranularity($from, $to, $period);

        $rows = match ($granularity) {
            'year' => $this->queryYearly($from, $to),
            'month' => $this->queryMonthlyFilled($from, $to, $period === 'year'),
            'week' => $this->queryWeekly($from, $to),
            default => $this->queryDaily($from, $to),
        };

        $periodLabel = match ($granularity) {
            'year' => 'по годам',
            'month' => $period === 'year' ? 'по месяцам года' : 'по месяцам',
            'week' => 'по неделям',
            default => 'по дням',
        };

        return [
            'data' => $rows,
            'granularity' => $granularity,
            'period_label' => $periodLabel,
        ];
    }

    private function resolveGranularity(Carbon $from, Carbon $to, ?string $period): string
    {
        if ($period) {
            return match ($period) {
                '7d', '30d', 'month' => 'day',
                'year' => 'month',
                'all' => $this->granularityForAllTime($from, $to),
                default => $this->autoGranularity($from, $to),
            };
        }

        return $this->autoGranularity($from, $to);
    }

    private function granularityForAllTime(Carbon $from, Carbon $to): string
    {
        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        if ($days > 730) {
            return 'year';
        }
        if ($days > 45) {
            return 'month';
        }

        return 'day';
    }

    private function autoGranularity(Carbon $from, Carbon $to): string
    {
        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        if ($days <= 45) {
            return 'day';
        }
        if ($days <= 120) {
            return 'week';
        }
        if ($days <= 730) {
            return 'month';
        }

        return 'year';
    }

    /** @return Collection<string, object> */
    private function aggregateByPeriodKey(Carbon $from, Carbon $to, string $periodSql): Collection
    {
        return Order::whereNotIn('status', [Order::STATUS_CANCELED, Order::STATUS_REFUSED])
            ->where('created_at', '>=', $from->copy()->startOfDay())
            ->where('created_at', '<=', $to->copy()->endOfDay())
            ->selectRaw("{$periodSql} as period_key, SUM(total) as revenue, COUNT(*) as orders_count")
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy('period_key');
    }

    private function monthPeriodSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-01', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m-01')";
    }

    private function yearPeriodSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-01-01', created_at)"
            : "DATE_FORMAT(created_at, '%Y-01-01')";
    }

    private function weekPeriodSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "date(created_at, 'weekday 1', '-7 days')"
            : 'DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY))';
    }

    private function ruMonthShort(Carbon $date): string
    {
        static $months = [
            1 => 'янв', 2 => 'фев', 3 => 'мар', 4 => 'апр',
            5 => 'май', 6 => 'июн', 7 => 'июл', 8 => 'авг',
            9 => 'сен', 10 => 'окт', 11 => 'ноя', 12 => 'дек',
        ];

        return $months[(int) $date->month] ?? $date->format('m');
    }

    /** @return list<array{date: string, label: string, revenue: float, count: int}> */
    private function queryDaily(Carbon $from, Carbon $to): array
    {
        $aggregated = $this->aggregateByPeriodKey($from, $to, 'DATE(created_at)');

        $data = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $day = $cursor->toDateString();
            $row = $aggregated->get($day);
            $data[] = [
                'date' => $day,
                'label' => $cursor->format('d.m'),
                'revenue' => (float) ($row->revenue ?? 0),
                'count' => (int) ($row->orders_count ?? 0),
            ];
            $cursor->addDay();
        }

        return $data;
    }

    /** @return list<array{date: string, label: string, revenue: float, count: int}> */
    private function queryWeekly(Carbon $from, Carbon $to): array
    {
        $aggregated = $this->aggregateByPeriodKey($from, $to, $this->weekPeriodSql());

        $data = [];
        $cursor = $from->copy()->startOfDay()->startOfWeek(Carbon::MONDAY);
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $aggregated->get($key);
            $weekEnd = $cursor->copy()->addDays(6);
            $data[] = [
                'date' => $key,
                'label' => $cursor->format('d.m').'–'.$weekEnd->format('d.m'),
                'revenue' => (float) ($row->revenue ?? 0),
                'count' => (int) ($row->orders_count ?? 0),
            ];
            $cursor->addWeek();
        }

        return $data;
    }

    /** @return list<array{date: string, label: string, revenue: float, count: int}> */
    private function queryMonthlyFilled(Carbon $from, Carbon $to, bool $shortMonthOnly = false): array
    {
        $aggregated = $this->aggregateByPeriodKey($from, $to, $this->monthPeriodSql());

        $data = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-01');
            $row = $aggregated->get($key);
            $label = $shortMonthOnly
                ? $this->ruMonthShort($cursor)
                : $this->ruMonthShort($cursor).' '.$cursor->format('Y');

            $data[] = [
                'date' => $key,
                'label' => $label,
                'revenue' => (float) ($row->revenue ?? 0),
                'count' => (int) ($row->orders_count ?? 0),
            ];
            $cursor->addMonth();
        }

        return $data;
    }

    /** @return list<array{date: string, label: string, revenue: float, count: int}> */
    private function queryYearly(Carbon $from, Carbon $to): array
    {
        $aggregated = $this->aggregateByPeriodKey($from, $to, $this->yearPeriodSql());

        $data = [];
        $cursor = $from->copy()->startOfYear();
        $end = $to->copy()->startOfYear();
        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-01-01');
            $row = $aggregated->get($key);
            $data[] = [
                'date' => $key,
                'label' => $cursor->format('Y'),
                'revenue' => (float) ($row->revenue ?? 0),
                'count' => (int) ($row->orders_count ?? 0),
            ];
            $cursor->addYear();
        }

        return $data;
    }
}

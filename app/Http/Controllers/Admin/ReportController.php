<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\Bookings;
use App\Reports\Overview;
use App\Reports\Quality;
use App\Reports\Report;
use App\Reports\Revenue;
use App\Reports\Sales;
use App\Reports\Service;
use App\Reports\Staff;
use App\Support\SimpleXlsx;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Thống kê: mỗi tab một báo cáo. Doanh thu chọn theo ngày / tuần / tháng; các tab khác chọn khoảng ngày
 * (mặc định 7 ngày gần nhất, so với 7 ngày trước đó). Mỗi tab xuất được Excel.
 */
class ReportController extends Controller
{
    public const TABS = ['overview', 'revenue', 'service', 'quality', 'bookings', 'staff', 'sales'];

    public const MAX_DAYS = 92;

    public const LIMITS = [10, 15, 20, 30];

    private const CLASSES = [
        'overview' => Overview::class, 'revenue' => Revenue::class, 'service' => Service::class, 'quality' => Quality::class,
        'bookings' => Bookings::class, 'staff' => Staff::class, 'sales' => Sales::class,
    ];

    public function show(Request $request, string $tab = 'overview'): View
    {
        $tabs = $this->tabs();
        abort_unless(in_array($tab, $tabs, true), 404);

        [$from, $to, $options] = $this->period($request, $tab);

        return view('admin.reports', [
            'tab' => $tab,
            'tabs' => $tabs,
            'from' => $from,
            'to' => $to,
            'options' => $options,
            'blocks' => $this->report($tab, $from, $to, $options)->blocks(),
        ]);
    }

    public function export(Request $request, string $tab): BinaryFileResponse
    {
        abort_unless(in_array($tab, $this->tabs(), true), 404);

        [$from, $to, $options] = $this->period($request, $tab);
        $rows = [[__('reports.export_range', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y')])]];

        // Mỗi khối một đoạn: tiêu đề, rồi các dòng; con số và biểu đồ xuất thành hai cột.
        foreach ($this->report($tab, $from, $to, $options)->blocks() as $block) {
            $rows[] = [];
            match ($block['type']) {
                'kpis' => array_push($rows, ...array_map(fn ($item) => [$item['label'], $item['value'], $item['note'] ?? '', $item['trend']['text'] ?? ''], $block['items'])),
                'columns', 'bars' => array_push($rows, [$block['title']], ...array_map(fn ($item) => [$item['label'], $item['text']], $block['items'])),
                'table' => array_push($rows, [$block['title']], $block['headers'], ...$block['rows']),
                'heatmap' => array_push($rows, [$block['title']], ['', ...$block['cols']], ...array_map(fn ($row) => [$row['label'], ...$row['cells']], $block['rows'])),
                'alerts' => array_push($rows, ...array_map(fn ($text) => [$text], $block['items'])),
                default => array_push($rows, [$block['text'] ?? '']),
            };
        }

        return SimpleXlsx::download('thong-ke-'.$tab.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx', [__('reports.tabs.'.$tab)], $rows);
    }

    /** Tab nào có tuỳ module đang bật. @return list<string> */
    private function tabs(): array
    {
        $on = fn (string ...$modules) => collect($modules)->contains(fn ($module) => has_module($module));

        return array_values(array_filter(self::TABS, fn (string $tab) => match ($tab) {
            'revenue', 'sales' => $on('dinein', 'takeaway'),
            'service', 'quality' => $on('dinein'),
            'bookings' => $on('reservation', 'takeaway'),
            'staff' => $on('dinein', 'schedule'),
            default => true,
        }));
    }

    private function report(string $tab, CarbonImmutable $from, CarbonImmutable $to, array $options): Report
    {
        $class = self::CLASSES[$tab];

        return new $class(tenant(), $from, $to, $options);
    }

    /** @return array{CarbonImmutable, CarbonImmutable, array<string, mixed>} */
    private function period(Request $request, string $tab): array
    {
        $parse = function (string $key) use ($request): ?CarbonImmutable {
            try {
                return $request->filled($key) ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query($key)) : null;
            } catch (Throwable) {
                return null;
            }
        };
        $limit = in_array((int) $request->query('limit'), self::LIMITS, true) ? (int) $request->query('limit') : 15;

        if ($tab === 'revenue') {
            $period = in_array($request->query('period'), ['day', 'week', 'month'], true) ? $request->query('period') : 'week';
            $date = min($parse('date') ?? CarbonImmutable::today(), CarbonImmutable::today());

            [$from, $to] = match ($period) {
                'day' => [$date, $date],
                'month' => [$date->startOfMonth(), $date->endOfMonth()->startOfDay()],
                default => [$date->startOfWeek(), $date->endOfWeek()->startOfDay()],
            };

            return [$from, $to, ['period' => $period, 'date' => $date, 'limit' => $limit]];
        }

        $to = min($parse('to') ?? CarbonImmutable::today(), CarbonImmutable::today());
        $from = $parse('from') ?? $to->subDays(6);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_DAYS) {
            $from = $to->subDays(self::MAX_DAYS - 1);
        }

        return [$from, $to, ['limit' => $limit]];
    }
}

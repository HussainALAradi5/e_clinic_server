<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Interfaces\Accounting\MonthSummaryServiceInterface;
use App\Interfaces\Identity\NotificationServiceInterface;
use App\Models\Identity\Person;
use App\Utilities\Shared\MoneyHelper;
use App\Utilities\Shared\Recipients;
use Carbon\CarbonImmutable;

/**
 * On the 1st: tells everyone who reads the financial reports how last month went (revenue, result, collected), with
 * a link to the full summary.
 */
final readonly class SendMonthSummary
{
    public function __construct(
        private MonthSummaryServiceInterface $summaries,
        private NotificationServiceInterface $notifications,
    ) {}

    public function __invoke(): int
    {
        $month = CarbonImmutable::now()->subMonthNoOverflow();
        $now = $this->summaries->summary($month->year, $month->month)['current'];
        $data = [
            'month' => $month->format('F Y'),
            'revenue' => MoneyHelper::format(MoneyHelper::of($now['revenue']['amount'])),
            'result' => MoneyHelper::format(MoneyHelper::of($now['net_result']['amount'])),
            'collected' => MoneyHelper::format(MoneyHelper::of($now['collected']['amount'])),
            'visits' => (string) $now['visits_completed'],
        ];
        $people = Recipients::withPermission('reports.read_all');
        $people->each(fn (Person $p) => rescue(fn () => $this->notifications->send('report.month_summary', $p, null, $data), report: true));

        return $people->count();
    }
}

<?php

namespace App\Domain\Exports;

use App\Domain\CRM\Models\Client;
use App\Domain\Finance\Models\Payout;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Support\TenantContext;
use App\Support\Analytics\ClientAnalytics;

/**
 * مولّد التقارير — نوع تقرير + مرشّحات → TabularData. مصدر واحد يخدم التصدير عند
 * الطلب والتقارير المجدولة، فيتطابقان.
 */
class ReportGenerator
{
    public const TYPES = [
        'clients_report' => 'تقرير أداء العملاء',
        'payouts' => 'تقرير المستحقات',
    ];

    public function generate(string $type, array $filters, int $tenantId): TabularData
    {
        return TenantContext::withTenant($tenantId, function () use ($type, $filters, $tenantId) {
            $ws = Organization::find(TenantContext::organizationId())?->name;
            $stamp = now()->format('Y-m-d H:i');

            return match ($type) {
                'payouts' => $this->payouts($ws, $stamp),
                default => $this->clientsReport($ws, $stamp),
            };
        });
    }

    private function clientsReport(?string $ws, string $stamp): TabularData
    {
        $clients = Client::query()->get();
        $metrics = ClientAnalytics::forPage($clients);
        $sar = fn (int $minor) => number_format($minor / 100, 0) . ' ر.س';
        $rows = $clients->map(fn (Client $c) => [
            'name' => $c->display_name,
            'active' => (int) ($metrics[$c->id]['active_campaigns'] ?? 0),
            'completion' => (int) ($metrics[$c->id]['completion'] ?? 0) . '%',
            'revenue' => $sar((int) ($metrics[$c->id]['revenue_minor'] ?? 0)),
        ])->sortByDesc(fn ($x) => $x['active'])->values();

        return new TabularData(
            title: 'تقرير أداء العملاء',
            columns: ['name' => 'العميل', 'active' => 'حملات نشطة', 'completion' => 'اكتمال الملف', 'revenue' => 'الإيراد (مُحصَّل)'],
            rows: $rows, meta: ['الفترة' => now()->format('Y')], workspace: $ws, generatedAt: $stamp,
        );
    }

    private function payouts(?string $ws, string $stamp): TabularData
    {
        $rows = Payout::with('creator')->latest()->get()->map(fn (Payout $p) => [
            'number' => $p->payout_number, 'creator' => $p->creator?->display_name ?? '—',
            'amount' => number_format(($p->amount_minor ?? 0) / 100, 2) . ' ' . ($p->currency ?: 'SAR'),
            'status' => __("statuses.{$p->status}"), 'due' => $p->due_date?->format('Y-m-d') ?? '—',
        ]);

        return new TabularData(
            title: 'تقرير المستحقات',
            columns: ['number' => 'الرقم', 'creator' => 'المبدع', 'amount' => 'المبلغ', 'status' => 'الحالة', 'due' => 'الاستحقاق'],
            rows: $rows, workspace: $ws, generatedAt: $stamp,
        );
    }
}

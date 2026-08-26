<?php

namespace App\Domain\Finance\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Communications\Services\NotificationService;
use App\Domain\Finance\Models\Invoice;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * متابعة الفواتير المتأخّرة — تذكير حقيقيّ مبنيّ على due_date الفعليّة (لا موعد مُختلَق).
 *
 * يُشعِر مرّة واحدة عند تجاوز الفاتورة المُصدَرة موعد استحقاقها ولم تُحصَّل بعد، ويحفظ
 * overdue_notified_at لضمان عدم التكرار (لا بريد ثانٍ لنفس الفاتورة). المستقبِلون:
 * مُصدِر الفاتورة إن وُجد + مديرو الوكالة/العمليّات/المالية (نمط SLA نفسه). يعمل مجدولًا.
 *
 * لا يخترع تصعيدًا ولا مالكًا: التصعيد متعدّد المستويات يتطلّب هرميّة غير مُخزَّنة — يُترَك
 * للمستقبل. هنا تذكير أوّليّ صادق وآمن من التكرار فقط.
 */
final class InvoiceReminderService
{
    public function __construct(private NotificationService $notifications) {}

    /** يمسح كل المستأجرين (تجاوز النطاق للقراءة الإداريّة) ويُشعِر بالمتأخّر الجديد. */
    public function scan(): array
    {
        return TenantContext::withBypass(function () {
            $overdue = Invoice::query()
                ->whereIn('status', ['issued', 'partially_paid']) // مُصدَرة وغير مدفوعة بالكامل
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereNull('overdue_notified_at')               // لم يُشعَر بعد (منع التكرار)
                ->with('client')
                ->get();

            $notified = 0;
            foreach ($overdue as $inv) {
                $inv->update(['overdue_notified_at' => now()]);  // العلامة أوّلًا: إعادة التشغيل آمنة
                if ($this->notifyOwners($inv)) {
                    $notified++;
                }
            }

            return ['scanned' => $overdue->count(), 'notified' => $notified];
        });
    }

    /** يُشعِر مُصدِر الفاتورة + مديري الوكالة/العمليّات/المالية. يُرجع true إن أُرسل لأحد. */
    private function notifyOwners(Invoice $inv): bool
    {
        $recipients = [];
        if ($inv->created_by) {
            $recipients[] = (int) $inv->created_by;
        }
        $admins = OrganizationMembership::withoutGlobalScopes()
            ->where('tenant_id', $inv->tenant_id)
            ->where('status', 'active')
            ->whereIn('role', ['agency_admin', 'operations_manager', 'finance'])
            ->pluck('user_id')->all();
        $recipients = array_values(array_unique(array_merge($recipients, array_map('intval', $admins))));

        if (empty($recipients)) {
            return false;
        }

        $clientName = $inv->client?->display_name;
        $objects = [['type' => 'invoice', 'name' => $inv->invoice_number]];
        if ($clientName) {
            $objects[] = ['type' => 'client', 'name' => $clientName];
        }

        $this->notifications->notifyMany(
            $inv->tenant_id,
            $recipients,
            'invoice.overdue',
            'general',
            "فاتورة متأخّرة: {$inv->invoice_number}",
            'تجاوزت الفاتورة موعد استحقاقها ولم تُحصَّل بعد. تابع التحصيل مع العميل.',
            "/app/invoices/{$inv->id}",
            [
                'objects' => $objects,
                'status' => 'متأخّرة',
                'due' => $inv->due_date?->format('Y-m-d'),
                'priority' => 'high',
                'cta_label' => 'عرض الفاتورة',
                'invoice_id' => $inv->id,
            ],
            $inv,
        );

        AuditLogger::log('invoice.overdue_notified', $inv, ['due_date' => $inv->due_date?->format('Y-m-d')], $inv->tenant_id, null);

        return true;
    }
}

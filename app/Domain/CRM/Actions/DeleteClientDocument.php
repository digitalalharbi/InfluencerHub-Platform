<?php
namespace App\Domain\CRM\Actions;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\CRM\Models\ClientDocument;
use App\Domain\Identity\Models\User;
class DeleteClientDocument {
    /** حذف ناعم للسجل؛ الملف الفعلي يبقى للتدقيق حتى تنظيف مجدول (لا حذف نهائي فوري). */
    public function handle(ClientDocument $doc, User $actor): void {
        AuditLogger::log('client_document.deleted', $doc, ['path' => $doc->path], $doc->tenant_id, $actor->id);
        $doc->delete();
    }
}

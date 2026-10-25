<?php

namespace App\Domain\Integrations\Google\Services;

use App\Domain\Integrations\Google\GoogleClient;
use App\Domain\Integrations\Google\GoogleScopes;
use App\Domain\Integrations\Models\ExternalObjectMap;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Tenancy\Support\TenantContext;

/**
 * Google Drive (GOOGLE-01): سرد/بيانات وصفيّة/رفع/تنزيل/أذونات، وربط مستند بحملة أو مبدع
 * عبر external_object_map (لا تكرار، تتبُّع المصدر). كل عملية تتحقّق من منح القدرة أولًا
 * وتستعمل رمزًا صالحًا. لا تخزّن محتوى الملفّات محليًّا — مجرّد روابط وبيانات وصفيّة.
 */
class GoogleDriveService
{
    public function __construct(
        private GoogleClient $client,
        private GoogleConnectionService $connections,
    ) {}

    /** @return array{files:array<int,array<string,mixed>>,nextPageToken:?string} */
    public function listFiles(IntegrationConnection $conn, ?string $folderId = null, ?string $query = null, ?string $pageToken = null): array
    {
        $conn = $this->ready($conn);
        $res = $this->client->driveListFiles($conn->access_token, $folderId, $query, $pageToken);

        return ['files' => $res['files'] ?? [], 'nextPageToken' => $res['nextPageToken'] ?? null];
    }

    /** @return array<string,mixed> */
    public function metadata(IntegrationConnection $conn, string $fileId): array
    {
        $conn = $this->ready($conn);

        return $this->client->driveFileMetadata($conn->access_token, $fileId);
    }

    /** @return array<string,mixed> */
    public function upload(IntegrationConnection $conn, string $name, string $mimeType, string $contents, ?string $folderId = null): array
    {
        $conn = $this->ready($conn);

        return $this->client->driveUploadFile($conn->access_token, $name, $mimeType, $contents, $folderId);
    }

    public function download(IntegrationConnection $conn, string $fileId): string
    {
        $conn = $this->ready($conn);

        return $this->client->driveDownloadFile($conn->access_token, $fileId);
    }

    /** @return array<int,array<string,mixed>> */
    public function permissions(IntegrationConnection $conn, string $fileId): array
    {
        $conn = $this->ready($conn);

        return $this->client->driveListPermissions($conn->access_token, $fileId);
    }

    /** @return array<string,mixed> */
    public function share(IntegrationConnection $conn, string $fileId, string $emailAddress, string $role = 'reader'): array
    {
        $conn = $this->ready($conn);

        return $this->client->driveCreatePermission($conn->access_token, $fileId, $role, 'user', $emailAddress);
    }

    /**
     * يربط مستند Drive بسجلّ محلّي (حملة/مبدع/عميل) دون تكرار الرابط. local_type هو وسم
     * منطقيّ ثابت ('campaign'|'creator'|'client'). يُحدَّث الربط إن تكرّر نفس الملفّ.
     */
    public function linkTo(IntegrationConnection $conn, string $fileId, string $localType, int $localId): ExternalObjectMap
    {
        return TenantContext::withTenant($conn->tenant_id, fn () => ExternalObjectMap::updateOrCreate(
            ['tenant_id' => $conn->tenant_id, 'provider' => 'google', 'external_type' => 'drive_file', 'external_id' => $fileId],
            ['local_type' => $localType, 'local_id' => $localId, 'synced_at' => now()],
        ));
    }

    /** @return array<int,ExternalObjectMap> المستندات المربوطة بسجلّ محلّي. */
    public function linkedTo(IntegrationConnection $conn, string $localType, int $localId): array
    {
        return TenantContext::withTenant($conn->tenant_id, fn () => ExternalObjectMap::query()
            ->where('tenant_id', $conn->tenant_id)->where('provider', 'google')->where('external_type', 'drive_file')
            ->where('local_type', $localType)->where('local_id', $localId)->get()->all());
    }

    private function ready(IntegrationConnection $conn): IntegrationConnection
    {
        $conn = $this->connections->ensureFresh($conn);
        $this->connections->assertCapability($conn, GoogleScopes::CAP_DRIVE);

        return $conn;
    }
}

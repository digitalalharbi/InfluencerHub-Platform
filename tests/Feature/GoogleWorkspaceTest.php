<?php

namespace Tests\Feature;

use App\Domain\Creators\Models\Creator;
use App\Domain\Creators\Services\CreatorTimeline;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Adapters\AdapterRegistry;
use App\Domain\Integrations\Google\Fakes\FakeGoogleClient;
use App\Domain\Integrations\Google\GoogleNotConfiguredException;
use App\Domain\Integrations\Google\GoogleScopes;
use App\Domain\Integrations\Google\GoogleWorkspaceAdapter;
use App\Domain\Integrations\Google\Services\GmailService;
use App\Domain\Integrations\Google\Services\GoogleCalendarService;
use App\Domain\Integrations\Google\Services\GoogleConnectionService;
use App\Domain\Integrations\Google\Services\GoogleDriveService;
use App\Domain\Integrations\Google\Services\GoogleSheetsExporter;
use App\Domain\Integrations\Google\Services\GoogleSheetsImporter;
use App\Domain\Integrations\Models\IntegrationConnection;
use App\Domain\Integrations\Models\IntegrationExternalEvent;
use App\Domain\Integrations\Services\OAuthFlowService;
use App\Domain\Tenancy\Models\Organization;
use App\Domain\Tenancy\Models\OrganizationMembership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * GOOGLE-01 — مساحة عمل Google على الأساس القائم. مُختبَرة بـFakeGoogleClient حتميًّا، بلا
 * بيانات اعتماد ولا شبكة: دورة OAuth بنطاقات تدريجية حقيقية، Drive/Sheets/Gmail/Calendar،
 * بوّابة القدرات، وتغذية الخطّ الزمني بأحداث حقيقيّة. لا ادّعاء حيّ.
 */
class GoogleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private FakeGoogleClient $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new FakeGoogleClient;
        config(['services.google.client_id' => 'test-client-id.apps.googleusercontent.com',
            'services.google.redirect' => 'https://app.test/integrations/google/callback']);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private const REDIRECT = 'https://app.test/integrations/google/callback';

    private function adapter(): GoogleWorkspaceAdapter
    {
        return new GoogleWorkspaceAdapter($this->fake, 'test-client-id.apps.googleusercontent.com', self::REDIRECT);
    }

    private function oauth(): OAuthFlowService
    {
        return new OAuthFlowService(new AdapterRegistry([$this->adapter()]));
    }

    private function connections(): GoogleConnectionService
    {
        return new GoogleConnectionService($this->oauth());
    }

    /** @return array{0:Tenant,1:User,2:Organization} */
    private function tenantOrgUser(): array
    {
        $t = Tenant::create(['name' => 't', 'slug' => Str::random(8), 'deployment_mode' => 'saas', 'status' => 'active']);
        TenantContext::bypass(true);
        $org = Organization::create(['tenant_id' => $t->id, 'name' => 'و', 'slug' => Str::random(8), 'type' => 'agency', 'status' => 'active']);
        $u = User::create(['name' => 'مدير', 'email' => Str::random(6).'@ex.com', 'password' => bcrypt('x'), 'is_active' => true]);
        OrganizationMembership::create(['tenant_id' => $t->id, 'organization_id' => $org->id, 'user_id' => $u->id, 'role' => 'agency_admin', 'status' => 'active']);
        TenantContext::reset();

        return [$t, $u, $org];
    }

    /** @param array<int,string> $caps */
    private function connect(Tenant $t, array $caps): IntegrationConnection
    {
        $begin = $this->oauth()->begin($t->id, 'google', self::REDIRECT, $caps);

        return $this->oauth()->complete($begin['state'], 'authcode-x');
    }

    // ——— OAuth + incremental scopes ———

    public function test_begin_builds_google_authorize_url_with_incremental_scopes_and_pkce(): void
    {
        [$t] = $this->tenantOrgUser();
        $out = $this->oauth()->begin($t->id, 'google', self::REDIRECT, [GoogleScopes::CAP_DRIVE]);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $out['url']);
        $this->assertStringContainsString('code_challenge_method=S256', $out['url']);
        $this->assertStringContainsString('access_type=offline', $out['url']);
        $this->assertStringContainsString('prompt=consent', $out['url']);
        // نطاق Drive فقط (موافقة تدريجية) — لا نطاق Gmail حين لم تُطلب قدرته
        $this->assertStringContainsString(rawurlencode('https://www.googleapis.com/auth/drive.file'), $out['url']);
        $this->assertStringNotContainsString('gmail', $out['url']);
    }

    public function test_complete_stores_connection_with_requested_capabilities_and_account(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_DRIVE, GoogleScopes::CAP_GMAIL]);

        $this->assertSame('connected', $conn->status);
        $this->assertSame('google', $conn->provider);
        $this->assertSame($this->fake->connectedSub, $conn->external_account_id);
        $this->assertSame($this->fake->connectedEmail, $conn->external_account_name);
        $this->assertSame('connected', $conn->capabilities['google_drive']);
        $this->assertSame('connected', $conn->capabilities['gmail']);
        $this->assertArrayNotHasKey('google_calendar', $conn->capabilities ?? []);
        // الرمز مُخزَّن مشفَّرًا، لا نصًّا صريحًا
        $raw = \DB::table('integration_connections')->where('id', $conn->id)->value('access_token');
        $this->assertNotSame($conn->access_token, $raw);
    }

    public function test_adapter_without_client_id_refuses_to_build_authorize_url(): void
    {
        config(['services.google.client_id' => null]);
        $adapter = new GoogleWorkspaceAdapter($this->fake, null, null);
        $this->expectException(GoogleNotConfiguredException::class);
        $adapter->authorizeUrl('state', 'challenge', self::REDIRECT);
    }

    // ——— Capability gate ———

    public function test_capability_gate_blocks_ungranted_capability(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_DRIVE]); // لا Gmail

        $gmail = new GmailService($this->fake, $this->connections());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('google_capability_not_granted:gmail');
        $gmail->listThreads($conn);
    }

    // ——— Drive ———

    public function test_drive_upload_list_download_share_and_link(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_DRIVE]);
        $drive = new GoogleDriveService($this->fake, $this->connections());

        $up = $drive->upload($conn, 'brief.txt', 'text/plain', 'الموجز');
        $this->assertNotEmpty($up['id']);
        $this->assertSame('الموجز', $drive->download($conn, $up['id']));
        $this->assertCount(1, $drive->listFiles($conn)['files']);

        $drive->share($conn, $up['id'], 'talent@ex.test', 'reader');
        $this->assertSame('talent@ex.test', $drive->permissions($conn, $up['id'])[0]['emailAddress']);

        $creator = TenantContext::withTenant($t->id, fn () => Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer', 'display_name' => 'م', 'status' => 'active']));
        $drive->linkTo($conn, $up['id'], 'creator', $creator->id);
        $this->assertDatabaseHas('external_object_map', ['provider' => 'google', 'external_type' => 'drive_file', 'external_id' => $up['id'], 'local_type' => 'creator', 'local_id' => $creator->id]);
        $this->assertCount(1, $drive->linkedTo($conn, 'creator', $creator->id));
    }

    // ——— Sheets import ———

    public function test_sheets_suggest_mapping_matches_arabic_and_english_headers(): void
    {
        [$t] = $this->tenantOrgUser();
        $importer = app(GoogleSheetsImporter::class);
        $map = $importer->suggestMapping(['الاسم', 'Email', 'المتابعين', 'منصة', 'عمود مجهول']);

        $this->assertSame('display_name', $map[0]);
        $this->assertSame('email', $map[1]);
        $this->assertSame('followers_count', $map[2]);
        $this->assertSame('primary_platform', $map[3]);
        $this->assertNull($map[4]);
    }

    public function test_sheets_preview_classifies_new_duplicate_conflict_invalid(): void
    {
        [$t] = $this->tenantOrgUser();
        TenantContext::withTenant($t->id, fn () => Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id.'-1', 'type' => 'influencer', 'display_name' => 'سارة', 'email' => 'sara@ex.test', 'followers_count' => 1000, 'status' => 'active']));

        $importer = app(GoogleSheetsImporter::class);
        $headers = ['الاسم', 'Email', 'المتابعين'];
        $mapping = $importer->suggestMapping($headers);
        $rows = [
            ['سارة', 'sara@ex.test', '1000'],   // duplicate (identical)
            ['سارة م', 'sara@ex.test', '2000'],  // conflict (same email, different name/followers)
            ['نورة', 'noura@ex.test', '500'],    // new
            ['', 'bad', 'x'],                    // invalid (no name + bad email)
        ];
        $prev = $importer->preview($t->id, $headers, $rows, $mapping);

        $this->assertSame(['total' => 4, 'new' => 1, 'duplicate' => 1, 'conflict' => 1, 'invalid' => 1], $prev['summary']);
        $conflict = collect($prev['rows'])->firstWhere('status', 'conflict');
        $this->assertArrayHasKey('followers_count', $conflict['diff']);
    }

    public function test_sheets_commit_creates_updates_and_dedups_within_batch(): void
    {
        [$t, $u] = $this->tenantOrgUser();
        $importer = app(GoogleSheetsImporter::class);
        $headers = ['الاسم', 'Email', 'المتابعين'];
        $mapping = $importer->suggestMapping($headers);

        $rows = [
            ['نورة', 'noura@ex.test', '500'],
            ['نورة', 'noura@ex.test', '500'],   // تكرار داخل نفس الدفعة ⇒ يُتخطّى
            ['ليان', 'layan@ex.test', '900'],
        ];
        $res = $importer->commit($t->id, $headers, $rows, $mapping, 'skip', $u);
        $this->assertSame(2, $res['created']);
        $this->assertSame(1, $res['skipped']);
        $this->assertSame(2, TenantContext::withTenant($t->id, fn () => Creator::where('tenant_id', $t->id)->count()));

        // تحديث عند التعارض
        $update = $importer->commit($t->id, $headers, [['نورة ع', 'noura@ex.test', '650']], $mapping, 'update', $u);
        $this->assertSame(1, $update['updated']);
        $noura = TenantContext::withTenant($t->id, fn () => Creator::where('email', 'noura@ex.test')->first());
        $this->assertSame(650, (int) $noura->followers_count);
    }

    public function test_sheets_export_creates_spreadsheet_with_rows(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_SHEETS]);
        $exporter = new GoogleSheetsExporter($this->fake, $this->connections());

        $out = $exporter->export($conn, 'القائمة المختصرة', ['الاسم', 'المتابعين'], [['سارة', 1000], ['نورة', 500]]);
        $this->assertNotEmpty($out['spreadsheetId']);
        $this->assertSame(2, $out['rows']);
        $this->assertCount(3, $this->fake->sheets[$out['spreadsheetId']]['Sheet1']); // ترويسة + صفّان
    }

    // ——— Gmail ———

    public function test_gmail_send_records_outbound_timeline_event_matched_to_creator(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_GMAIL]);
        $creator = TenantContext::withTenant($t->id, fn () => Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer', 'display_name' => 'طلال', 'email' => 'talent@ex.test', 'status' => 'active']));

        $gmail = new GmailService($this->fake, $this->connections());
        $sent = $gmail->send($conn, 'talent@ex.test', 'تعاون', 'مرحبًا، لدينا حملة');
        $this->assertNotEmpty($sent['id']);
        $this->assertCount(1, $this->fake->sentMessages);

        $ev = TenantContext::withTenant($t->id, fn () => IntegrationExternalEvent::where('external_id', $sent['id'])->first());
        $this->assertSame('email_out', $ev->kind);
        $this->assertSame('outbound', $ev->direction);
        $this->assertSame($creator->id, $ev->creator_id);
    }

    public function test_gmail_match_contact_resolves_creator_by_email(): void
    {
        [$t] = $this->tenantOrgUser();
        $this->connect($t, [GoogleScopes::CAP_GMAIL]);
        $creator = TenantContext::withTenant($t->id, fn () => Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer', 'display_name' => 'ر', 'email' => 'match@ex.test', 'status' => 'active']));

        $gmail = new GmailService($this->fake, $this->connections());
        $this->assertSame($creator->id, $gmail->matchContact($t->id, 'MATCH@ex.test')['creator_id']);
        $this->assertNull($gmail->matchContact($t->id, 'nobody@ex.test')['creator_id']);
    }

    // ——— Calendar ———

    public function test_calendar_create_typed_event_feeds_creator_timeline(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_CALENDAR]);
        $creator = TenantContext::withTenant($t->id, fn () => Creator::create(['tenant_id' => $t->id, 'creator_number' => 'CR-'.$t->id, 'type' => 'influencer', 'display_name' => 'تصوير', 'status' => 'active']));

        $cal = new GoogleCalendarService($this->fake, $this->connections());
        $event = $cal->createEvent($conn, 'primary', 'shoot', 'جلسة تصوير', now()->addDay()->toIso8601String(), now()->addDay()->addHours(2)->toIso8601String(), ['creator_id' => $creator->id]);
        $this->assertNotEmpty($event['id']);

        $ev = TenantContext::withTenant($t->id, fn () => IntegrationExternalEvent::where('external_id', $event['id'])->first());
        $this->assertSame('shoot', $ev->kind);
        $this->assertSame($creator->id, $ev->creator_id);

        $timeline = TenantContext::withTenant($t->id, fn () => CreatorTimeline::for($creator->fresh()));
        $this->assertTrue(collect($timeline)->contains(fn ($e) => $e['type'] === 'google_google_calendar'));
    }

    public function test_calendar_rejects_unknown_kind(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_CALENDAR]);
        $cal = new GoogleCalendarService($this->fake, $this->connections());
        $this->expectException(\InvalidArgumentException::class);
        $cal->createEvent($conn, 'primary', 'not_a_kind', 's', now()->toIso8601String(), now()->addHour()->toIso8601String());
    }

    public function test_calendar_delete_removes_timeline_row(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_CALENDAR]);
        $cal = new GoogleCalendarService($this->fake, $this->connections());
        $event = $cal->createEvent($conn, 'primary', 'meeting', 'لقاء', now()->toIso8601String(), now()->addHour()->toIso8601String());

        $this->assertTrue($cal->deleteEvent($conn, 'primary', $event['id']));
        $this->assertSame(0, TenantContext::withTenant($t->id, fn () => IntegrationExternalEvent::where('external_id', $event['id'])->count()));
    }

    public function test_calendar_sync_incremental_stores_sync_token(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_CALENDAR]);
        $this->fake->events['primary'] = [
            ['id' => 'evt-a', 'summary' => 'حدث', 'start' => ['dateTime' => now()->toIso8601String()], 'extendedProperties' => ['private' => ['ihub_kind' => 'briefing']]],
        ];
        $cal = new GoogleCalendarService($this->fake, $this->connections());
        $res = $cal->syncIncremental($conn);
        $this->assertSame(1, $res->fetched);
        $this->assertSame('synctoken-1', $conn->fresh()->meta['calendar_sync_token']);
    }

    // ——— HTTP connect/callback (reachable flow) ———

    private function bindFakeRegistry(): void
    {
        $this->app->singleton(AdapterRegistry::class, fn () => new AdapterRegistry([
            new GoogleWorkspaceAdapter($this->fake, 'test-client-id.apps.googleusercontent.com', self::REDIRECT),
        ]));
    }

    public function test_http_connect_redirects_to_google_then_callback_stores_connection(): void
    {
        [$t, $u] = $this->tenantOrgUser();
        $this->bindFakeRegistry();

        $res = $this->actingAs($u)->get('/app/integrations/google/connect?capabilities[]=google_drive');
        $res->assertRedirect();
        $loc = $res->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $loc);
        parse_str((string) parse_url($loc, PHP_URL_QUERY), $q);

        $this->actingAs($u)->get('/app/integrations/google/callback?code=authcode&state='.$q['state'])
            ->assertRedirect('/app/integrations');

        $this->assertDatabaseHas('integration_connections', ['tenant_id' => $t->id, 'provider' => 'google', 'status' => 'connected']);
    }

    public function test_http_connect_without_credentials_shows_honest_error(): void
    {
        [, $u] = $this->tenantOrgUser();
        config(['services.google.client_id' => null]);
        $this->app->singleton(AdapterRegistry::class, fn () => new AdapterRegistry([
            new GoogleWorkspaceAdapter($this->fake, null, null),
        ]));

        $this->actingAs($u)->get('/app/integrations/google/connect')
            ->assertRedirect('/app/integrations')
            ->assertSessionHasErrors('google');
        $this->assertDatabaseMissing('integration_connections', ['provider' => 'google']);
    }

    // ——— Readiness ladder on a real google connection ———

    public function test_connected_google_reports_auth_then_discovery_readiness(): void
    {
        [$t] = $this->tenantOrgUser();
        $conn = $this->connect($t, [GoogleScopes::CAP_DRIVE]);
        // الحساب مُكتشَف (external_account_id من userInfo) ⇒ RESOURCE_DISCOVERY_VERIFIED
        $this->assertSame('RESOURCE_DISCOVERY_VERIFIED', $conn->readiness());
    }
}

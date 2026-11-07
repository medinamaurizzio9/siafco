<?php

namespace Tests\Feature;

use App\Jobs\SendCommunicationPush;
use App\Models\Affiliate;
use App\Models\AffiliationPlan;
use App\Models\Communication;
use App\Models\Person;
use App\Models\Sector;
use App\Models\User;
use App\Services\CommunicationFcm;
use App\Services\Communications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunicationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadministrador', 'user_type' => 'internal', 'is_active' => true]);
    }

    private function affiliate(string $status = 'activo'): Affiliate
    {
        $sector = Sector::create(['name' => fake()->unique()->word(), 'code' => fake()->unique()->bothify('SEC-###'), 'is_active' => true]);
        $plan = AffiliationPlan::create(['name' => fake()->unique()->word(), 'affiliation_fee' => 100, 'credential_fee' => 20, 'is_active' => true]);
        $person = Person::create(['full_name' => fake()->name(), 'ci' => fake()->unique()->numerify('#######'), 'email' => fake()->unique()->safeEmail()]);
        $user = User::factory()->create(['person_id' => $person->id, 'role' => 'afiliado', 'user_type' => 'affiliate', 'is_active' => true]);

        return Affiliate::create(['person_id' => $person->id, 'user_id' => $user->id, 'email' => $person->email, 'sector_id' => $sector->id, 'affiliation_plan_id' => $plan->id, 'full_name' => $person->full_name, 'ci' => $person->ci, 'registration_number' => fake()->unique()->bothify('REG-#####'), 'verification_token' => fake()->uuid(), 'status' => $status]);
    }

    private function draft(array $fields = []): Communication
    {
        $this->post(route('communications.store'), array_merge(['title' => 'Asamblea', 'summary' => 'Convocatoria', 'body' => '<b>Acta</b><script>alert(1)</script>', 'priority' => 'important', 'requires_confirmation' => 1, 'all' => 1], $fields))->assertRedirect()->assertSessionHasNoErrors();

        return Communication::latest('id')->firstOrFail();
    }

    public function test_portal_dialog_uses_session_and_keeps_opening_separate_from_confirmation(): void
    {
        $affiliate = $this->affiliate();
        $other = $this->affiliate();
        $admin = $this->admin();
        $panel = route('affiliate.panel');
        $this->actingAs($affiliate->user)->get($panel)->assertOk()->assertDontSee('<dialog class="communication-dialog"', false);

        $publish = function (string $title, Affiliate $recipient, array $extra = []) use ($admin) {
            $this->actingAs($admin);
            $item = $this->draft(array_merge(['all'=>0, 'affiliate_ids'=>json_encode([$recipient->id]), 'title'=>$title,
                'summary'=>'Resumen breve de prueba', 'body'=>'Contenido reservado del detalle'], $extra));
            Communications::publish($item, $admin->id, 1, null);
            return $item;
        };
        for ($i = 1; $i <= 6; $i++) {
            $latest = $publish("Aviso $i", $affiliate);
        }
        $publish('Solo para otro afiliado', $other);
        $publish('Aviso vencido', $affiliate, ['expires_at'=>now()->addMinute()->toDateTimeString()]);
        $this->travel(2)->minutes();
        $response = $this->actingAs($affiliate->user)->get($panel)->assertOk()
            ->assertSee('Resumen breve de prueba')->assertSee('Mis comunicados, 6 sin leer')
            ->assertDontSee('Contenido reservado del detalle')->assertDontSee('Aviso vencido')->assertDontSee('Solo para otro afiliado');
        $html = new \DOMDocument;
        @$html->loadHTML($response->getContent());
        $xpath = new \DOMXPath($html);
        $this->assertSame(5, $xpath->query('//dialog//article')->length);
        $this->assertSame(3, $xpath->query('//details[@class="communication-bell"]//article')->length);
        $watermark = $xpath->evaluate('string(//dialog//input[@name="publication"]/@value)');
        $this->post(route('inbox.dismiss'), ['publication'=>$watermark])->assertRedirect($panel);
        $this->get($panel)->assertOk()->assertDontSee('<dialog class="communication-dialog"', false)->assertSee('Mis comunicados, 6 sin leer');
        $this->get(route('inbox.show', $latest))->assertOk()->assertSee('Mis comunicados, 5 sin leer');
        $this->assertDatabaseHas('communication_recipients', ['communication_id'=>$latest->id, 'confirmed_at'=>null]);
        $this->post(route('inbox.read', $latest))->assertRedirect();
        $this->assertNotNull(DB::table('communication_recipients')->where('communication_id',$latest->id)->value('confirmed_at'));
        $this->get($panel)->assertDontSee('<dialog class="communication-dialog"', false);
        $publish('Aviso posterior', $affiliate);
        $response = $this->actingAs($affiliate->user)->get($panel)->assertOk()->assertSee('<dialog class="communication-dialog"', false)->assertSee('Aviso posterior');
        $this->get(route('inbox.index'))->assertOk()->assertDontSee('<dialog class="communication-dialog"', false);
        $newWatermark = DB::table('communication_audit_logs')->where('event', 'published')->max('id');
        $this->post(route('inbox.dismiss'), ['publication'=>$newWatermark])->assertRedirect($panel);
        $this->get($panel)->assertDontSee('<dialog class="communication-dialog"', false);
        $this->app['session']->invalidate();
        $this->actingAs($affiliate->user)->get($panel)->assertOk()->assertSee('<dialog class="communication-dialog"', false);
    }

    public function test_draft_editor_publication_and_private_inbox(): void
    {
        Storage::fake('local');
        $a = $this->affiliate();
        $b = $this->affiliate();
        $other = $this->affiliate();
        $inactive = $this->affiliate('pendiente_pago');
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->get(route('communications.index'))->assertOk();
        $this->get(route('communications.create'))->assertOk()->assertSee('<title>Crear comunicado</title>', false);
        $this->postJson(route('communications.store'), [])->assertUnprocessable();
        $this->getJson(route('communications.candidates', ['q' => $a->ci]))->assertOk()->assertJsonPath('0.id', $a->id);
        $item = $this->draft(['all' => 0, 'sectors' => [$a->sector_id], 'affiliate_ids' => json_encode([$a->id, $b->id, $inactive->id]), 'cover' => UploadedFile::fake()->image('portada.jpg')]);
        $this->get(route('communications.edit', $item))->assertOk()
            ->assertSee('<title>Editar comunicado</title>', false)->assertSee('Asamblea');
        $this->postJson(route('communications.audience-count'), [
            'all' => false, 'sectors' => [$a->sector_id], 'affiliates' => [$a->id, $b->id, $inactive->id],
        ])->assertOk()->assertJsonPath('count', 2);
        $this->postJson(route('communications.audience-count'), [
            'all' => true, 'sectors' => [], 'affiliates' => [],
        ])->assertOk()->assertJsonPath('count', 3);
        $this->assertDatabaseCount('communication_recipients', 0);
        $this->assertStringNotContainsString('<script>', $item->body);
        $this->get(route('communications.preview', $item))->assertOk()->assertSee('2 destinatarios');
        $this->postJson(route('communications.publish', $item), ['expected' => 3])->assertUnprocessable();
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('communications.publish', $item), ['expected' => 2])->assertRedirect();
        }
        $this->assertDatabaseCount('communication_recipients', 2);
        $this->get(route('communications.report', $item))->assertOk();
        $this->get(route('communications.edit', $item))->assertStatus(409);
        $file = DB::table('communication_attachments')->value('id');
        $base = '/api/mobile/v1/communications';
        Sanctum::actingAs($other->user);
        $this->getJson("$base/$item->id")->assertNotFound();
        $this->getJson("$base/$item->id/attachments/$file")->assertNotFound();
        $this->postJson("$base/$item->id/read")->assertNotFound();
        Sanctum::actingAs($a->user);
        $this->getJson("$base/unread-count")->assertJsonPath('data.unread_count', 1);
        $this->getJson($base)->assertOk()->assertJsonPath('data.data.0.id', $item->id);
        $this->getJson("$base/$item->id")->assertOk();
        $this->getJson("$base/unread-count")->assertJsonPath('data.unread_count', 0);
        $this->postJson("$base/$item->id/read")->assertOk();
        $original = DB::table('communication_recipients')->where('affiliate_id', $a->id)->first();
        $this->travel(1)->minutes();
        $this->postJson("$base/$item->id/read")->assertOk();
        $this->assertDatabaseHas('communication_recipients', ['id' => $original->id, 'opened_at' => $original->opened_at, 'confirmed_at' => $original->confirmed_at]);
        $this->actingAs($a->user, 'web');
        $this->get(route('inbox.index'))->assertOk()->assertSee('Asamblea');
        $this->get(route('inbox.show', $item))->assertOk();
        $this->get(route('inbox.file', [$item, $file]))->assertOk();
        $this->get(route('communications.report', $item))->assertForbidden();
    }

    public function test_invalid_edit_restores_entered_text_and_cleared_audience(): void
    {
        $affiliate = $this->affiliate();
        $this->actingAs($this->admin());
        $item = $this->draft(['sectors' => [$affiliate->sector_id]]);
        $edit = route('communications.edit', $item);
        $this->from($edit)->put(route('communications.update', $item), [
            'title' => 'Título corregido', 'summary' => '', 'body' => 'Texto revisado',
            'priority' => 'urgent', 'affiliate_ids' => '[]',
        ])->assertRedirect($edit)->assertSessionHasErrors('summary');

        $response = $this->get($edit)->assertOk()->assertSee('Título corregido')->assertSee('Texto revisado');
        $html = new \DOMDocument;
        @$html->loadHTML($response->getContent());
        $xpath = new \DOMXPath($html);
        $this->assertSame(0, $xpath->query('//input[@checked and (@name="all" or @name="sectors[]" or @name="requires_confirmation")]')->length);
        $this->assertSame(1, $xpath->query('//textarea[@name="summary" and @aria-invalid="true"]')->length);
    }

    public function test_filters_authorization_files_and_archive(): void
    {
        $api = '/api/mobile/v1/communications';
        $this->getJson($api)->assertUnauthorized()->assertJsonPath('success', false);
        $a = $this->affiliate();
        $b = $this->affiliate();
        $this->assertSame([$a->id], Communications::audience(['sectors' => [$a->sector_id]])->pluck('id')->all());
        $this->assertSame([$b->id], Communications::audience(['affiliates' => [$b->id]])->pluck('id')->all());
        $admin = $this->admin();
        $this->actingAs($admin);
        $item = $this->draft();
        $payload = ['title' => 'Cambio', 'summary' => 'Resumen', 'priority' => 'urgent', 'body' => '<img src=x>'];
        $this->putJson(route('communications.update', $item), $payload + ['files' => [UploadedFile::fake()->create('script.php', 2)]])->assertUnprocessable();
        $this->put(route('communications.update', $item), $payload + ['all' => 1])->assertRedirect();
        $this->assertDatabaseHas('communication_audit_logs', ['event' => 'edited', 'user_id' => $admin->id]);
        $this->actingAs(User::factory()->create(['role' => 'consulta', 'user_type' => 'internal', 'is_active' => true]));
        $this->post(route('communications.publish', $item), ['expected' => 2])->assertForbidden();
        $this->get(route('communications.report', $item))->assertForbidden();
        $this->actingAs($admin);
        $this->post(route('communications.publish', $item), ['expected' => 2])->assertRedirect();
        $this->putJson(route('communications.update', $item), $payload)->assertConflict();
        $this->post(route('communications.archive', $item))->assertRedirect();
        $this->assertDatabaseHas('communication_audit_logs', ['event' => 'archived', 'user_id' => $admin->id]);
        Sanctum::actingAs($a->user);
        $this->getJson("$api/$item->id")->assertNotFound()->assertJsonPath('success', false);
        $this->getJson("$api?filter=invalid")->assertUnprocessable()->assertJsonPath('success', false);
        Sanctum::actingAs($this->affiliate('pendiente_pago')->user);
        $this->getJson($api)->assertForbidden();
    }

    public function test_fcm_http_request_contains_only_public_notification_fields(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'communication-fcm');
        try {
            file_put_contents($path, json_encode(['project_id' => 'test-project', 'private_key_id' => 'test-key']));
            config(['communication_push.credentials' => $path]);
            Cache::put('communication-oauth-'.hash('sha256', 'test-key'), 'test-access', 60);
            Http::preventStrayRequests();
            $url = 'https://fcm.googleapis.com/v1/projects/test-project/messages:send';
            Http::fake([$url => Http::response(['name' => 'test-message'])]);
            $item = (object) ['id' => 12, 'title' => 'Aviso', 'summary' => 'Resumen', 'body' => 'privado'];
            $this->assertTrue((new CommunicationFcm)->send('test-device', $item)->successful());
            Http::assertSent(fn ($request) => $request->url() === $url && $request->hasHeader('Authorization', 'Bearer test-access')
                && $request['message'] === ['token' => 'test-device', 'data' => ['type' => 'communication', 'communication_id' => '12', 'title' => 'Aviso', 'summary' => 'Resumen']]);
        } finally {
            unlink($path);
        }
    }

    public function test_schedule_devices_retries_and_revocation(): void
    {
        $a = $this->affiliate();
        $admin = $this->admin();
        $this->actingAs($admin);
        $item = $this->draft();
        Communications::publish($item, $admin->id, 1, now()->addHour()->toDateTimeString());
        $this->assertDatabaseCount('communication_push_deliveries', 0);
        Sanctum::actingAs($a->user);
        $this->getJson('/api/mobile/v1/communications')->assertJsonPath('data.total', 0);
        $device = ['token' => 'demo', 'device_id' => fake()->uuid(), 'enabled' => true];
        $url = '/api/mobile/v1/push-devices';
        $this->postJson($url, $device)->assertOk();
        $this->postJson($url, $device)->assertOk();
        $this->assertDatabaseCount('affiliate_push_devices', 1);
        $this->assertDatabaseMissing('affiliate_push_devices', ['token' => 'demo']);
        $this->travel(61)->minutes();
        Queue::fake();
        $this->artisan('communications:process')->assertSuccessful();
        $this->artisan('communications:process')->assertSuccessful();
        $this->assertDatabaseCount('communication_push_deliveries', 1);
        $this->assertDatabaseHas('communications', ['id' => $item->id, 'status' => 'published']);
        $id = DB::table('communication_push_deliveries')->value('id');
        $job = new SendCommunicationPush($id);
        Http::fakeSequence()->push([], 503)->push(['name' => 'ok']);
        $fcm = new class extends CommunicationFcm
        {
            public function send(string $token, object $item)
            {
                return Http::post('https://example.test/send');
            }
        };
        $job->handle($fcm);
        $this->assertDatabaseHas('communication_push_deliveries', ['id' => $id, 'status' => 'pending', 'attempts' => 1]);
        $this->travel(2)->minutes();
        $job->handle($fcm);
        $job->handle($fcm);
        $this->assertDatabaseHas('communication_push_deliveries',['id' => $id, 'status' => 'sent', 'attempts' => 2]);
        $this->deleteJson($url.'/'.$device['device_id'])->assertOk();
        $this->assertDatabaseCount('affiliate_push_devices',0);
    }
}

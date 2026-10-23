<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    private const ERROR_PAGE = '&quot;component&quot;:&quot;Error&quot;';

    public function test_an_unknown_page_shows_the_friendly_404_page(): void
    {
        $this->get('/esta-pagina-no-existe')
            ->assertStatus(404)
            ->assertSee(self::ERROR_PAGE, false)
            ->assertSee('&quot;status&quot;:404', false);
    }

    public function test_the_old_html_urls_also_show_the_friendly_404_page(): void
    {
        $this->get('/politicas-privacidad.html')->assertStatus(404)->assertSee(self::ERROR_PAGE, false);
    }

    public function test_the_friendly_404_also_shows_with_debug_mode_on(): void
    {
        config(['app.debug' => true]);

        $this->get('/kks')->assertStatus(404)->assertSee(self::ERROR_PAGE, false);
    }

    public function test_a_server_error_is_friendly_in_production_and_technical_in_debug(): void
    {
        Route::get('/_boom', fn () => throw new \RuntimeException('fallo interno secreto'));

        config(['app.debug' => false]);
        $this->get('/_boom')->assertStatus(500)
            ->assertSee(self::ERROR_PAGE, false)
            ->assertSee('&quot;status&quot;:500', false)
            ->assertDontSee('fallo interno secreto');

        config(['app.debug' => true]);
        $this->get('/_boom')->assertStatus(500)->assertDontSee(self::ERROR_PAGE, false);
    }

    public function test_json_requests_keep_returning_json_errors(): void
    {
        $this->getJson('/esta-pagina-no-existe')->assertStatus(404)->assertJsonStructure(['message']);
    }

    public function test_a_forbidden_page_shows_the_friendly_403_page(): void
    {
        $customer = $this->userWithRole('Customer');

        $this->actingAs($customer)->get('/loans')
            ->assertStatus(403)
            ->assertSee(self::ERROR_PAGE, false)
            ->assertSee('&quot;status&quot;:403', false);
    }

    public function test_the_privacy_notice_is_public_and_does_not_leak_secrets(): void
    {
        config(['promas.recaptcha.secret' => 'secreto-que-no-debe-salir', 'promas.legal_name' => 'Promas Servicios SA de CV']);

        $this->get('/aviso-de-privacidad')
            ->assertOk()
            ->assertSee('&quot;component&quot;:&quot;PrivacyNotice&quot;', false)
            ->assertSee('Promas Servicios SA de CV')
            ->assertDontSee('secreto-que-no-debe-salir');
    }

    public function test_the_public_pages_load(): void
    {
        foreach (['/', '/quienes-somos', '/prestamo-personal', '/prestamo-garantia', '/contacto', '/prestamo-personal/solicitar'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsLoans;
use Tests\TestCase;

/**
 * Auditoría de rutas: si alguien agrega una ruta nueva, esta prueba obliga a decidir
 * si es pública (y entonces se anota aquí) o si debe quedar protegida.
 */
class RouteProtectionTest extends TestCase
{
    use BuildsLoans, RefreshDatabase;

    /** Rutas públicas por diseño: sitio, solicitud de crédito, simulador, contacto, autenticación y webhook. */
    private const PUBLIC = [
        '/', 'quienes-somos', 'prestamo-personal', 'prestamo-garantia', 'contacto', 'aviso-de-privacidad',
        'prestamo-personal/solicitar', 'success', 'cancel', 'stripe/webhook', 'up',
        'api/simulator', 'api/simulator/config',
        'request-credit/*', 'postal-codes/{code}',
        'login', 'forgot-password', 'reset-password', 'reset-password/{token}', 'sanctum/csrf-cookie',
        'storage/{path}',
    ];

    /** Rutas del equipo: requieren sesión y uno de los roles del equipo (User::STAFF_ROLES). */
    private const STAFF = [
        'loans', 'loans/*', 'customers/{id}', 'requests', 'requests/*', 'statistics/*', 'document-types', 'document-types/*',
        'pipeline', 'portfolio/*', 'reports', 'reports/*',
    ];

    private function routes(): array
    {
        return collect(Route::getRoutes()->getRoutes())->reject(fn (LaravelRoute $r) => str_starts_with($r->uri(), '_'))->all();
    }

    private function uriIn(string $uri, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($uri === $pattern || fnmatch($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }

    private function middleware(LaravelRoute $route): array
    {
        return array_map(fn ($m) => is_string($m) ? $m : 'closure', $route->gatherMiddleware());
    }

    public function test_every_route_is_either_declared_public_or_requires_authentication(): void
    {
        $unprotected = [];
        foreach ($this->routes() as $route) {
            $isPublic = $this->uriIn($route->uri(), self::PUBLIC);
            $requiresAuth = (bool) array_filter($this->middleware($route), fn ($m) => str_starts_with($m, 'auth'));

            if (! $isPublic && ! $requiresAuth) {
                $unprotected[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $unprotected, "Rutas sin protección y sin declarar como públicas:\n".implode("\n", $unprotected));
    }

    public function test_staff_and_admin_routes_require_the_right_role(): void
    {
        $missing = [];
        foreach ($this->routes() as $route) {
            $uri = $route->uri();
            $roles = collect($this->middleware($route))->first(fn ($m) => str_starts_with($m, 'role:'));

            if (str_starts_with($uri, 'admin/') && $roles !== 'role:Administrator') {
                $missing[] = "{$uri} debe exigir solo Administrator (tiene: ".($roles ?? 'nada').')';
            }
            $staff = 'role:'.implode('|', \App\Models\User::STAFF_ROLES);
            if ($this->uriIn($uri, self::STAFF) && ! in_array($roles, [$staff, 'role:Administrator'], true)) {
                $missing[] = "{$uri} debe exigir los roles del equipo (tiene: ".($roles ?? 'nada').')';
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    public function test_guests_are_sent_to_login_from_every_protected_get_route(): void
    {
        foreach ($this->routes() as $route) {
            if (! in_array('GET', $route->methods(), true) || $this->uriIn($route->uri(), self::PUBLIC)) {
                continue;
            }
            if (! array_filter($this->middleware($route), fn ($m) => $m === 'auth')) {
                continue;
            }
            if (str_starts_with($route->uri(), 'verify-email/') || str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $this->get('/'.$uri)->assertRedirect('/login', "GET /{$uri} debe llevar al login a un invitado");
        }
    }

    public function test_a_customer_gets_403_on_every_staff_and_admin_get_route(): void
    {
        $customer = $this->userWithRole('Customer');

        foreach ($this->routes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            if (! ($this->uriIn($route->uri(), self::STAFF) || str_starts_with($route->uri(), 'admin/'))) {
                continue;
            }

            $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
            $this->actingAs($customer)->get('/'.$uri)->assertForbidden("GET /{$uri} no debe estar abierto a clientes");
        }
    }

    public function test_an_advisor_cannot_open_admin_only_routes(): void
    {
        $advisor = $this->userWithRole('Advisor');

        $this->actingAs($advisor)->get('/admin/loan-products')->assertForbidden();
    }

    public function test_a_customer_cannot_view_another_customers_payments(): void
    {
        $other = $this->approvedLoan();
        $stranger = $this->userWithRole('Customer');

        $this->actingAs($stranger)
            ->get(route('loans.show.payments', ['customer' => $other['customer']->id, 'loan' => $other['loan']->id]))
            ->assertForbidden();

        $this->actingAs($other['owner'])
            ->get(route('loans.show.payments', ['customer' => $other['customer']->id, 'loan' => $other['loan']->id]))
            ->assertOk();
    }
}

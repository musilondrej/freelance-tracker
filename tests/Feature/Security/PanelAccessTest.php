<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('guardedUris')]
    public function test_redirects_guests_to_the_login_page(string $uri): void
    {
        $this->get($uri)->assertRedirect('/login');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function guardedUris(): array
    {
        return [
            'dashboard' => ['/'],
            'profile' => ['/profile'],
        ];
    }

    #[DataProvider('missingUris')]
    public function test_has_no_public_registration_or_password_reset(string $uri): void
    {
        $this->get($uri)->assertNotFound();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingUris(): array
    {
        return [
            'registration' => ['/register'],
            'password reset' => ['/password-reset/request'],
        ];
    }

    public function test_multi_factor_authentication_is_optional_locally_and_required_in_production(): void
    {
        $user = User::factory()->withoutMultiFactorAuthentication()->create();

        $this->actingAs($user)->get('/')->assertOk();
        $this->assertFalse(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired());

        // The set-up redirect is wired at boot in production; here the condition itself is checked.
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->assertTrue(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired());
    }

    public function test_sends_hardening_headers_and_hides_the_php_version(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeaderMissing('X-Powered-By');
    }

    public function test_login_page_is_czech_and_sends_a_csp_with_matching_nonce(): void
    {
        $response = $this->get('/login')->assertOk()->assertSee('Přihlásit');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertSame(1, preg_match("/'nonce-([^']+)'/", $csp, $nonce));
        $this->assertStringContainsString('nonce="'.($nonce[1] ?? '').'"', (string) $response->getContent());
    }
}

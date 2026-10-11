<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_api_responses_carry_security_headers(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
        $response->assertHeader('x-content-type-options', 'nosniff');
        $response->assertHeader('x-frame-options', 'DENY');
        $response->assertHeader('referrer-policy', 'strict-origin-when-cross-origin');

        $policy = $response->headers->get('permissions-policy');
        $this->assertNotNull($policy);

        $cacheControl = $response->headers->get('cache-control');
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_html_responses_receive_content_security_policy(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $csp = $response->headers->get('content-security-policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }
}

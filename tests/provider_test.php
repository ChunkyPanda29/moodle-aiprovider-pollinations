<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiprovider_pollinations;

/**
 * Unit tests for the Pollinations AI provider class.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_pollinations\provider
 */
final class provider_test extends \advanced_testcase {
    /** @var provider Instance of the provider under test. */
    private provider $provider;

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->provider = new provider();
    }

    /**
     * Test that the provider supports the expected AI actions.
     */
    public function test_get_action_list(): void {
        $actions = $this->provider->get_action_list();
        $this->assertContains(\core_ai\aiactions\generate_text::class, $actions);
        $this->assertContains(\core_ai\aiactions\summarise_text::class, $actions);
        $this->assertContains(\core_ai\aiactions\generate_image::class, $actions);
        $this->assertCount(3, $actions);
    }

    /**
     * Test that generate_userid returns a deterministic SHA-256 hash.
     */
    public function test_generate_userid_is_deterministic(): void {
        $hash1 = $this->provider->generate_userid('42');
        $hash2 = $this->provider->generate_userid('42');

        $this->assertEquals($hash1, $hash2);
    }

    /**
     * Test that generate_userid returns a SHA-256 hash (64 hex chars).
     */
    public function test_generate_userid_is_sha256(): void {
        $hash = $this->provider->generate_userid('42');
        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    /**
     * Test that different user IDs produce different hashes.
     */
    public function test_generate_userid_differs_per_user(): void {
        $hash1 = $this->provider->generate_userid('1');
        $hash2 = $this->provider->generate_userid('2');
        $this->assertNotEquals($hash1, $hash2);
    }

    /**
     * Test that the hash does not contain the original user ID.
     */
    public function test_generate_userid_does_not_leak_id(): void {
        $hash = $this->provider->generate_userid('12345');
        $this->assertStringNotContainsString('12345', $hash);
    }

    /**
     * Test provider is not configured when no API key is set.
     */
    public function test_is_provider_configured_without_key(): void {
        $this->assertFalse($this->provider->is_provider_configured());
    }

    /**
     * Test provider is configured when an API key is set.
     */
    public function test_is_provider_configured_with_key(): void {
        set_config('apikey', 'sk_test123456', 'aiprovider_pollinations');
        $provider = new provider();
        $this->assertTrue($provider->is_provider_configured());
    }

    /**
     * Test that authentication headers are added correctly.
     */
    public function test_add_authentication_headers(): void {
        set_config('apikey', 'sk_secretkey123', 'aiprovider_pollinations');
        $provider = new provider();

        $request = new \GuzzleHttp\Psr7\Request('GET', 'https://example.com');
        $authrequest = $provider->add_authentication_headers($request);

        $this->assertTrue($authrequest->hasHeader('Authorization'));
        $this->assertEquals('Bearer sk_secretkey123', $authrequest->getHeaderLine('Authorization'));
    }

    /**
     * Test that the default app key constant is set and non-empty.
     */
    public function test_default_app_key_is_set(): void {
        $this->assertNotEmpty(provider::DEFAULT_APP_KEY);
        $this->assertStringStartsWith('pk_', provider::DEFAULT_APP_KEY);
    }

    /**
     * Test that API endpoint constants are correct.
     */
    public function test_api_endpoints(): void {
        $this->assertEquals('https://gen.pollinations.ai/v1/chat/completions', provider::TEXT_API_ENDPOINT);
        $this->assertEquals('https://gen.pollinations.ai', provider::IMAGE_API_BASE);
    }
}

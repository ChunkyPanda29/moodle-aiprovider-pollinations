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

use GuzzleHttp\Psr7\Response;

/**
 * Unit tests for the abstract processor (via process_generate_text).
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_pollinations\abstract_processor
 * @covers     \aiprovider_pollinations\process_generate_text
 */
final class process_generate_text_test extends \advanced_testcase {
    /** @var process_generate_text Processor instance under test. */
    private process_generate_text $processor;

    /** @var \core_ai\aiactions\generate_text */
    private \core_ai\aiactions\generate_text $action;

    /** @var provider */
    private provider $provider;

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        set_config('apikey', 'sk_testkey123', 'aiprovider_pollinations');
        // Explicitly reset safety and instruction configs to prevent leakage.
        set_config('safety_privacy', 0, 'aiprovider_pollinations');
        set_config('safety_secrets', 0, 'aiprovider_pollinations');
        set_config('safety_nsfw', 0, 'aiprovider_pollinations');
        set_config('action_generate_text_systeminstruction', '', 'aiprovider_pollinations');

        $this->provider = new provider();
        $context = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();

        $this->action = new \core_ai\aiactions\generate_text(
            contextid: $context->id,
            userid: $user->id,
            prompttext: 'Write a short poem about Moodle.',
        );

        $this->processor = new process_generate_text($this->provider, $this->action);
    }

    /**
     * Call a protected/private method on an object via reflection.
     *
     * @param object $object The object instance.
     * @param string $method The method name.
     * @param array $args Method arguments.
     * @return mixed
     */
    private function call_method(object $object, string $method, array $args = []): mixed {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    /**
     * Test that the endpoint URI is correct for text generation.
     */
    public function test_get_endpoint(): void {
        $endpoint = $this->call_method($this->processor, 'get_endpoint');
        $this->assertEquals(provider::TEXT_API_ENDPOINT, (string) $endpoint);
    }

    /**
     * Test default model is 'openai' when no config is set.
     */
    public function test_get_model_default(): void {
        $model = $this->call_method($this->processor, 'get_model');
        $this->assertEquals('openai', $model);
    }

    /**
     * Test model can be overridden via config.
     */
    public function test_get_model_configured(): void {
        set_config('action_generate_text_model', 'claude', 'aiprovider_pollinations');
        $model = $this->call_method($this->processor, 'get_model');
        $this->assertEquals('claude', $model);
    }

    /**
     * Test that the request object is built correctly.
     */
    public function test_create_request_object(): void {
        $request = $this->call_method($this->processor, 'create_request_object', [
            'userid' => 'hashed_user_123',
        ]);

        $this->assertEquals('POST', $request->getMethod());
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertArrayHasKey('model', $body);
        $this->assertArrayHasKey('messages', $body);
        $this->assertCount(1, $body['messages']);
        $this->assertEquals('user', $body['messages'][0]['role']);
        $this->assertEquals('Write a short poem about Moodle.', $body['messages'][0]['content']);
    }

    /**
     * Test that system instruction is included in the request when configured.
     */
    public function test_create_request_object_with_system_instruction(): void {
        set_config('action_generate_text_systeminstruction', 'You are a helpful assistant.', 'aiprovider_pollinations');

        $context = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();
        $action = new \core_ai\aiactions\generate_text(
            contextid: $context->id,
            userid: $user->id,
            prompttext: 'Hello',
        );
        $processor = new process_generate_text($this->provider, $action);

        $request = $this->call_method($processor, 'create_request_object', ['userid' => 'hash123']);
        $body = json_decode($request->getBody()->getContents(), true);

        $this->assertCount(2, $body['messages']);
        $this->assertEquals('system', $body['messages'][0]['role']);
        $this->assertEquals('You are a helpful assistant.', $body['messages'][0]['content']);
        $this->assertEquals('user', $body['messages'][1]['role']);
    }

    /**
     * Test parsing a successful OpenAI-compatible response.
     */
    public function test_handle_api_success(): void {
        $responsebody = json_encode([
            'id' => 'chatcmpl-abc123',
            'choices' => [
                [
                    'message' => ['content' => 'Roses are red, Moodle is blue.'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 12,
            ],
        ]);

        $response = new Response(200, ['Content-Type' => 'application/json'], $responsebody);
        $result = $this->call_method($this->processor, 'handle_api_success', [$response]);

        $this->assertTrue($result['success']);
        $this->assertEquals('chatcmpl-abc123', $result['id']);
        $this->assertEquals('Roses are red, Moodle is blue.', $result['generatedcontent']);
        $this->assertEquals('stop', $result['finishreason']);
        $this->assertEquals(10, $result['prompttokens']);
        $this->assertEquals(12, $result['completiontokens']);
    }

    /**
     * Test handling a response with missing fields.
     */
    public function test_handle_api_success_with_missing_fields(): void {
        $response = new Response(200, [], '{}');
        $result = $this->call_method($this->processor, 'handle_api_success', [$response]);

        $this->assertTrue($result['success']);
        $this->assertEquals('', $result['id']);
        $this->assertEquals('', $result['generatedcontent']);
        $this->assertEquals('unknown', $result['finishreason']);
        $this->assertEquals(0, $result['prompttokens']);
        $this->assertEquals(0, $result['completiontokens']);
    }

    /**
     * Test error handling for 401 (authentication failed).
     */
    public function test_handle_api_error_401(): void {
        $response = new Response(401, [], json_encode(['error' => 'Invalid key']));
        $result = $this->call_method($this->processor, 'handle_api_error', [$response]);

        $this->assertFalse($result['success']);
        $this->assertEquals(401, $result['errorcode']);
        $this->assertStringContainsString('Authentication failed', $result['errormessage']);
    }

    /**
     * Test error handling for 402 (payment required).
     */
    public function test_handle_api_error_402(): void {
        $response = new Response(402, [], json_encode(['error' => 'Insufficient balance']));
        $result = $this->call_method($this->processor, 'handle_api_error', [$response]);

        $this->assertFalse($result['success']);
        $this->assertEquals(402, $result['errorcode']);
        $this->assertStringContainsString('Payment required', $result['errormessage']);
    }

    /**
     * Test error handling for 403 (forbidden).
     */
    public function test_handle_api_error_403(): void {
        $response = new Response(403, [], json_encode(['error' => 'Forbidden']));
        $result = $this->call_method($this->processor, 'handle_api_error', [$response]);

        $this->assertFalse($result['success']);
        $this->assertEquals(403, $result['errorcode']);
        $this->assertStringContainsString('Access forbidden', $result['errormessage']);
    }

    /**
     * Test error handling for 429 (rate limited).
     */
    public function test_handle_api_error_429(): void {
        $response = new Response(429, [], json_encode(['error' => 'Too many requests']));
        $result = $this->call_method($this->processor, 'handle_api_error', [$response]);

        $this->assertFalse($result['success']);
        $this->assertEquals(429, $result['errorcode']);
        $this->assertStringContainsString('Rate limit exceeded', $result['errormessage']);
    }

    /**
     * Test error handling for 400 (bad request).
     */
    public function test_handle_api_error_400(): void {
        $response = new Response(400, [], json_encode(['error' => ['message' => 'Invalid model']]));
        $result = $this->call_method($this->processor, 'handle_api_error', [$response]);

        $this->assertFalse($result['success']);
        $this->assertEquals(400, $result['errorcode']);
        $this->assertStringContainsString('Bad request', $result['errormessage']);
    }

    /**
     * Test error handling for 500 (server error).
     */
    public function test_handle_api_error_500(): void {
        $response = new Response(500, [], json_encode(['error' => 'Internal error']));
        $result = $this->call_method($this->processor, 'handle_api_error', [$response]);

        $this->assertFalse($result['success']);
        $this->assertEquals(500, $result['errorcode']);
        $this->assertStringContainsString('server error', $result['errormessage']);
    }

    /**
     * Test that 429 is retryable.
     */
    public function test_is_retryable_error_429(): void {
        $result = $this->call_method($this->processor, 'is_retryable_error', [429]);
        $this->assertTrue($result);
    }

    /**
     * Test that 5xx errors are retryable.
     */
    public function test_is_retryable_error_5xx(): void {
        $this->assertTrue($this->call_method($this->processor, 'is_retryable_error', [500]));
        $this->assertTrue($this->call_method($this->processor, 'is_retryable_error', [502]));
        $this->assertTrue($this->call_method($this->processor, 'is_retryable_error', [503]));
    }

    /**
     * Test that 4xx errors (except 429) are NOT retryable.
     */
    public function test_is_retryable_error_non_retryable(): void {
        $this->assertFalse($this->call_method($this->processor, 'is_retryable_error', [200]));
        $this->assertFalse($this->call_method($this->processor, 'is_retryable_error', [400]));
        $this->assertFalse($this->call_method($this->processor, 'is_retryable_error', [401]));
        $this->assertFalse($this->call_method($this->processor, 'is_retryable_error', [403]));
        $this->assertFalse($this->call_method($this->processor, 'is_retryable_error', [404]));
    }

    /**
     * Test that safety header returns null when no safety settings enabled.
     */
    public function test_get_safety_header_disabled(): void {
        $result = $this->call_method($this->processor, 'get_safety_header');
        $this->assertNull($result);
    }

    /**
     * Test that safety header returns correct filters when enabled.
     */
    public function test_get_safety_header_privacy(): void {
        set_config('safety_privacy', 1, 'aiprovider_pollinations');
        $result = $this->call_method($this->processor, 'get_safety_header');
        $this->assertEquals('privacy', $result);
    }

    /**
     * Test that safety header combines multiple filters.
     */
    public function test_get_safety_header_combined(): void {
        set_config('safety_privacy', 1, 'aiprovider_pollinations');
        set_config('safety_secrets', 1, 'aiprovider_pollinations');
        $result = $this->call_method($this->processor, 'get_safety_header');
        $this->assertEquals('privacy,secrets', $result);
    }

    /**
     * Test that safety header includes NSFW filter when enabled.
     */
    public function test_get_safety_header_nsfw(): void {
        set_config('safety_nsfw', 1, 'aiprovider_pollinations');
        $result = $this->call_method($this->processor, 'get_safety_header');
        $this->assertStringContainsString('sexual,violence', $result);
    }
}

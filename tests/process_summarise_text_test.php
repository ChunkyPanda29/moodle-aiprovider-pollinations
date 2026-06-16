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
 * Unit tests for the summarise text processor.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_pollinations\process_summarise_text
 */
final class process_summarise_text_test extends \advanced_testcase {
    /** @var process_summarise_text Processor instance under test. */
    private process_summarise_text $processor;

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        set_config('apikey', 'sk_testkey123', 'aiprovider_pollinations');

        $provider = new provider();
        $context = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();

        $action = new \core_ai\aiactions\summarise_text(
            contextid: $context->id,
            userid: $user->id,
            prompttext: 'This is a long piece of text that should be summarised.',
        );

        $this->processor = new process_summarise_text($provider, $action);
    }

    /**
     * Call a protected/private method via reflection.
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
     * Test that the endpoint is the text API endpoint.
     */
    public function test_get_endpoint(): void {
        $endpoint = $this->call_method($this->processor, 'get_endpoint');
        $this->assertEquals(provider::TEXT_API_ENDPOINT, (string) $endpoint);
    }

    /**
     * Test default model falls back to 'openai'.
     */
    public function test_get_model_default(): void {
        $model = $this->call_method($this->processor, 'get_model');
        $this->assertEquals('openai', $model);
    }

    /**
     * Test model can be overridden via config.
     */
    public function test_get_model_configured(): void {
        set_config('action_summarise_text_model', 'gemini', 'aiprovider_pollinations');
        $model = $this->call_method($this->processor, 'get_model');
        $this->assertEquals('gemini', $model);
    }

    /**
     * Test that summarise_text extends process_generate_text.
     * This ensures it inherits the OpenAI-compatible request/response handling.
     */
    public function test_inherits_from_generate_text(): void {
        $this->assertInstanceOf(process_generate_text::class, $this->processor);
    }
}

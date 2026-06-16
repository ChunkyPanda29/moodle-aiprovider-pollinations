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
use GuzzleHttp\Psr7\Uri;

/**
 * Unit tests for the image generation processor.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_pollinations\process_generate_image
 */
final class process_generate_image_test extends \advanced_testcase {
    /** @var process_generate_image Processor instance under test. */
    private process_generate_image $processor;

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        set_config('apikey', 'sk_testkey123', 'aiprovider_pollinations');

        $provider = new provider();
        $context = \context_system::instance();
        $user = $this->getDataGenerator()->create_user();

        $action = new \core_ai\aiactions\generate_image($context);
        $action->set_configuration([
            'userid' => $user->id,
            'prompttext' => 'A cat wearing a graduation cap',
            'aspectratio' => 'square',
            'quality' => 'standard',
        ]);

        $this->processor = new process_generate_image($provider, $action);
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
     * Test that the endpoint URI is correct for image generation.
     */
    public function test_get_endpoint(): void {
        $endpoint = $this->call_method($this->processor, 'get_endpoint');
        $this->assertEquals(provider::IMAGE_API_BASE, (string) $endpoint);
    }

    /**
     * Test default model is 'flux' when no config is set.
     */
    public function test_get_model_default(): void {
        $model = $this->call_method($this->processor, 'get_model');
        $this->assertEquals('flux', $model);
    }

    /**
     * Test model can be overridden via config.
     */
    public function test_get_model_configured(): void {
        set_config('action_generate_image_model', 'seedream', 'aiprovider_pollinations');
        $model = $this->call_method($this->processor, 'get_model');
        $this->assertEquals('seedream', $model);
    }

    /**
     * Test dimension calculation for square + standard.
     */
    public function test_calculate_dimensions_square_standard(): void {
        [$width, $height] = $this->call_method($this->processor, 'calculate_dimensions', ['square', 'standard']);
        $this->assertEquals(1024, $width);
        $this->assertEquals(1024, $height);
    }

    /**
     * Test dimension calculation for landscape + standard (16:9).
     */
    public function test_calculate_dimensions_landscape_standard(): void {
        [$width, $height] = $this->call_method($this->processor, 'calculate_dimensions', ['landscape', 'standard']);
        $this->assertEquals(1024, $width);
        $this->assertEquals(576, $height); // 1024 * 9/16 = 576
    }

    /**
     * Test dimension calculation for portrait + standard (9:16).
     */
    public function test_calculate_dimensions_portrait_standard(): void {
        [$width, $height] = $this->call_method($this->processor, 'calculate_dimensions', ['portrait', 'standard']);
        $this->assertEquals(576, $width);
        $this->assertEquals(1024, $height);
    }

    /**
     * Test dimension calculation for HD quality.
     */
    public function test_calculate_dimensions_hd(): void {
        [$width, $height] = $this->call_method($this->processor, 'calculate_dimensions', ['square', 'hd']);
        $this->assertEquals(1536, $width);
        $this->assertEquals(1536, $height);
    }

    /**
     * Test dimension calculation for landscape HD (16:9).
     */
    public function test_calculate_dimensions_landscape_hd(): void {
        [$width, $height] = $this->call_method($this->processor, 'calculate_dimensions', ['landscape', 'hd']);
        $this->assertEquals(1536, $width);
        $this->assertEquals(864, $height); // 1536 * 9/16 = 864
    }

    /**
     * Test that dimensions are always even numbers.
     */
    public function test_dimensions_are_even(): void {
        foreach (['square', 'landscape', 'portrait'] as $ratio) {
            foreach (['standard', 'hd'] as $quality) {
                [$width, $height] = $this->call_method(
                    $this->processor,
                    'calculate_dimensions',
                    [$ratio, $quality]
                );
                $this->assertEquals(0, $width % 2, "Width should be even for {$ratio}/{$quality}");
                $this->assertEquals(0, $height % 2, "Height should be even for {$ratio}/{$quality}");
            }
        }
    }

    /**
     * Test that the image URL is built correctly.
     */
    public function test_build_image_url(): void {
        $url = $this->call_method($this->processor, 'build_image_url');

        $this->assertStringStartsWith(provider::IMAGE_API_BASE . '/image/', $url);
        $this->assertStringContainsString('model=flux', $url);
        $this->assertStringContainsString('width=1024', $url);
        $this->assertStringContainsString('height=1024', $url);
        $this->assertStringContainsString('nologo=true', $url);
        // Prompt should be URL-encoded.
        $this->assertStringContainsString(rawurlencode('A cat wearing a graduation cap'), $url);
    }

    /**
     * Test that seed parameter is included when configured.
     */
    public function test_build_image_url_with_seed(): void {
        set_config('action_generate_image_seed', '42', 'aiprovider_pollinations');
        $url = $this->call_method($this->processor, 'build_image_url');
        $this->assertStringContainsString('seed=42', $url);
    }

    /**
     * Test that safety filters are included when enabled.
     */
    public function test_build_image_url_with_safety(): void {
        set_config('safety_privacy', 1, 'aiprovider_pollinations');
        $url = $this->call_method($this->processor, 'build_image_url');
        $this->assertStringContainsString('safe=privacy', $url);
    }

    /**
     * Test that the request object is a GET request (overridden method).
     */
    public function test_create_request_object_is_get(): void {
        $request = $this->call_method($this->processor, 'create_request_object', ['userid' => 'hash123']);
        $this->assertEquals('GET', $request->getMethod());
    }
}

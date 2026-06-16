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

namespace aiprovider_pollinations\external;

/**
 * Unit tests for the BYOP external API class.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_pollinations\external\byop
 */
final class byop_test extends \advanced_testcase {
    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that disconnect clears the API key.
     */
    public function test_disconnect(): void {
        $this->setAdminUser();
        set_config('apikey', 'sk_test123', 'aiprovider_pollinations');

        $result = byop::disconnect();
        $this->assertTrue($result['success']);
        $this->assertEmpty(get_config('aiprovider_pollinations', 'apikey'));
    }

    /**
     * Test get_status when not connected.
     */
    public function test_get_status_disconnected(): void {
        $this->setAdminUser();
        $result = byop::get_status();
        $this->assertFalse($result['connected']);
    }

    /**
     * Test get_status when connected.
     */
    public function test_get_status_connected(): void {
        $this->setAdminUser();
        set_config('apikey', 'sk_test123', 'aiprovider_pollinations');
        $result = byop::get_status();
        $this->assertTrue($result['connected']);
    }

    /**
     * Test init_device_flow returns structure.
     */
    public function test_init_device_flow_returns_structure(): void {
        $this->setAdminUser();
        $result = byop::init_device_flow();

        // The result should have a 'success' key.
        // It may fail if Pollinations API is unreachable in CI.
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Test poll_device_token parameters validation.
     */
    public function test_poll_device_token_parameters(): void {
        $params = byop::poll_device_token_parameters();
        $this->assertInstanceOf(\core_external\external_function_parameters::class, $params);
    }

    /**
     * Test init_device_flow returns structure definition.
     */
    public function test_init_device_flow_returns_structure(): void {
        $returns = byop::init_device_flow_returns();
        $this->assertInstanceOf(\core_external\external_single_structure::class, $returns);
    }

    /**
     * Test get_status returns structure definition.
     */
    public function test_get_status_returns_structure(): void {
        $returns = byop::get_status_returns();
        $this->assertInstanceOf(\core_external\external_single_structure::class, $returns);
    }

    /**
     * Test disconnect returns structure definition.
     */
    public function test_disconnect_returns_structure(): void {
        $returns = byop::disconnect_returns();
        $this->assertInstanceOf(\core_external\external_single_structure::class, $returns);
    }
}

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

namespace aiprovider_pollinations\task;

/**
 * Unit tests for the check_balance_task scheduled task.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_pollinations\task\check_balance_task
 */
final class check_balance_task_test extends \advanced_testcase {
    /** @var check_balance_task The task instance. */
    private check_balance_task $task;

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->task = new check_balance_task();
    }

    /**
     * Test that the task has a name.
     */
    public function test_get_name(): void {
        $name = $this->task->get_name();
        $this->assertNotEmpty($name);
        $this->assertEquals('Check Pollinations pollen balance', $name);
    }

    /**
     * Test that the task is a scheduled task.
     */
    public function test_is_scheduled_task(): void {
        $this->assertInstanceOf(\core\task\scheduled_task::class, $this->task);
    }

    /**
     * Test that the task skips gracefully when no API key is configured.
     */
    public function test_execute_without_api_key(): void {
        // No API key set — should skip, not crash.
        $this->expectOutputString('');
        // Suppress mtrace output.
        ob_start();
        $this->task->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('API key not configured', $output);
    }
}

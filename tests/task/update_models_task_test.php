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
 * Unit tests for the update_models_task scheduled task.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_pollinations\task\update_models_task
 */
final class update_models_task_test extends \advanced_testcase {
    /** @var update_models_task The task instance. */
    private update_models_task $task;

    #[\Override]
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->task = new update_models_task();
    }

    /**
     * Test that the task has a name.
     */
    public function test_get_name(): void {
        $name = $this->task->get_name();
        $this->assertNotEmpty($name);
        $this->assertEquals('Update Pollinations model list', $name);
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
        ob_start();
        $this->task->execute();
        $output = ob_get_clean();
        $this->assertStringContainsString('API key not configured', $output);
        $this->assertStringContainsString('Skipping model update', $output);
    }
}

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
 * Scheduled task to update the cached Pollinations model lists.
 *
 * Fetches both text and image model lists daily to keep the
 * admin settings selectors current.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_models_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_update_models', 'aiprovider_pollinations');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $provider = new \aiprovider_pollinations\provider();

        if (!$provider->is_provider_configured()) {
            mtrace(get_string('error_noapikey', 'aiprovider_pollinations') . ' Skipping model update.');
            return;
        }

        // Fetch text models.
        $textmodels = $provider->fetch_models('text');
        if (empty($textmodels)) {
            mtrace(get_string('task_models_update_failed', 'aiprovider_pollinations', 'text models: empty response'));
        } else {
            mtrace(get_string('task_models_updated', 'aiprovider_pollinations', (object)[
                'count' => count($textmodels),
                'type' => 'text',
            ]));
        }

        // Fetch image models.
        $imagemodels = $provider->fetch_models('image');
        if (empty($imagemodels)) {
            mtrace(get_string('task_models_update_failed', 'aiprovider_pollinations', 'image models: empty response'));
        } else {
            mtrace(get_string('task_models_updated', 'aiprovider_pollinations', (object)[
                'count' => count($imagemodels),
                'type' => 'image',
            ]));
        }
    }
}

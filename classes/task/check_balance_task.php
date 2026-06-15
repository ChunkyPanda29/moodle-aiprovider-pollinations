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
 * Scheduled task to check the Pollinations pollen balance and notify admins if low.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_balance_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_check_balance', 'aiprovider_pollinations');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        $provider = new \aiprovider_pollinations\provider();

        if (!$provider->is_provider_configured()) {
            mtrace('Pollinations API key not configured. Skipping balance check.');
            return;
        }

        $balance = $provider->fetch_balance();

        if ($balance === null) {
            mtrace(get_string('task_balance_check_failed', 'aiprovider_pollinations', 'API error'));
            return;
        }

        $total = $balance['total'] ?? ($balance['tier_balance'] ?? 0) + ($balance['paid_balance'] ?? 0);
        mtrace(get_string('task_balance_checked', 'aiprovider_pollinations', $total));

        // Check if balance is below the threshold.
        $threshold = (int) get_config('aiprovider_pollinations', 'balancethreshold');
        if ($threshold > 0 && $total < $threshold) {
            $this->notify_admins($total);
        }
    }

    /**
     * Send a notification to all site administrators about low balance.
     *
     * @param int $balance The current pollen balance.
     */
    private function notify_admins(int $balance): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $admins = get_admins();
        $subject = get_string('task_balance_low_subject', 'aiprovider_pollinations', $balance);
        $message = get_string('task_balance_low_body', 'aiprovider_pollinations', $balance);

        foreach ($admins as $admin) {
            $eventdata = new \core\message\message();
            $eventdata->component = 'aiprovider_pollinations';
            $eventdata->name = 'lowbalance';
            $eventdata->userfrom = \core_user::get_noreply_user();
            $eventdata->userto = $admin->id;
            $eventdata->subject = $subject;
            $eventdata->fullmessage = $message;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = '';
            $eventdata->smallmessage = $message;
            $eventdata->notification = 1;

            message_send($eventdata);
        }

        mtrace(get_string('task_balance_notified', 'aiprovider_pollinations', count($admins)));
    }
}

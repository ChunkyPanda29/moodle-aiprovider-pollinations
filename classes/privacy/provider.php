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

namespace aiprovider_pollinations\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider implementation for Pollinations AI provider.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @codeCoverageIgnore
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\ReturnTypeWillChange]
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('aiprovider_pollinations', [
            'prompttext' => 'privacy:metadata:aiprovider_pollinations:prompttext',
            'model' => 'privacy:metadata:aiprovider_pollinations:model',
            'systeminstruction' => 'privacy:metadata:aiprovider_pollinations:systeminstruction',
        ], 'privacy:metadata:aiprovider_pollinations:externalpurpose');
        return $collection;
    }

    #[\ReturnTypeWillChange]
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    #[\ReturnTypeWillChange]
    public static function get_users_in_context(userlist $userlist) {
    }

    #[\ReturnTypeWillChange]
    public static function export_user_data(approved_contextlist $contextlist) {
    }

    #[\ReturnTypeWillChange]
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
    }

    #[\ReturnTypeWillChange]
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }
}

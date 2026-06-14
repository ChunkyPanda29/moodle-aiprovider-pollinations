<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     aiprovider_pollinations
 * @copyright   2026 Krissy Painter
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_ai\admin\admin_settingspage_provider;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    global $PAGE;

    $settings = new admin_settingspage_provider(
        'aiprovider_pollinations',
        new lang_string('pluginname', 'aiprovider_pollinations'),
        'moodle/site:config',
        true,
    );

    // BYOP connection heading.
    $settings->add(new admin_setting_heading(
        'aiprovider_pollinations/byop_heading',
        new lang_string('byop_heading', 'aiprovider_pollinations'),
        '',
    ));

    // BYOP connect UI placeholder — the AMD module targets this container.
    $byopplaceholder = \html_writer::div(
        '',
        '',
        ['id' => 'aiprovider_pollinations_byop_container']
    );
    $settings->add(new admin_setting_description(
        'aiprovider_pollinations/byop_connect_ui',
        new lang_string('byop_connect', 'aiprovider_pollinations'),
        $byopplaceholder,
    ));

    // API key (set automatically by BYOP flow, not directly editable by users).
    $settings->add(new admin_setting_configpasswordunmask(
        'aiprovider_pollinations/apikey',
        new lang_string('apikey', 'aiprovider_pollinations'),
        new lang_string('apikey_desc', 'aiprovider_pollinations'),
        '',
    ));

    // Load the BYOP AMD module properly via Moodle's page requirements.
    $PAGE->requires->js_call_amd('aiprovider_pollinations/byop_connect', 'init');

    // Rate limiting heading.
    $settings->add(new admin_setting_heading(
        'aiprovider_pollinations/ratelimit',
        new lang_string('ratelimit_heading', 'aiprovider_pollinations'),
        '',
    ));

    // Global rate limiting.
    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_pollinations/enableglobalratelimit',
        new lang_string('enableglobalratelimit', 'aiprovider_pollinations'),
        new lang_string('enableglobalratelimit_desc', 'aiprovider_pollinations'),
        0,
    ));

    $settings->add(new admin_setting_configtext(
        'aiprovider_pollinations/globalratelimit',
        new lang_string('globalratelimit', 'aiprovider_pollinations'),
        new lang_string('globalratelimit_desc', 'aiprovider_pollinations'),
        100,
        PARAM_INT,
    ));
    $settings->hide_if(
        'aiprovider_pollinations/globalratelimit',
        'aiprovider_pollinations/enableglobalratelimit',
        'eq',
        0,
    );

    // Per-user rate limiting.
    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_pollinations/enableuserratelimit',
        new lang_string('enableuserratelimit', 'aiprovider_pollinations'),
        new lang_string('enableuserratelimit_desc', 'aiprovider_pollinations'),
        0,
    ));

    $settings->add(new admin_setting_configtext(
        'aiprovider_pollinations/userratelimit',
        new lang_string('userratelimit', 'aiprovider_pollinations'),
        new lang_string('userratelimit_desc', 'aiprovider_pollinations'),
        10,
        PARAM_INT,
    ));
    $settings->hide_if(
        'aiprovider_pollinations/userratelimit',
        'aiprovider_pollinations/enableuserratelimit',
        'eq',
        0,
    );

    // Safety settings heading.
    $settings->add(new admin_setting_heading(
        'aiprovider_pollinations/safety',
        new lang_string('safety_heading', 'aiprovider_pollinations'),
        '',
    ));

    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_pollinations/safety_privacy',
        new lang_string('safety_privacy', 'aiprovider_pollinations'),
        new lang_string('safety_privacy_desc', 'aiprovider_pollinations'),
        1,
    ));

    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_pollinations/safety_secrets',
        new lang_string('safety_secrets', 'aiprovider_pollinations'),
        new lang_string('safety_secrets_desc', 'aiprovider_pollinations'),
        1,
    ));

    $settings->add(new admin_setting_configcheckbox(
        'aiprovider_pollinations/safety_nsfw',
        new lang_string('safety_nsfw', 'aiprovider_pollinations'),
        new lang_string('safety_nsfw_desc', 'aiprovider_pollinations'),
        0,
    ));

    // Account & balance section.
    $settings->add(new admin_setting_heading(
        'aiprovider_pollinations/account',
        new lang_string('account_heading', 'aiprovider_pollinations'),
        '',
    ));

    $settings->add(new admin_setting_configtext(
        'aiprovider_pollinations/balancethreshold',
        new lang_string('account_balancethreshold', 'aiprovider_pollinations'),
        new lang_string('account_balancethreshold_desc', 'aiprovider_pollinations'),
        100,
        PARAM_INT,
    ));
}

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

/**
 * Strings for component 'aiprovider_pollinations', language 'en'.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// General.
$string['pluginname'] = 'Pollinations AI provider';

// API settings.
$string['apikey'] = 'Pollinations API key';
$string['apikey_desc'] = 'Your Pollinations secret key (sk_...). This is set automatically via the Connect to Pollinations flow above, but can also be entered manually for testing.';
$string['appkey'] = 'BYOP app key override (optional)';
$string['appkey_desc'] = 'Leave empty to use the default BYOP app key. Override only if you have your own Pollinations developer app key (pk_...).';

// BYOP device flow.
$string['byop_heading'] = 'Pollinations Connection';
$string['byop_connect'] = 'Connect to Pollinations';
$string['byop_connect_desc'] = 'Click to authorize your Moodle site with Pollinations. You will need a Pollinations account.';
$string['byop_connected'] = 'Connected as {$a}';
$string['byop_disconnected'] = 'Not connected';
$string['byop_disconnect'] = 'Disconnect';
$string['byop_code_instructions'] = 'Go to {$a->url} and enter code: {$a->code}';
$string['byop_polling'] = 'Waiting for authorization...';
$string['byop_balance'] = 'Balance: {$a} pollen';
$string['byop_error_init'] = 'Failed to start authorization. Please try again.';
$string['byop_error_poll'] = 'Authorization check failed. Please try again.';
$string['byop_error_denied'] = 'Authorization was denied.';
$string['byop_success'] = 'Successfully connected to Pollinations!';

// Rate limiting.
$string['enableglobalratelimit'] = 'Set site-wide rate limit';
$string['enableglobalratelimit_desc'] = 'Limit the number of requests that the Pollinations API provider can receive across the entire site every hour.';
$string['globalratelimit'] = 'Maximum number of site-wide requests';
$string['globalratelimit_desc'] = 'The number of site-wide requests allowed per hour.';

// Action: generate_text.
$string['action:generate_text:endpoint'] = 'API endpoint';
$string['action:generate_text:model'] = 'AI model';
$string['action:generate_text:model_desc'] = 'The Pollinations text model used to generate the response. Models are fetched automatically from the Pollinations API.';
$string['action:generate_text:systeminstruction'] = 'System instruction';
$string['action:generate_text:systeminstruction_desc'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';

// Action: summarise_text.
$string['action:summarise_text:endpoint'] = 'API endpoint';
$string['action:summarise_text:model'] = 'AI model';
$string['action:summarise_text:model_desc'] = 'The Pollinations text model used to summarise the provided text. Models are fetched automatically from the Pollinations API.';
$string['action:summarise_text:systeminstruction'] = 'System instruction';
$string['action:summarise_text:systeminstruction_desc'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';

// Model info section.
$string['modelinfo_heading'] = 'Model information';
$string['modelinfo_desc'] = 'Information about available Pollinations models. Models are refreshed daily via a scheduled task.';
$string['modelinfo_lastupdated'] = 'Last updated';
$string['modelinfo_never'] = 'Never (run the update_models scheduled task)';
$string['modelinfo_count'] = '{$a} models available';
$string['modelinfo_nocached'] = 'No cached model data. Run the update_models scheduled task or check your API key.';

// Account section.
$string['account_heading'] = 'Account & balance';
$string['account_balancethreshold'] = 'Low balance reminder threshold';
$string['account_balancethreshold_desc'] = 'When the pollen balance drops below this value, a notification will be sent to site administrators.';
$string['account_balance_current'] = 'Current pollen balance';
$string['account_balance_unknown'] = 'Unknown (check API key)';
$string['account_balance_total'] = 'Total: {$a} pollen';
$string['account_tier_balance'] = 'Tier balance: {$a} pollen';
$string['account_paid_balance'] = 'Paid balance: {$a} pollen';

// Error messages.
$string['error_noapikey'] = 'Pollinations API key is not configured.';
$string['error_apirequest'] = 'Error communicating with the Pollinations API: {$a}';
$string['error_invalidresponse'] = 'Invalid response from Pollinations API.';
$string['error_ratelimit'] = 'Rate limit exceeded.';
$string['getallmodels_error'] = 'Unable to fetch models. Please check your API key.';

// Privacy.
$string['privacy:metadata'] = 'The Pollinations AI provider plugin does not store any personal data.';
$string['privacy:metadata:aiprovider_pollinations:externalpurpose'] = 'This information is sent to the Pollinations API in order for a response to be generated. Your Pollinations account settings may change how Pollinations stores and retains this data. No user data is explicitly sent to Pollinations or stored in Moodle LMS by this plugin.';
$string['privacy:metadata:aiprovider_pollinations:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_pollinations:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_pollinations:systeminstruction'] = 'The system instruction sent with the request.';

// Scheduled tasks.
$string['task_update_models'] = 'Update Pollinations model list';
$string['task_check_balance'] = 'Check Pollinations pollen balance';
$string['task_balance_low_subject'] = 'Pollinations AI balance low: {$a} pollen remaining';
$string['task_balance_low_body'] = 'The Pollinations AI provider pollen balance has dropped to {$a} pollen. Please top up your account at https://pollinations.ai to avoid service disruption.';
$string['task_models_updated'] = 'Pollinations models updated: {$a->count} models available.';
$string['task_models_update_failed'] = 'Failed to update Pollinations model list: {$a}';
$string['task_balance_checked'] = 'Pollinations balance checked: {$a} pollen.';
$string['task_balance_check_failed'] = 'Failed to check Pollinations balance: {$a}';

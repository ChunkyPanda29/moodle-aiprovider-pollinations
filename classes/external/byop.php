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

use core\http_client;
use core_external\external_api;
use core_external\external_description;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use GuzzleHttp\Psr7\Request;

/**
 * External API class for BYOP device flow integration.
 *
 * Provides AJAX-callable methods for the Pollinations BYOP
 * (Bring Your Own Pollen) device authorisation flow.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byop extends external_api {
    /**
     * Returns the BYOP publishable app key, checking for an override in config.
     *
     * @return string
     */
    private static function get_app_key(): string {
        return \aiprovider_pollinations\provider::DEFAULT_APP_KEY;
    }

    /**
     * Parameters for init_device_flow.
     *
     * @return external_function_parameters
     */
    public static function init_device_flow_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns description of init_device_flow result.
     *
     * @return external_description
     */
    public static function init_device_flow_returns(): external_description {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded'),
            'user_code' => new external_value(PARAM_ALPHANUMEXT, 'The code the user must enter', VALUE_OPTIONAL),
            'verification_url' => new external_value(PARAM_URL, 'The URL the user must visit', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Initiate a BYOP device flow.
     *
     * Posts to the Pollinations device/code endpoint, stores the device_code
     * in the Moodle session, and returns the user_code + verification URL.
     *
     * @return array
     */
    public static function init_device_flow(): array {
        global $SESSION;

        // Ensure the admin session is started.
        require_sesskey();

        $appkey = self::get_app_key();
        $client = \core\di::get(http_client::class);

        $request = new Request(
            method: 'POST',
            uri: 'https://enter.pollinations.ai/api/device/code',
            body: json_encode([
                'client_id' => $appkey,
                'scope' => 'generate',
            ]),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );

        try {
            $response = $client->send($request);
            $body = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() !== 200 || empty($body['device_code'])) {
                return [
                    'success' => false,
                    'error' => get_string('byop_error_init', 'aiprovider_pollinations'),
                ];
            }

            // Store the device code in session for later polling.
            $SESSION->aiprovider_pollinations_device_code = $body['device_code'];

            return [
                'success' => true,
                'user_code' => $body['user_code'],
                'verification_url' => 'https://enter.pollinations.ai/device',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => get_string('byop_error_init', 'aiprovider_pollinations') . ' ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Parameters for poll_device_token.
     *
     * @return external_function_parameters
     */
    public static function poll_device_token_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns description of poll_device_token result.
     *
     * @return external_description
     */
    public static function poll_device_token_returns(): external_description {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the token was obtained'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'pending, authorised, or error', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Poll for the device token.
     *
     * Uses the device_code stored in the session to check if the user has
     * completed authorisation. On success, stores the access_token (sk_...)
     * as the plugin apikey.
     *
     * @return array
     */
    public static function poll_device_token(): array {
        global $SESSION;

        require_sesskey();

        $devicecode = $SESSION->aiprovider_pollinations_device_code ?? null;
        if (empty($devicecode)) {
            return [
                'success' => false,
                'status' => 'error',
                'error' => get_string('byop_error_poll', 'aiprovider_pollinations'),
            ];
        }

        $client = \core\di::get(http_client::class);

        $request = new Request(
            method: 'POST',
            uri: 'https://enter.pollinations.ai/api/device/token',
            body: json_encode([
                'device_code' => $devicecode,
            ]),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );

        try {
            $response = $client->send($request);
            $body = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() !== 200) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'error' => get_string('byop_error_poll', 'aiprovider_pollinations'),
                ];
            }

            // Check for pending.
            if (isset($body['error']) && $body['error'] === 'authorization_pending') {
                return [
                    'success' => false,
                    'status' => 'pending',
                ];
            }

            // Check for denied/expired.
            if (isset($body['error'])) {
                $error = $body['error'];
                if ($error === 'access_denied') {
                    return [
                        'success' => false,
                        'status' => 'denied',
                        'error' => get_string('byop_error_denied', 'aiprovider_pollinations'),
                    ];
                }
                if ($error === 'expired_token') {
                    return [
                        'success' => false,
                        'status' => 'expired',
                        'error' => get_string('byop_error_init', 'aiprovider_pollinations'),
                    ];
                }
                return [
                    'success' => false,
                    'status' => 'error',
                    'error' => $error,
                ];
            }

            // We got a token!
            if (!empty($body['access_token'])) {
                // Store the access token as the apikey.
                set_config('apikey', $body['access_token'], 'aiprovider_pollinations');

                // Clean up the session.
                unset($SESSION->aiprovider_pollinations_device_code);

                return [
                    'success' => true,
                    'status' => 'authorised',
                ];
            }

            return [
                'success' => false,
                'status' => 'error',
                'error' => get_string('byop_error_poll', 'aiprovider_pollinations'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parameters for get_status.
     *
     * @return external_function_parameters
     */
    public static function get_status_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns description of get_status result.
     *
     * @return external_description
     */
    public static function get_status_returns(): external_description {
        return new external_single_structure([
            'connected' => new external_value(PARAM_BOOL, 'Whether a key is configured'),
            'username' => new external_value(PARAM_TEXT, 'Connected user name', VALUE_OPTIONAL),
            'balance' => new external_value(PARAM_TEXT, 'Human-readable balance', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Get the current BYOP connection status.
     *
     * Checks if an apikey is configured and optionally fetches
     * user info and balance from the Pollinations API.
     *
     * @return array
     */
    public static function get_status(): array {
        $apikey = get_config('aiprovider_pollinations', 'apikey');
        if (empty($apikey)) {
            return [
                'connected' => false,
            ];
        }

        $client = \core\di::get(http_client::class);
        $result = [
            'connected' => true,
        ];

        // Fetch user info.
        try {
            $userinforequest = new Request(
                method: 'GET',
                uri: 'https://enter.pollinations.ai/api/device/userinfo',
                headers: [
                    'Authorization' => 'Bearer ' . $apikey,
                ],
            );
            $userinforesponse = $client->send($userinforequest);
            if ($userinforesponse->getStatusCode() === 200) {
                $userinfo = json_decode($userinforesponse->getBody()->getContents(), true);
                if (is_array($userinfo)) {
                    $result['username'] = $userinfo['preferred_username']
                        ?? $userinfo['name']
                        ?? $userinfo['sub']
                        ?? '';
                }
            }
        } catch (\Exception $e) {
            debugging('Failed to fetch Pollinations user info: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Fetch balance.
        try {
            $balancerequest = new Request(
                method: 'GET',
                uri: 'https://gen.pollinations.ai/account/balance',
                headers: [
                    'Authorization' => 'Bearer ' . $apikey,
                ],
            );
            $balanceresponse = $client->send($balancerequest);
            if ($balanceresponse->getStatusCode() === 200) {
                $balancedata = json_decode($balanceresponse->getBody()->getContents(), true);
                if (is_array($balancedata)) {
                    $total = $balancedata['total']
                        ?? ($balancedata['tier_balance'] ?? 0) + ($balancedata['paid_balance'] ?? 0);
                    $result['balance'] = (string) $total;
                    // Cache the balance.
                    set_config('cached_balance', json_encode($balancedata), 'aiprovider_pollinations');
                }
            }
        } catch (\Exception $e) {
            // Non-critical - continue without balance.
            debugging('Failed to fetch Pollinations balance: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $result;
    }

    /**
     * Parameters for disconnect.
     *
     * @return external_function_parameters
     */
    public static function disconnect_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Returns description of disconnect result.
     *
     * @return external_description
     */
    public static function disconnect_returns(): external_description {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether disconnection succeeded'),
        ]);
    }

    /**
     * Disconnect the BYOP connection by clearing the stored apikey.
     *
     * @return array
     */
    public static function disconnect(): array {
        require_sesskey();

        set_config('apikey', '', 'aiprovider_pollinations');

        return [
            'success' => true,
        ];
    }
}

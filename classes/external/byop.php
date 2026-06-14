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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core\http_client;
use GuzzleHttp\Psr7\Request;

/**
 * External API class for BYOP device flow integration.
 *
 * Uses the Pollinations Device Flow:
 * 1. Admin clicks "Connect" → plugin requests a device code
 * 2. Admin opens enter.pollinations.ai/device and enters the code
 * 3. Plugin polls until the user authorizes
 * 4. Pollinations returns a scoped user key (sk_...)
 *
 * The device flow is domain-independent — no redirect URI registration needed.
 * This is essential for a plugin distributed to thousands of different Moodle sites.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byop extends external_api {
    /**
     * The BYOP publishable app key.
     */
    const APP_KEY = 'pk_Jpc…Hqe6';

    /**
     * Initiate the BYOP device authorisation flow.
     *
     * Requests a device code from Pollinations. The user must visit
     * the verification URI and enter the user_code to authorise.
     *
     * @return array Contains device_code, user_code, and verification_uri.
     */
    public static function init_device_flow(): array {
        require_sesskey();

        $request = new Request(
            method: 'POST',
            uri: 'https://enter.pollinations.ai/api/device/code',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode([
                'client_id' => self::APP_KEY,
                'scope' => 'generate',
            ]),
        );

        $client = \core\di::get(http_client::class);

        try {
            $response = $client->send($request);
            $body = json_decode($response->getBody()->getContents(), true);

            if (!is_array($body) || !isset($body['device_code'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response from Pollinations.',
                ];
            }

            return [
                'success' => true,
                'device_code' => $body['device_code'],
                'user_code' => $body['user_code'],
                'verification_uri' => 'https://enter.pollinations.ai/device',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
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
     * Return structure for init_device_flow.
     *
     * @return external_single_structure
     */
    public static function init_device_flow_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'device_code' => new external_value(PARAM_RAW, 'Device code for polling', VALUE_OPTIONAL),
            'user_code' => new external_value(PARAM_RAW, 'User code to display', VALUE_OPTIONAL),
            'verification_uri' => new external_value(PARAM_URL, 'URI for user to visit', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_RAW, 'Error message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Poll for the BYOP device authorisation token.
     *
     * Returns authorization_pending while the user hasn't authorised yet.
     * Returns the API key once the user completes authorisation.
     *
     * @param string $devicecode The device code from init_device_flow.
     * @return array
     */
    public static function poll_device_token(string $devicecode): array {
        require_sesskey();

        $params = self::validate_parameters(self::poll_device_token_parameters(), [
            'devicecode' => $devicecode,
        ]);

        $request = new Request(
            method: 'POST',
            uri: 'https://enter.pollinations.ai/api/device/token',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode([
                'device_code' => $params['devicecode'],
            ]),
        );

        $client = \core\di::get(http_client::class);

        try {
            $response = $client->send($request);
            $status = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            // Check for authorisation pending — this is NORMAL, not an error.
            if ($status === 400 && isset($body['error']) && $body['error'] === 'authorization_pending') {
                return [
                    'success' => false,
                    'pending' => true,
                ];
            }

            // Check for other pending states.
            if ($status === 400 && isset($body['error']) && $body['error'] === 'slow_down') {
                return [
                    'success' => false,
                    'pending' => true,
                ];
            }

            // Check for denied.
            if (isset($body['error']) && $body['error'] === 'access_denied') {
                return [
                    'success' => false,
                    'pending' => false,
                    'error' => 'Authorization was denied.',
                ];
            }

            // Check for expired.
            if (isset($body['error']) && $body['error'] === 'expired_token') {
                return [
                    'success' => false,
                    'pending' => false,
                    'error' => 'The device code has expired. Please try again.',
                ];
            }

            // Success — we got the access token.
            if (isset($body['access_token'])) {
                $apikey = $body['access_token'];
                set_config('apikey', $apikey, 'aiprovider_pollinations');

                return [
                    'success' => true,
                    'pending' => false,
                ];
            }

            // Unknown response.
            return [
                'success' => false,
                'pending' => false,
                'error' => 'Unexpected response from Pollinations.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'pending' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parameters for poll_device_token.
     *
     * @return external_function_parameters
     */
    public static function poll_device_token_parameters(): external_function_parameters {
        return new external_function_parameters([
            'devicecode' => new external_value(PARAM_RAW, 'The device code to poll'),
        ]);
    }

    /**
     * Return structure for poll_device_token.
     *
     * @return external_single_structure
     */
    public static function poll_device_token_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether authorisation completed'),
            'pending' => new external_value(PARAM_BOOL, 'Whether authorisation is still pending', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_RAW, 'Error message', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Get the current BYOP connection status.
     *
     * @return array
     */
    public static function get_status(): array {
        require_sesskey();

        $apikey = get_config('aiprovider_pollinations', 'apikey');
        $connected = !empty($apikey);

        $result = [
            'connected' => $connected,
        ];

        if ($connected) {
            $provider = new \aiprovider_pollinations\provider();
            $balance = $provider->fetch_balance();
            if ($balance !== null) {
                $result['balance'] = $balance['total'] ?? $balance['balance'] ?? null;
            }
        }

        return $result;
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
     * Return structure for get_status.
     *
     * @return external_single_structure
     */
    public static function get_status_returns(): external_single_structure {
        return new external_single_structure([
            'connected' => new external_value(PARAM_BOOL, 'Whether connected'),
            'balance' => new external_value(PARAM_FLOAT, 'Current pollen balance', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Disconnect by clearing the stored API key.
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

    /**
     * Parameters for disconnect.
     *
     * @return external_function_parameters
     */
    public static function disconnect_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return structure for disconnect.
     *
     * @return external_single_structure
     */
    public static function disconnect_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether disconnection was successful'),
        ]);
    }
}

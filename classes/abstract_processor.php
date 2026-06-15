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

namespace aiprovider_pollinations;

use core\http_client;
use core_ai\aiactions\responses\response_base;
use core_ai\process_base;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Abstract processor for Pollinations AI actions.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class abstract_processor extends process_base {
    /** @var int Maximum retry attempts for transient failures. */
    private const MAX_RETRIES = 3;

    /** @var int Base delay between retries in milliseconds. */
    private const RETRY_BASE_DELAY_MS = 1000;

    /** @var int Request timeout in seconds. */
    private const REQUEST_TIMEOUT = 60;

    /**
     * Get the endpoint URI.
     *
     * @return UriInterface
     */
    abstract protected function get_endpoint(): UriInterface;

    /**
     * Get the name of the model to use.
     *
     * @return string
     */
    abstract protected function get_model(): string;

    /**
     * Get the system instructions.
     *
     * @return string
     */
    protected function get_system_instruction(): string {
        return $this->action::get_system_instruction();
    }

    /**
     * Create the request object to send to the Pollinations API.
     *
     * @param string $userid The user id.
     * @return RequestInterface The request object to send to the Pollinations API.
     */
    abstract protected function create_request_object(
        string $userid,
    ): RequestInterface;

    /**
     * Handle a successful response from the external AI api.
     *
     * @param ResponseInterface $response The response object.
     * @return array The response.
     */
    abstract protected function handle_api_success(ResponseInterface $response): array;

    /**
     * Get the configured safety header value, if any.
     *
     * @return string|null The safety value or null if disabled.
     */
    protected function get_safety_header(): ?string {
        $filters = [];
        if ((bool) get_config('aiprovider_pollinations', 'safety_privacy')) {
            $filters[] = 'privacy';
        }
        if ((bool) get_config('aiprovider_pollinations', 'safety_secrets')) {
            $filters[] = 'secrets';
        }
        if ((bool) get_config('aiprovider_pollinations', 'safety_nsfw')) {
            $filters[] = 'sexual,violence';
        }
        if (!empty($filters)) {
            return implode(',', $filters);
        }
        return null;
    }

    /**
     * Query the Pollinations API with retry logic.
     *
     * Retries on transient failures (429, 5xx) with exponential backoff.
     *
     * @return array The response array.
     */
    #[\Override]
    protected function query_ai_api(): array {
        $request = $this->create_request_object(
            userid: $this->provider->generate_userid($this->action->get_configuration('userid')),
        );
        $request = $this->provider->add_authentication_headers($request);

        // Add safety header if configured.
        $safety = $this->get_safety_header();
        if ($safety !== null) {
            $request = $request->withAddedHeader('Pollinations-Safe', $safety);
        }

        $client = \core\di::get(http_client::class);
        $endpoint = $this->get_endpoint();

        $attempt = 0;
        $lasterror = null;

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            try {
                $response = $client->send($request, [
                    'base_uri' => $endpoint,
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::TIMEOUT => self::REQUEST_TIMEOUT,
                    RequestOptions::CONNECT_TIMEOUT => 15,
                ]);
            } catch (RequestException $e) {
                // Network-level errors are always retryable.
                $lasterror = [
                    'success' => false,
                    'errorcode' => $e->getCode(),
                    'errormessage' => get_string('error_connection', 'aiprovider_pollinations', $e->getMessage()),
                ];

                // Retry on connection failures (but not on the last attempt).
                if ($attempt < self::MAX_RETRIES) {
                    $this->sleep_before_retry($attempt);
                    continue;
                }
                break;
            }

            $status = $response->getStatusCode();

            if ($status === 200) {
                // Success — parse and return.
                return $this->handle_api_success($response);
            }

            // Check if this error is retryable.
            if ($this->is_retryable_error($status) && $attempt < self::MAX_RETRIES) {
                // Parse error for logging, then retry.
                $lasterror = $this->handle_api_error($response);

                // Honour Retry-After header if present (for 429s).
                $retryafter = $response->getHeaderLine('Retry-After');
                if (!empty($retryafter) && is_numeric($retryafter)) {
                    usleep((int) $retryafter * 1000000);
                } else {
                    $this->sleep_before_retry($attempt);
                }
                continue;
            }

            // Non-retryable error or out of retries.
            return $this->handle_api_error($response);
        }

        // Exhausted all retries.
        if ($lasterror !== null) {
            $lasterror['errormessage'] = get_string(
                'error_retryexhausted',
                'aiprovider_pollinations',
                ['message' => $lasterror['errormessage'], 'attempts' => $attempt],
            );
            return $lasterror;
        }

        // Fallback (should not reach here).
        return [
            'success' => false,
            'errorcode' => 500,
            'errormessage' => get_string('error_unknown', 'aiprovider_pollinations'),
        ];
    }

    /**
     * Check if an HTTP status code is retryable.
     *
     * @param int $status The HTTP status code.
     * @return bool True if the error is transient and worth retrying.
     */
    private function is_retryable_error(int $status): bool {
        // 429 = rate limited, 5xx = server errors.
        return $status === 429 || ($status >= 500 && $status < 600);
    }

    /**
     * Sleep before retrying with exponential backoff + jitter.
     *
     * @param int $attempt The current attempt number (1-based).
     */
    private function sleep_before_retry(int $attempt): void {
        $delayms = self::RETRY_BASE_DELAY_MS * (2 ** ($attempt - 1));
        // Add up to 25% random jitter to avoid thundering herd.
        $jitter = mt_rand(0, (int) ($delayms * 0.25));
        usleep(($delayms + $jitter) * 1000);
    }

    /**
     * Handle an error from the external AI api.
     *
     * Returns user-friendly error messages for common failure cases.
     *
     * @param ResponseInterface $response The response object.
     * @return array The error response.
     */
    protected function handle_api_error(ResponseInterface $response): array {
        $responsearr = [
            'success' => false,
            'errorcode' => $response->getStatusCode(),
        ];

        $status = $response->getStatusCode();
        $bodyraw = $response->getBody()->getContents();
        $bodyobj = json_decode($bodyraw);

        // Provide user-friendly messages for common errors.
        switch ($status) {
            case 401:
                $responsearr['errormessage'] = get_string('error_authfailed', 'aiprovider_pollinations');
                break;
            case 402:
                $responsearr['errormessage'] = get_string('error_paymentrequired', 'aiprovider_pollinations');
                break;
            case 403:
                $responsearr['errormessage'] = get_string('error_forbidden', 'aiprovider_pollinations');
                break;
            case 429:
                $responsearr['errormessage'] = get_string('error_ratelimit_exceeded', 'aiprovider_pollinations');
                break;
            case 400:
                $msg = $bodyobj->error->message ?? $bodyobj->message ?? get_string('error_badrequest_default', 'aiprovider_pollinations');
                $responsearr['errormessage'] = get_string('error_badrequest', 'aiprovider_pollinations', $msg);
                break;
            default:
                $responsearr['errormessage'] = $this->get_generic_error_message($status, $response, $bodyobj);
                break;
        }

        return $responsearr;
    }

    /**
     * Get a generic error message for non-standard HTTP status codes.
     *
     * @param int $status The HTTP status code.
     * @param ResponseInterface $response The response object.
     * @param mixed $bodyobj The decoded JSON body (or null).
     * @return string The error message.
     */
    private function get_generic_error_message(
        int $status,
        ResponseInterface $response,
        mixed $bodyobj,
    ): string {
        if ($status >= 500 && $status < 600) {
            return get_string(
                'error_servererror',
                'aiprovider_pollinations',
                ['status' => $status, 'phrase' => $response->getReasonPhrase()],
            );
        }
        if (isset($bodyobj->error->message)) {
            return $bodyobj->error->message;
        }
        if (json_last_error() === JSON_ERROR_NONE && isset($bodyobj->error)) {
            return json_encode($bodyobj->error);
        }
        return get_string(
            'error_httpgeneric',
            'aiprovider_pollinations',
            ['status' => $status, 'phrase' => $response->getReasonPhrase()],
        );
    }
}

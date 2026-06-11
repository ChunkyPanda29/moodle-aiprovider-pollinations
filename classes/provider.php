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

use core_ai\aiactions;
use core_ai\rate_limiter;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use core\http_client;

/**
 * Class provider.
 *
 * @package    aiprovider_pollinations
 * @copyright  2025 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /** @var string The Pollinations API key. */
    private string $apikey;

    /** @var string The BYOP publishable app key (optional). */
    private string $appkey;

    /** @var bool Is global rate limiting for the API enabled. */
    private bool $enableglobalratelimit;

    /** @var int The global rate limit. */
    private int $globalratelimit;

    /**
     * Class constructor.
     */
    public function __construct() {
        $this->apikey = get_config('aiprovider_pollinations', 'apikey');
        $this->appkey = get_config('aiprovider_pollinations', 'appkey') ?? '';
        $this->enableglobalratelimit = get_config('aiprovider_pollinations', 'enableglobalratelimit');
        $this->globalratelimit = get_config('aiprovider_pollinations', 'globalratelimit');
    }

    /**
     * Get the list of actions that this provider supports.
     *
     * @return array An array of action class names.
     */
    public function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\summarise_text::class,
        ];
    }

    /**
     * Generate a user id.
     *
     * This is a hash of the site id and user id,
     * this means we can determine who made the request
     * but don't pass any personal data to Pollinations.
     *
     * @param string $userid The user id.
     * @return string The generated user id.
     */
    public function generate_userid(string $userid): string {
        global $CFG;
        return hash('sha256', $CFG->siteidentifier . $userid);
    }

    /**
     * Update a request to add any headers required by the provider.
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\RequestInterface
     */
    public function add_authentication_headers(RequestInterface $request): RequestInterface {
        return $request
            ->withAddedHeader('Authorization', 'Bearer ' . $this->apikey);
    }

    #[\ReturnTypeWillChange]
    public function is_request_allowed(aiactions\base $action): array|bool {
        $ratelimiter = \core\di::get(rate_limiter::class);
        $component = \core\component::get_component_from_classname(get_class($this));

        // Check the global rate limit.
        if ($this->enableglobalratelimit) {
            if (!$ratelimiter->check_global_rate_limit(
                component: $component,
                ratelimit: $this->globalratelimit,
            )) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => 'Global rate limit exceeded',
                ];
            }
        }

        return true;
    }

    /**
     * Get any action settings for this provider.
     *
     * @param string $action The action class name.
     * @param \admin_root $ADMIN The admin root object.
     * @param string $section The section name.
     * @param bool $hassiteconfig Whether the current user has moodle/site:config capability.
     * @return array An array of settings.
     */
    public function get_action_settings(
        string $action,
        \admin_root $ADMIN,
        string $section,
        bool $hassiteconfig,
    ): array {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $settings = [];

        if ($actionname === 'generate_text' || $actionname === 'summarise_text') {
            // Model selector populated from cached Pollinations models.
            $settings[] = new \admin_setting_configselect(
                "aiprovider_pollinations/action_{$actionname}_model",
                new \lang_string("action:{$actionname}:model", 'aiprovider_pollinations'),
                new \lang_string("action:{$actionname}:model_desc", 'aiprovider_pollinations'),
                'openai',
                $this->get_all_models(),
            );

            // API endpoint.
            $settings[] = new \admin_setting_configtext(
                "aiprovider_pollinations/action_{$actionname}_endpoint",
                new \lang_string("action:{$actionname}:endpoint", 'aiprovider_pollinations'),
                '',
                'https://gen.pollinations.ai/v1/chat/completions',
                PARAM_URL,
            );

            // System instruction.
            $settings[] = new \admin_setting_configtextarea(
                "aiprovider_pollinations/action_{$actionname}_systeminstruction",
                new \lang_string("action:{$actionname}:systeminstruction", 'aiprovider_pollinations'),
                new \lang_string("action:{$actionname}:systeminstruction_desc", 'aiprovider_pollinations'),
                $action::get_system_instruction(),
                PARAM_TEXT,
            );
        }

        return $settings;
    }

    /**
     * Check this provider has the minimal configuration to work.
     *
     * @return bool Return true if configured.
     */
    public function is_provider_configured(): bool {
        return !empty($this->apikey);
    }

    /**
     * Get list of all Pollinations text models for the admin settings selector.
     *
     * Reads from the cached model data stored in plugin config.
     * Falls back to a live API call if no cached data exists.
     *
     * @return array List of models suitable for admin_setting_configselect.
     */
    private function get_all_models(): array {
        $cached = get_config('aiprovider_pollinations', 'cached_models');
        if (!empty($cached)) {
            $models = json_decode($cached, true);
            if (is_array($models) && !empty($models)) {
                return $models;
            }
        }

        // Try a live fetch if no cached data.
        return $this->fetch_models();
    }

    /**
     * Fetch the model list from the Pollinations API and cache it.
     *
     * @return array List of models suitable for admin_setting_configselect.
     */
    public function fetch_models(): array {
        $request = new Request(
            method: 'GET',
            uri: 'https://gen.pollinations.ai/text/models',
        );

        // Model listing does not require authentication, but include it if available.
        if (!empty($this->apikey)) {
            $request = $this->add_authentication_headers($request);
        }

        $client = \core\di::get(http_client::class);

        try {
            $response = $client->send($request);
            if ($response->getStatusCode() !== 200) {
                return [];
            }
            $body = json_decode($response->getBody()->getContents(), true);
            if (!is_array($body)) {
                return [];
            }

            $selectmodels = [];
            foreach ($body as $model) {
                $name = $model['name'] ?? $model['id'] ?? '';
                if (empty($name)) {
                    continue;
                }
                $brand = $model['brand'] ?? 'Unknown';
                $title = $model['title'] ?? $name;
                $inputs = implode(', ', $model['input_modalities'] ?? ['text']);
                $outputs = implode(', ', $model['output_modalities'] ?? ['text']);
                $display = "{$title} ({$brand}) — {$inputs} → {$outputs}";
                $selectmodels[$name] = $display;
            }

            // Cache the result for the select dropdown.
            set_config('cached_models', json_encode($selectmodels), 'aiprovider_pollinations');
            // Also cache the raw data for balance / info display.
            set_config('cached_models_raw', json_encode($body), 'aiprovider_pollinations');
            set_config('models_last_updated', time(), 'aiprovider_pollinations');

            return $selectmodels;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Fetch the current pollen balance from the Pollinations account API.
     *
     * @return array|null Balance data or null on failure.
     */
    public function fetch_balance(): ?array {
        if (empty($this->apikey)) {
            return null;
        }

        $request = new Request(
            method: 'GET',
            uri: 'https://gen.pollinations.ai/account/balance',
        );
        $request = $this->add_authentication_headers($request);

        $client = \core\di::get(http_client::class);

        try {
            $response = $client->send($request);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $body = json_decode($response->getBody()->getContents(), true);
            if (!is_array($body)) {
                return null;
            }

            // Cache the balance.
            set_config('cached_balance', json_encode($body), 'aiprovider_pollinations');

            return $body;
        } catch (\Exception $e) {
            return null;
        }
    }
}

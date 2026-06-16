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
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /**
     * Default BYOP publishable app key.
     *
     * This key is safe to ship in client-side code. It identifies this plugin
     * for the Pollinations BYOP (Bring Your Own Pollen) device flow and
     * earns the developer a 25% markup on all API usage.
     */
    public const DEFAULT_APP_KEY = 'pk_JpcODXmxY8ORHqe6';

    /** @var string The Pollinations text API endpoint. */
    public const TEXT_API_ENDPOINT = 'https://gen.pollinations.ai/v1/chat/completions';

    /** @var string The Pollinations image API base URL. */
    public const IMAGE_API_BASE = 'https://gen.pollinations.ai';

    /** @var string The Pollinations API key (sk_... obtained via BYOP or manual entry). */
    private string $apikey;

    /** @var bool Is global rate limiting for the API enabled. */
    private bool $enableglobalratelimit;

    /** @var int The global rate limit. */
    private int $globalratelimit;

    /** @var bool Is per-user rate limiting for the API enabled. */
    private bool $enableuserratelimit;

    /** @var int The per-user rate limit. */
    private int $userratelimit;

    /**
     * Class constructor.
     */
    public function __construct() {
        $this->apikey = get_config('aiprovider_pollinations', 'apikey') ?? '';
        $this->enableglobalratelimit = (bool) get_config('aiprovider_pollinations', 'enableglobalratelimit');
        $this->globalratelimit = (int) get_config('aiprovider_pollinations', 'globalratelimit');
        $this->enableuserratelimit = (bool) get_config('aiprovider_pollinations', 'enableuserratelimit');
        $this->userratelimit = (int) get_config('aiprovider_pollinations', 'userratelimit');
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
            \core_ai\aiactions\generate_image::class,
        ];
    }

    /**
     * Generate a user id.
     *
     * This is a hash of the site id and user id,
     * which means we can determine who made the request
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

    /**
     * Check whether a request is allowed based on rate limits.
     *
     * @param aiactions\base $action The action to check.
     * @return array|bool True if allowed, otherwise an array with error details.
     */
    #[\Override]
    public function is_request_allowed(aiactions\base $action): array|bool {
        $ratelimiter = \core\di::get(rate_limiter::class);
        $component = \core\component::get_component_from_classname(get_class($this));

        // Check the user rate limit.
        if ($this->enableuserratelimit) {
            $userallowed = $ratelimiter->check_user_rate_limit(
                component: $component,
                ratelimit: $this->userratelimit,
                userid: $action->get_configuration('userid'),
            );
            if (!$userallowed) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => get_string('error_userratelimit', 'aiprovider_pollinations'),
                ];
            }
        }

        // Check the global rate limit.
        if ($this->enableglobalratelimit) {
            $globallyallowed = $ratelimiter->check_global_rate_limit(
                component: $component,
                ratelimit: $this->globalratelimit,
            );
            if (!$globallyallowed) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => get_string('error_globalratelimit', 'aiprovider_pollinations'),
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
            // Model selector populated from cached Pollinations text models.
            $settings[] = new \admin_setting_configselect(
                "aiprovider_pollinations/action_{$actionname}_model",
                new \lang_string("action:{$actionname}:model", 'aiprovider_pollinations'),
                new \lang_string("action:{$actionname}:model_desc", 'aiprovider_pollinations'),
                'openai',
                $this->get_all_models('text'),
            );

            // System instruction.
            $settings[] = new \admin_setting_configtextarea(
                "aiprovider_pollinations/action_{$actionname}_systeminstruction",
                new \lang_string("action:{$actionname}:systeminstruction", 'aiprovider_pollinations'),
                new \lang_string("action:{$actionname}:systeminstruction_desc", 'aiprovider_pollinations'),
                $action::get_system_instruction(),
                PARAM_TEXT,
            );
        } else if ($actionname === 'generate_image') {
            // Model selector for image models.
            $settings[] = new \admin_setting_configselect(
                "aiprovider_pollinations/action_{$actionname}_model",
                new \lang_string("action:{$actionname}:model", 'aiprovider_pollinations'),
                new \lang_string("action:{$actionname}:model_desc", 'aiprovider_pollinations'),
                'flux',
                $this->get_all_models('image'),
            );

            // Optional seed for reproducible images.
            $settings[] = new \admin_setting_configtext(
                "aiprovider_pollinations/action_{$actionname}_seed",
                new \lang_string("action:{$actionname}:seed", 'aiprovider_pollinations'),
                new \lang_string("action:{$actionname}:seed_desc", 'aiprovider_pollinations'),
                '',
                PARAM_INT,
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
     * Get list of Pollinations models for the admin settings selector.
     *
     * Reads from the cached model data stored in plugin config.
     * Falls back to a live API call if no cached data exists.
     *
     * @param string $type The model type ('text' or 'image').
     * @return array List of models suitable for admin_setting_configselect.
     */
    private function get_all_models(string $type = 'text'): array {
        $cachekey = $type === 'image' ? 'cached_image_models' : 'cached_text_models';
        $cached = get_config('aiprovider_pollinations', $cachekey);
        if (!empty($cached)) {
            $models = json_decode($cached, true);
            if (is_array($models) && !empty($models)) {
                return $models;
            }
        }

        // Try a live fetch if no cached data.
        return $this->fetch_models($type);
    }

    /**
     * Fetch the model list from the Pollinations API and cache it.
     *
     * @param string $type The model type ('text', 'image', or 'all').
     * @return array List of models suitable for admin_setting_configselect.
     */
    public function fetch_models(string $type = 'text'): array {
        $client = \core\di::get(http_client::class);

        if ($type === 'image') {
            return $this->fetch_image_models($client);
        }

        return $this->fetch_text_models($client);
    }

    /**
     * Fetch text models from the Pollinations API.
     *
     * @param \core\http_client $client HTTP client instance.
     * @return array List of text models.
     */
    private function fetch_text_models(\core\http_client $client): array {
        $request = new Request(
            method: 'GET',
            uri: 'https://gen.pollinations.ai/text/models',
        );

        // Model listing does not require authentication, but include it if available.
        if (!empty($this->apikey)) {
            $request = $this->add_authentication_headers($request);
        }

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
                $capabilities = implode(', ', $model['capabilities'] ?? []);
                $pricing = $model['pricing'] ?? [];
                $pollensymbol = '🌸';
                $costinfo = '';
                if (!empty($pricing['completionTextTokens'])) {
                    $costper1m = round((float) $pricing['completionTextTokens'] * 1000000, 2);
                    $costinfo = " [{$pollensymbol}{$costper1m}/1M tokens]";
                }
                $paidonly = !empty($model['paid_only']) ? ' 💎' : '';
                $display = "{$title} ({$brand}) — {$inputs} → {$outputs}{$costinfo}{$paidonly}";
                $selectmodels[$name] = $display;
            }

            // Cache the result.
            set_config('cached_text_models', json_encode($selectmodels), 'aiprovider_pollinations');
            set_config('cached_text_models_raw', json_encode($body), 'aiprovider_pollinations');
            set_config('text_models_last_updated', time(), 'aiprovider_pollinations');

            return $selectmodels;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Fetch image models from the Pollinations API.
     *
     * @param \core\http_client $client HTTP client instance.
     * @return array List of image models.
     */
    private function fetch_image_models(\core\http_client $client): array {
        $request = new Request(
            method: 'GET',
            uri: 'https://gen.pollinations.ai/image/models',
        );

        // Model listing does not require authentication, but include it if available.
        if (!empty($this->apikey)) {
            $request = $this->add_authentication_headers($request);
        }

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
                $name = $model['name'] ?? '';
                if (empty($name)) {
                    continue;
                }
                $brand = $model['brand'] ?? 'Unknown';
                $title = $model['title'] ?? $name;
                $inputs = implode(', ', $model['input_modalities'] ?? ['text']);
                $pricing = $model['pricing'] ?? [];
                $pollensymbol = '🌸';
                $costinfo = '';
                if (!empty($pricing['completionImageTokens'])) {
                    $costperimg = round((float) $pricing['completionImageTokens'], 4);
                    $costinfo = " [{$pollensymbol}{$costperimg}/image]";
                }
                $paidonly = !empty($model['paid_only']) ? ' 💎' : '';
                $display = "{$title} ({$brand}) — {$inputs}{$costinfo}{$paidonly}";
                $selectmodels[$name] = $display;
            }

            // Cache the result.
            set_config('cached_image_models', json_encode($selectmodels), 'aiprovider_pollinations');
            set_config('cached_image_models_raw', json_encode($body), 'aiprovider_pollinations');
            set_config('image_models_last_updated', time(), 'aiprovider_pollinations');

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

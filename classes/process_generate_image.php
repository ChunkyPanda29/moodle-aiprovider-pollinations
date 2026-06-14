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

use core_ai\ai_image;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class process image generation.
 *
 * Pollinations image generation uses a simple GET endpoint:
 *   GET https://gen.pollinations.ai/image/{prompt}?model=flux&width=1024&height=1024&seed=123
 *
 * The response is raw image bytes (JPEG or PNG), not base64 JSON.
 * This differs from Gemini/OpenAI which return JSON with base64 data.
 *
 * @package    aiprovider_pollinations
 * @copyright  2026 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_image extends abstract_processor {
    /** @var int The number of images to generate. */
    private int $numberimages = 1;

    /**
     * Get the endpoint for the image generation API.
     *
     * @return UriInterface
     */
    #[\Override]
    protected function get_endpoint(): UriInterface {
        return new Uri(provider::IMAGE_API_BASE);
    }

    /**
     * Get the model to use for image generation.
     *
     * @return string
     */
    #[\Override]
    protected function get_model(): string {
        return get_config('aiprovider_pollinations', 'action_generate_image_model');
    }

    /**
     * Query the Pollinations image API and process the response.
     *
     * Override parent query_ai_api because Pollinations image generation
     * uses a GET request with URL parameters (not a POST with JSON body).
     * The response is raw image bytes, not JSON.
     */
    #[\Override]
    protected function query_ai_api(): array {
        // Build the full image URL with query parameters.
        $imageurl = $this->build_image_url();

        $request = new Request(
            method: 'GET',
            uri: $imageurl,
        );
        $request = $this->provider->add_authentication_headers($request);

        $client = \core\di::get(\core\http_client::class);

        try {
            $response = $client->send($request, [
                \GuzzleHttp\RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'success' => false,
                'errorcode' => $e->getCode(),
                'errormessage' => $e->getMessage(),
            ];
        }

        $status = $response->getStatusCode();
        if ($status === 200) {
            return $this->handle_api_success($response);
        } else {
            return $this->handle_api_error($response);
        }
    }

    /**
     * Build the Pollinations image generation URL with query parameters.
     *
     * Pollinations uses: GET /image/{prompt}?model=flux&width=W&height=H&seed=S&nologo=true
     *
     * @return string The fully qualified image generation URL.
     */
    private function build_image_url(): string {
        $baseurl = rtrim($this->get_endpoint()->__toString(), '/');
        $prompt = $this->action->get_configuration('prompttext');

        // Map Moodle's aspect ratio to Pollinations width/height.
        [$width, $height] = $this->calculate_dimensions(
            $this->action->get_configuration('aspectratio'),
            $this->action->get_configuration('quality'),
        );

        // Build query parameters.
        $params = [
            'model' => $this->get_model(),
            'width' => $width,
            'height' => $height,
            'nologo' => 'true',
        ];

        // Add seed if configured (allows reproducible images).
        $seed = get_config('aiprovider_pollinations', 'action_generate_image_seed');
        if (!empty($seed) && is_numeric($seed)) {
            $params['seed'] = (int) $seed;
        }

        // Add safety filters if any are enabled.
        $safetyfilters = [];
        if ((bool) get_config('aiprovider_pollinations', 'safety_privacy')) {
            $safetyfilters[] = 'privacy';
        }
        if ((bool) get_config('aiprovider_pollinations', 'safety_secrets')) {
            $safetyfilters[] = 'secrets';
        }
        if ((bool) get_config('aiprovider_pollinations', 'safety_nsfw')) {
            $safetyfilters[] = 'sexual,violence';
        }
        if (!empty($safetyfilters)) {
            $params['safe'] = implode(',', $safetyfilters);
        }

        $querystring = http_build_query($params);
        $encodedprompt = rawurlencode($prompt);

        return "{$baseurl}/image/{$encodedprompt}?{$querystring}";
    }

    /**
     * Calculate width and height based on Moodle's aspect ratio and quality settings.
     *
     * Moodle provides:
     * - aspectratio: 'square', 'landscape', 'portrait'
     * - quality: 'standard', 'hd'
     *
     * Pollinations accepts arbitrary width/height via query params.
     *
     * @param string $aspectratio The aspect ratio ('square', 'landscape', 'portrait').
     * @param string $quality The quality level ('standard', 'hd').
     * @return array [$width, $height]
     */
    private function calculate_dimensions(string $aspectratio, string $quality): array {
        // Base resolutions by quality.
        $base = ($quality === 'hd') ? 1536 : 1024;

        switch ($aspectratio) {
            case 'landscape':
                // 16:9 ratio.
                $width = $base;
                $height = (int) round($base * 9 / 16);
                break;
            case 'portrait':
                // 9:16 ratio.
                $width = (int) round($base * 9 / 16);
                $height = $base;
                break;
            case 'square':
            default:
                // 1:1 ratio.
                $width = $base;
                $height = $base;
                break;
        }

        // Ensure dimensions are even numbers (some image models prefer this).
        $width = $width - ($width % 2);
        $height = $height - ($height % 2);

        return [$width, $height];
    }

    /**
     * Handle a successful response from the Pollinations image API.
     *
     * Pollinations returns raw image bytes (not JSON).
     * We need to:
     * 1. Detect the image type from Content-Type header
     * 2. Save to Moodle's file storage (draft area)
     * 3. Apply the Moodle AI watermark
     *
     * @param ResponseInterface $response The response object containing raw image data.
     * @return array The response array with file information.
     */
    protected function handle_api_success(ResponseInterface $response): array {
        global $CFG;

        $imagebytes = (string) $response->getBody();

        // Determine mime type from Content-Type header, default to JPEG.
        $contenttype = $response->getHeaderLine('Content-Type');
        if (strpos($contenttype, 'image/png') !== false) {
            $extension = 'png';
        } else if (strpos($contenttype, 'image/webp') !== false) {
            $extension = 'webp';
        } else {
            $extension = 'jpg';
        }

        // Create a temporary file for the generated image.
        $filename = 'generatedimage_' . time() . '.' . $extension;
        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tempdst, $imagebytes);

        // Add the Moodle AI watermark.
        $image = new ai_image($tempdst);
        $image->add_watermark()->save();

        // Store in the user's draft file area.
        require_once("{$CFG->libdir}/filelib.php");

        $userid = $this->action->get_configuration('userid');
        $fileinfo = new \stdClass();
        $fileinfo->contextid = \context_user::instance($userid)->id;
        $fileinfo->filearea = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid = file_get_unused_draft_itemid();
        $fileinfo->filepath = '/';
        $fileinfo->filename = $filename;

        $fs = get_file_storage();
        $fileobj = $fs->create_file_from_string($fileinfo, file_get_contents($tempdst));

        return [
            'success' => true,
            'draftfile' => $fileobj,
        ];
    }

    /**
     * Create the request object.
     *
     * Note: This method is not used for image generation because we override
     * query_ai_api() to build a GET URL. However, it's required by the abstract class.
     *
     * @param string $userid The user id.
     * @return RequestInterface
     */
    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        // Not used — see overridden query_ai_api().
        return new Request(method: 'GET', uri: '');
    }
}

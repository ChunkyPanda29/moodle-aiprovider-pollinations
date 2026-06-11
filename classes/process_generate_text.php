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

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class process text generation.
 *
 * @package    aiprovider_pollinations
 * @copyright  2025 Krissy Painter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_text extends abstract_processor {
    #[\ReturnTypeWillChange]
    protected function get_endpoint(): UriInterface {
        return new Uri(get_config('aiprovider_pollinations', 'action_generate_text_endpoint'));
    }

    #[\ReturnTypeWillChange]
    protected function get_model(): string {
        return get_config('aiprovider_pollinations', 'action_generate_text_model');
    }

    #[\ReturnTypeWillChange]
    protected function get_system_instruction(): string {
        return get_config('aiprovider_pollinations', 'action_generate_text_systeminstruction');
    }

    #[\ReturnTypeWillChange]
    protected function create_request_object(string $userid): RequestInterface {
        /*
         * Pollinations uses an OpenAI-compatible chat completions API.
         *
         * POST /v1/chat/completions
         * {
         *     "model": "openai",
         *     "messages": [
         *         {"role": "system", "content": "..."},
         *         {"role": "user", "content": "..."}
         *     ]
         * }
         */

        $messages = [];

        // Add system instruction if available.
        $systeminstruction = $this->get_system_instruction();
        if (!empty($systeminstruction)) {
            $messages[] = [
                'role' => 'system',
                'content' => $systeminstruction,
            ];
        }

        // Add the user message.
        $messages[] = [
            'role' => 'user',
            'content' => $this->action->get_configuration('prompttext'),
        ];

        $requestobj = [
            'model' => $this->get_model(),
            'messages' => $messages,
        ];

        return new Request(
            method: 'POST',
            uri: '',
            body: json_encode($requestobj),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    /**
     * Handle a successful response from the Pollinations API.
     *
     * Response format (OpenAI-compatible):
     * {
     *     "choices": [{
     *         "message": {"content": "..."},
     *         "finish_reason": "stop"
     *     }],
     *     "usage": {"prompt_tokens": 10, "completion_tokens": 20}
     * }
     *
     * @param ResponseInterface $response The response object.
     * @return array The response.
     */
    protected function handle_api_success(ResponseInterface $response): array {
        $bodystring = (string) $response->getBody();
        $responsebody = json_decode($bodystring);

        $choice = $responsebody->choices[0] ?? null;
        $usage = $responsebody->usage ?? null;

        return [
            'success' => true,
            'id' => $responsebody->id ?? '',
            'generatedcontent' => $choice->message->content ?? '',
            'finishreason' => $choice->finish_reason ?? 'unknown',
            'prompttokens' => $usage->prompt_tokens ?? 0,
            'completiontokens' => $usage->completion_tokens ?? 0,
        ];
    }
}

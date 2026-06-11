# Pollinations AI Provider for Moodle

A Moodle AI provider plugin that integrates [Pollinations.ai](https://pollinations.ai) into the Moodle 4.5+ AI subsystem.

## Features

- **Text generation** — Use any Pollinations text model for AI-powered content generation
- **Text summarisation** — Summarise content using Pollinations models
- **OpenAI-compatible API** — Uses the standard `/v1/chat/completions` endpoint
- **Automatic model discovery** — Models are fetched daily from the Pollinations API
- **BYOP (Bring Your Own Pollen)** — Optional earn-as-you-go integration with 25% markup
- **Balance monitoring** — Scheduled task checks pollen balance and alerts admins when low
- **Rate limiting** — Configurable site-wide rate limits

## Requirements

- Moodle 4.5.0 or later (Build: 2024100700)
- PHP 8.0+
- A Pollinations API key (get one at [pollinations.ai](https://pollinations.ai))

## Installation

1. Clone or download this repository into `ai/provider/pollinations/` in your Moodle directory:
   ```bash
   cd /path/to/moodle/ai/provider/
   git clone https://github.com/ChunkyPanda29/moodle_aiprovider_pollinations.git pollinations
   ```

2. Visit your Moodle site as an administrator to complete the installation.

3. Go to **Site administration → Plugins → AI providers → Pollinations AI provider** and enter your API key.

## Configuration

### API Key

Enter your Pollinations secret key (`sk_...`) or publishable key (`pk_...`). Secret keys are recommended for server-side use as they have no rate limits and full access.

### BYOP App Key (Optional)

If you have a Bring Your Own Pollen (BYOP) publishable app key (`pk_...`), enter it to enable earnings. Users pay 25% over base rates and you receive 25% markup. Create an app key at [enter.pollinations.ai](https://enter.pollinations.ai) with `earningsEnabled: true`.

### Rate Limiting

Enable site-wide rate limiting to cap the number of requests per hour across the entire Moodle site.

### Per-Action Settings

Each action (generate text, summarise text) has its own settings:

- **Model** — Select from the list of available Pollinations text models (fetched automatically)
- **Endpoint** — The API endpoint URL (defaults to `https://gen.pollinations.ai/v1/chat/completions`)
- **System instruction** — The system prompt sent with each request

### Balance Monitoring

- **Low balance reminder threshold** — When the pollen balance drops below this value, admins are notified
- The balance is checked daily by a scheduled task

## Scheduled Tasks

| Task | Schedule | Description |
|------|----------|-------------|
| Update Pollinations model list | Daily at 03:00 | Fetches the latest model list from the Pollinations API |
| Check Pollinations pollen balance | Daily at 09:30 | Checks account balance and notifies admins if below threshold |

## API Compatibility

This plugin uses the Pollinations OpenAI-compatible endpoint:

```
POST https://gen.pollinations.ai/v1/chat/completions
Authorization: Bearer sk_...
Content-Type: application/json
```

Request format:
```json
{
  "model": "openai",
  "messages": [
    {"role": "system", "content": "You are a helpful assistant."},
    {"role": "user", "content": "Hello!"}
  ]
}
```

Response format:
```json
{
  "choices": [{
    "message": {"content": "..."},
    "finish_reason": "stop"
  }],
  "usage": {"prompt_tokens": 10, "completion_tokens": 20}
}
```

## Privacy

This plugin sends the following data to Pollinations:

- User prompt text
- System instruction
- Selected model name

No personal data is explicitly sent. User IDs are hashed before transmission. See the privacy provider for full details.

## License

GNU General Public License v3.0 or later. See [LICENSE](LICENSE) for details.

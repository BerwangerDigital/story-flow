<?php

namespace StoryFlow\AI;

class ExternalAPIStrategy implements OpenAIStrategyInterface {

    /**
     * @var string API endpoint.
     */
    private $endpoint;

    /**
     * @var string API token.
     */
    private $token;

    /**
     * Constructor.
     *
     * @param string $endpoint The API endpoint URL.
     * @param string $token The API token for authentication.
     */
    public function __construct(string $endpoint, string $token) {
        $this->endpoint = $endpoint;
        $this->token = $token;
    }

    public function processPrompt(string $prompt, array $options = []): string {
        $response = wp_remote_post($this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'prompt' => $prompt,
                'options' => $options,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ''; // Handle error gracefully
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return $body['result'] ?? '';
    }
}

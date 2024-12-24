<?php

namespace StoryFlow\AI;

use OpenAI\Client;

class DirectOpenAIStrategy implements OpenAIStrategyInterface {

    /**
     * @var Client OpenAI client instance.
     */
    private $client;

    /**
     * Constructor.
     *
     * @param string $api_key The OpenAI API key.
     */
    public function __construct(string $api_key) {
        $this->client = new Client(['api_key' => $api_key]);
    }

    public function processPrompt(string $prompt, array $options = []): string {
        $response = $this->client->completions()->create(array_merge([
            'model' => 'text-davinci-003',
            'prompt' => $prompt,
            'max_tokens' => 1000,
            'temperature' => 0.7,
        ], $options));

        return $response['choices'][0]['text'] ?? '';
    }
}

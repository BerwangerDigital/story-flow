<?php

namespace StoryFlow\Queue;

use OpenAI\Client as OpenAIClient;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Queue_Processor
 * Handles processing the queue for generating content.
 */
class Queue_Processor {

    private $queue_table;
    private $generated_content_table;
	private $pitch_table;
    private $openai_client;
	private $batch_size = 5;

    /**
     * Constructor.
     * Initializes database tables and the OpenAI client.
     */
    public function __construct() {
        global $wpdb;

        $this->queue_table = $wpdb->prefix . SF_TABLE_QUEUE;
		$this->pitch_table = $wpdb->prefix . SF_TABLE_PITCH_SUGGESTIONS;

		if (!CONFIGURATION__OPENAI__APIKEY) {
            throw new \Exception(__('OpenAI API key is not set.', SF_TEXTDOMAIN));
        }

		$this->openai_client = \OpenAI::client(CONFIGURATION__OPENAI__APIKEY);
    }

    /**
     * Processes the queue and generates content using OpenAI.
     */
    public function process_queue() {
        global $wpdb;

        // Fetch the next batch of items from the queue
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->queue_table} WHERE status = %s ORDER BY created_at ASC LIMIT %d",
                'pending',
                $this->batch_size
            )
        );

		// if (!$items) {
		// 	error_log(__('No items found in the queue.', SF_TEXTDOMAIN));
		// 	return; // Exit early if no items are found
		// }

        foreach ($items as $item) {
            try {
                // Mark the item as "processing"
                //$this->update_queue_status($item->id, 'processing');

				// Fetch the pitch details
				$pitch_details = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$this->pitch_table} WHERE id = %d",
						$item->pitch_id
					)
				);

				//if (!$pitch_details) {
				//	throw new \Exception(__('Pitch details not found.', SF_TEXTDOMAIN));
				//}

                // Fetch the prompt
                $prompt = $this->get_prompt($item->pitch_id);

                //if (!$prompt) {
                //    throw new \Exception(__('No valid prompt available.', SF_TEXTDOMAIN));
                //}

                // Generate content using OpenAI
                //$content = $this->send_to_openai_structured($prompt);
                $content = $this->curl_to_openai_structured($prompt);

                // Save the generated content
                //$this->save_generated_content($content, $item->pitch_id);

				//if (!$content) {
                //    throw new \Exception(__('Generated content is empty.', SF_TEXTDOMAIN));
                //}

                // Update statuses
                //$this->update_queue_status($item->id, 'completed');
				//$this->update_pitch_status($item->pitch_id, 'generated');

            } catch (\Exception $e) {
                // Handle failure
                //$this->update_queue_status($item->id, 'failed');
                //error_log(sprintf(__('Queue processing error: %s', SF_TEXTDOMAIN), $e->getMessage()));
            }
        }
    }

    /**
     * Combines logic to fetch the appropriate or default prompt for a pitch.
     *
     * @param int $pitch_id The pitch ID.
     * @return string|null The generated prompt with replaced placeholders, or null if not found.
     */
    private function get_prompt($pitch_id) {
        global $wpdb;

        // Retrieve pitch details
        $pitch = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT category, topic, suggested_pitch, main_seo_keyword FROM {$this->pitch_table} WHERE id = %d",
                $pitch_id
            )
        );

        if (!$pitch) {
            return null;
        }

        // Priority 1: Category + Topic
        $prompt_template = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT prompt FROM {$wpdb->prefix}" . SF_TABLE_PROMPTS . " WHERE category = %s AND topic = %s LIMIT 1",
                $pitch->category,
                $pitch->topic
            )
        );

        // Priority 2: Category
        if (!$prompt_template) {
            $prompt_template = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT prompt FROM {$wpdb->prefix}" . SF_TABLE_PROMPTS . " WHERE category = %s AND topic IS NULL LIMIT 1",
                    $pitch->category
                )
            );
        }

        // Use default prompt if no specific prompt is found
        if (!$prompt_template) {
            $prompt_template = get_option('story_flow_default_prompt', null);
        }

        if (!$prompt_template) {
            return null;
        }

        // Prepare data for placeholder replacement
        $data = [
            'pitch' => $pitch->suggested_pitch,
            'keywords' => $pitch->main_seo_keyword,
            'topic' => $pitch->topic,
			'category' => $pitch->category,
        ];

        // Replace placeholders in the prompt template
        return $this->prepare_prompt($prompt_template, $data);
    }

    /**
     * Updates the status of a pitch in the database.
     *
     * @param int $pitch_id The pitch ID.
     * @param string $status The new status.
     */
	private function update_pitch_status($pitch_id, $status) {
		global $wpdb;
		$wpdb->update(
			$this->pitch_table,
			['status' => $status, 'updated_at' => current_time('mysql')],
			['id' => $pitch_id],
			['%s', '%s'],
			['%d']
		);
	}

    /**
     * Updates the status of a queue item.
     *
     * @param int $queue_id The queue item ID.
     * @param string $status The new status.
     */
    private function update_queue_status($queue_id, $status) {
        global $wpdb;
        $wpdb->update(
            $this->queue_table,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $queue_id],
            ['%s', '%s'],
            ['%d']
        );
    }

	/**
	 * Replaces placeholders in the prompt template with the provided data.
	 *
	 * @param string $template The prompt template containing placeholders.
	 * @param array $data An associative array of placeholders and their corresponding values.
	 * @return string The prompt with placeholders replaced or the original template if no keys are found.
	 */
	private function prepare_prompt($template, $data) {
		// Verifica se há placeholders no template
		if (!preg_match('/{(\w+)}/', $template)) {
			return $template;
		}

		// Substitui os placeholders encontrados com os valores correspondentes
		return preg_replace_callback('/{(\w+)}/', function($matches) use ($data) {
			$key = $matches[1];
			return $data[$key] ?? "[MISSING: $key]";
		}, $template);
	}

    /**
     * Sends a structured prompt to OpenAI and retrieves the response.
     *
     * @param string $prompt The prompt text.
     * @return array|null The generated structured content from OpenAI.
     */
    private function curl_to_openai_structured($prompt) {
        $system_prompt = get_option('story_flow_default_prompt', '');

        $ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . CONFIGURATION__OPENAI__APIKEY,
			'Content-Type: application/json'
		]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_structured_output',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string', 'description' => 'Título do artigo.'],
                                'description' => ['type' => 'string', 'description' => 'Descrição do artigo otimizada para SEO.'],
                                'body' => ['type' => 'string', 'description' => 'Conteúdo do artigo.']
                            ],
                            'required' => [
                                'title',
                                'description',
                                'body'
                            ],
                            'additionalProperties' => false
                        ],
                        'strict' => true
                    ]
                ]
            ],
            'tool_choice' => [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_structured_output'
                ],
            ],
            'max_tokens' => 2500,
            'temperature' => 0.5,
            'top_p' => 0.9,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.3,
        ]));

        // Expected response:
        // {
        // "title":"Os Melhores Exercícios para Emagrecer em Casa: Guia Completo",
        // "description":"Descubra os melhores exercícios para emagrecer em casa e como incorporá-los na sua rotina para uma vida mais saudável e fitness.",
        // "body":"## Introdu\u00e7\u00e3o\n\nEm tempos de pandemia, a busca por exerc\u00edcios para emagrecer em casa tem crescido exponencialmente. Muitas pessoas est\u00e3o procurando maneiras de manter a forma e a sa\u00fade sem sair de casa. Mas qual \u00e9 o melhor exerc\u00edcio para emagrecer em casa?\n\n## Aer\u00f3bicos: Perda de peso eficiente\n\nOs exerc\u00edcios aer\u00f3bicos s\u00e3o uma excelente op\u00e7\u00e3o para quem deseja perder peso. Eles ajudam a aumentar o metabolismo e a queimar calorias, contribuindo para a perda de peso. Alguns exemplos de exerc\u00edcios aer\u00f3bicos que voc\u00ea pode fazer em casa incluem pular corda, dan\u00e7ar, correr no lugar e fazer polichinelos.\n\n## Treino de for\u00e7a: Constru\u00e7\u00e3o muscular\n\nEnquanto os exerc\u00edcios aer\u00f3bicos s\u00e3o \u00f3timos para queimar calorias, o treino de for\u00e7a \u00e9 essencial para construir e manter a massa muscular. Isso \u00e9 importante porque a massa muscular ajuda a aumentar o seu metabolismo, o que significa que voc\u00ea vai queimar mais calorias mesmo quando estiver em repouso. Exemplos de exerc\u00edcios de treino de for\u00e7a que voc\u00ea pode fazer em casa incluem flex\u00f5es, agachamentos e levantamento de peso.\n\n## Yoga e Pilates: Equil\u00edbrio e flexibilidade\n\nPara aqueles que preferem um ritmo mais lento, tanto o yoga quanto o pilates s\u00e3o \u00f3timas op\u00e7\u00f5es. Ambos s\u00e3o excelentes para melhorar o equil\u00edbrio e a flexibilidade, al\u00e9m de ajudarem a fortalecer os m\u00fasculos centrais. Eles tamb\u00e9m podem ajudar a aliviar o estresse, o que pode ser um fator contribuinte para o ganho de peso.\n\n## Conclus\u00e3o\n\nA chave para a perda de peso efetiva \u00e9 a consist\u00eancia. Escolha um ou mais exerc\u00edcios que voc\u00ea goste e se comprometa a faz\u00ea-los regularmente. Lembre-se, qualquer exerc\u00edcio \u00e9 melhor do que nenhum exerc\u00edcio. Comece devagar e gradualmente aumente a intensidade \u00e0 medida que fica mais forte. E lembre-se, sempre \u00e9 importante consultar um profissional de sa\u00fade antes de iniciar qualquer novo programa de exerc\u00edcios."
        // }

        // TODO: Como converter a propriedade body para o formato esperado pelo WordPress?

        $response = curl_exec($ch);

		if (curl_errno($ch)) {
			curl_close($ch);
			return false;
		}

		curl_close($ch);

		$response = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Decode Error: " . json_last_error_msg());
            return null;
        }

        $raw_arguments = $response['choices'][0]['message']['tool_calls'][0]['function']['arguments'];

        error_log("Raw Arguments: " . print_r($raw_arguments, true));

        $raw_arguments = json_encode($raw_arguments, true);

        $content = json_decode($raw_arguments, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Decode Error: " . json_last_error_msg());
            return null;
        }

        error_log("Generated Content JSON: " . print_r($content, true));

        return $content;
    }

    /**
     * Sends a structured prompt to OpenAI and retrieves the response.
     *
     * @param string $prompt The prompt text.
     * @return array|null The generated structured content from OpenAI.
     */
    private function send_to_openai_structured($prompt) {
        $system_prompt = get_option('story_flow_default_prompt', '');

        $response = $this->openai_client->chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            'tools' => [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'generate_structured_output',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string', 'description' => 'Título do artigo.'],
                                'description' => ['type' => 'string', 'description' => 'Descrição do artigo otimizada para SEO.'],
                                'body' => ['type' => 'string', 'description' => 'Conteúdo do artigo.']
                            ],
                            'required' => [
                                'title',
                                'description',
                                'body'
                            ],
                            'additionalProperties' => false
                        ],
                        'strict' => true
                    ]
                ]
            ],
            'tool_choice' => [
                'type' => 'function',
                'function' => [
                    'name' => 'generate_structured_output'
                ],
            ],
            'max_tokens' => 2500,
            'temperature' => 0.5,
            'top_p' => 0.9,
            'frequency_penalty' => 0.2,
            'presence_penalty' => 0.3,
        ]);

		$raw_arguments = $response->choices[0]->message->toolCalls[0]->function->arguments ?? null;

        if (!$raw_arguments) {
            return null;
        }

        // Usar saneamento avançado para remover caracteres inválidos
        //$sanitized_arguments = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $sanitized_arguments);

        // Remove BOM do início da string, se houver
        //$sanitized_arguments = preg_replace('/^\xEF\xBB\xBF/', '', $raw_arguments);

        // $sanitized_arguments = preg_replace(
        //     '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
        //     '',
        //     $raw_arguments
        // );

        error_log(var_dump($raw_arguments));

        $sanitized_arguments = utf8_encode($raw_arguments);
        $content = json_decode($sanitized_arguments, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Decode Error: " . json_last_error_msg());
            //error_log("Sanitized JSON: " . $raw_arguments);
            return null;
        }

        // Format body content into WordPress block editor format
        //$content['body'] = $this->format_body_to_blocks($content['body']);

        error_log("Generated Content: " . print_r($content, true));

        return $content;
    }

   /**
     * Formats the body content into WordPress block editor format.
     *
     * @param string $body The raw body content.
     * @return string The formatted content with blocks.
     */
    private function format_body_to_blocks($body) {
        $lines = explode("\n", trim($body));
        $formatted_body = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if (strpos($line, '##') === 0) {
                // Convert ## headings to H2 blocks
                $formatted_body .= "<!-- wp:heading {\"level\":2} -->\n" . substr($line, 2) . "\n<!-- /wp:heading -->\n";
            } else {
                // Convert plain text to paragraph blocks
                $formatted_body .= "<!-- wp:paragraph -->\n" . $line . "\n<!-- /wp:paragraph -->\n";
            }
        }

        return $formatted_body;
    }

	/**
	 * Saves the generated content as a normal post and links it to the pitch in the database.
	 *
	 * @param array $content The generated content.
	 * @param int $pitch_id The pitch ID associated with the content.
	 */
	private function save_generated_content($content, $pitch_id) {
		global $wpdb;

		if (!is_array($content)) {
			error_log('Failed to insert post: No generated content');
			return; // Exit early if no content is provided
		}

		// Check if the post was created successfully
		$post_id = wp_insert_post([
			'post_title'   => $content['title'],
			'post_content' => $content['body'],
			'post_excerpt' => $content['description'],
			'post_status'  => 'draft',
			'post_type'    => 'post',
			'post_author'  => get_option('story_flow_default_author')
		]);

		// Check if the post was created successfully
		if (is_wp_error($post_id)) {
			error_log('Failed to insert post: ' . $post_id->get_error_message());
			return;
		}
	}
}

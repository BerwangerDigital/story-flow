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

        foreach ($items as $item) {
            try {
                // Mark the item as "processing"
                $this->update_queue_status($item->id, 'processing');

				// Fetch the pitch details
				$pitch_details = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$this->pitch_table} WHERE id = %d",
						$item->pitch_id
					)
				);

				if (!$pitch_details) {
					throw new \Exception(__('Pitch details not found.', SF_TEXTDOMAIN));
				}

                // Fetch the prompt
                $prompt = $this->get_prompt_for_pitch($item->pitch_id) ?: $this->get_default_prompt($item->pitch_id);

                if (!$prompt) {
                    throw new \Exception(__('No valid prompt available.', SF_TEXTDOMAIN));
                }

                // Generate content using OpenAI
                $content = $this->send_to_openai($prompt);

                // Save the generated content
                $this->save_generated_content($content, $item->pitch_id);

				if (!$content) {
                    throw new \Exception(__('Generated content is empty.', SF_TEXTDOMAIN));
                }

                // Update statuses
                $this->update_queue_status($item->id, 'completed');
				$this->update_pitch_status($item->pitch_id, 'generated');

            } catch (\Exception $e) {
                // Handle failure
                $this->update_queue_status($item->id, 'failed');
                error_log(sprintf(__('Queue processing error: %s', SF_TEXTDOMAIN), $e->getMessage()));
            }
        }
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
	 * Retrieves the appropriate prompt for a pitch based on its category and topic.
	 *
	 * @param int $pitch_id The pitch ID.
	 * @return string|null The generated prompt with replaced placeholders, or null if not found.
	 */
	private function get_prompt_for_pitch($pitch_id) {
		global $wpdb;

		// Retrieve pitch details
		$pitch = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT category, topic, suggested_pitch, main_seo_keyword FROM {$wpdb->prefix}" . SF_TABLE_PITCH_SUGGESTIONS . " WHERE id = %d",
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

		if (!$prompt_template) {
			return null;
		}

		// Prepare data for placeholder replacement
		$data = [
			'pitch' => $pitch->suggested_pitch,
			'keywords' => $pitch->main_seo_keyword,
			'topic' => $pitch->topic,
		];

		// Replace placeholders in the prompt template
		return $this->prepare_prompt($prompt_template, $data);
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
     * Retrieves the default prompt from options.
     *
     * @return string|null The default prompt, or null if not set.
     */
    private function get_default_prompt($pitch_id) {
		global $wpdb;

		// Retrieve pitch details
		$pitch = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT category, topic, suggested_pitch, main_seo_keyword FROM {$wpdb->prefix}" . SF_TABLE_PITCH_SUGGESTIONS . " WHERE id = %d",
				$pitch_id
			)
		);

		if (!$pitch) {
			return null;
		}

		// Prepare data for placeholder replacement
		$data = [
			'pitch' => $pitch->suggested_pitch,
			'keywords' => $pitch->main_seo_keyword,
			'topic' => $pitch->topic,
		];

		$prompt_template = get_option('story_flow_default_prompt', null);

        return $this->prepare_prompt($prompt_template, $data);
    }

	/**
	 * Sends a prompt to OpenAI and retrieves the response.
	 *
	 * @param string $prompt The prompt text.
	 * @return string The generated content from OpenAI.
	 */
	private function send_to_openai($prompt) {

		$system_prompt = get_option('story_flow_default_prompt', '');

		// Step 1: Generate the body of the post
		$response_body = $this->openai_client->chat()->create([
			'model' => 'gpt-4',
			'messages' => [
				['role' => 'system', 'content' => $system_prompt],
				['role' => 'user', 'content' => 'Escreva somente o corpo da matéria em texto puro conforme as instruções: ' . $prompt],
			],
			'max_tokens' => 1500,
			'temperature' => 0.5,
			'top_p' => 0.9,
			'frequency_penalty' => 0.2,
			'presence_penalty' => 0.3,
		]);

		$body = $response_body['choices'][0]['message']['content'] ?? null;

		if (!$body) {
			return null; // Exit early if no body is generated
		}

		// Step 2: Generate the title based on the body
		$response_title = $this->openai_client->chat()->create([
			'model' => 'gpt-4',
			'messages' => [
				['role' => 'system', 'content' => 'Você é um assistente especializado em geração de títulos atrativos e claros para SEO'],
				['role' => 'assistant', 'content' => $body],
				['role' => 'user', 'content' => 'Com base no texto enviado, gere um título curto e claro para o artigo, que provoque a curiosidade do leitor. Utilize em caixa-alta apenas a primeira letra do título.'],
			],
			'max_tokens' => 250
		]);

		$title = $response_title['choices'][0]['message']['content'] ?? null;

		if (!$title) {
			return null; // Exit early if no title is generated
		}

		if (substr($title, 0, 1) === '"' || substr($title, 0, 1) === "'") {
			$title = substr($title, 1);
		}

		if (substr($title, -1) === '"' || substr($title, -1) === "'") {
			$title = substr($title, 0, -1);
		}

		// Step 3: Generate the SEO description based on the body
		$response_seo = $this->openai_client->chat()->create([
			'model' => 'gpt-4',
			'messages' => [
				['role' => 'system', 'content' => 'Você é um assistente especializado em descrições curtas e otimizadas para SEO.'],
				['role' => 'assistant', 'content' => $body],
				['role' => 'user', 'content' => 'Com base no texto acima, crie uma descrição otimizada para SEO com até 160 caracteres.'],
			]
		]);

		$seo_description = $response_seo['choices'][0]['message']['content'] ?? null;

		if (!$seo_description) {
			return null; // Exit early if no SEO description is generated
		}

		return [
			'title' => trim($title),
			'body' => trim($body),
			'seo_description' => trim($seo_description),
		];
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

		// Cria o post no WordPress
		$post_id = wp_insert_post([
			'post_title'   => $content['title'],
			'post_content' => $content['body'],
			'post_excerpt' => $content['seo_description'],
			'post_status'  => 'draft', // Salva como rascunho para revisão
			'post_type'    => 'post', // Tipo de post padrão
			'post_author'  => get_option('story_flow_default_author')
		]);

		// Verifica se o post foi criado com sucesso
		if (is_wp_error($post_id)) {
			error_log('Failed to insert post: ' . $post_id->get_error_message());
			return;
		}
	}
}

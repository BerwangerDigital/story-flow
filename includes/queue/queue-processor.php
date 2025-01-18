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
    private $openai_client;

    /**
     * Constructor.
     * Initializes database tables and the OpenAI client.
     */
    public function __construct() {
        global $wpdb;

        $this->queue_table = $wpdb->prefix . SF__TABLE_QUEUE;
        $this->generated_content_table = $wpdb->prefix . SF__TABLE_GENERATED_CONTENT;

        // $api_key = get_option('sf_openai_api_key'); // Assuming the OpenAI API key is stored in options
        //$api_key = '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';
		$api_key = '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';
		if (!$api_key) {
            throw new \Exception(__('OpenAI API key is not set.', 'story-flow'));
        }

        // $this->openai_client = OpenAIClient::factory([
        //     'api_key' => $api_key,
        // ]);

		//$this->openai_client = OpenAIClient::client($api_key, null, 'ai-sgm');

		$this->openai_client = \OpenAI::client($api_key);

    }

    /**
     * Processes the queue and generates content using OpenAI.
     */
    public function process_queue() {
        global $wpdb;

		error_log('Processing queue...');

        // Fetch the next batch of items from the queue
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->queue_table} WHERE status = %s ORDER BY created_at ASC LIMIT %d",
                'pending',
                1 // Process in batches of 5
            )
        );

		error_log('Items found: ' . count($items));

        foreach ($items as $item) {
            try {
                // Mark the item as "processing"
                //$this->update_queue_status($item->id, 'processing');

                // Retrieve prompt details
                $prompt = $this->get_prompt_for_pitch($item->pitch_id);
                if (!$prompt) {
                    $prompt = $this->get_default_prompt();
                }

                if (!$prompt) {
                    throw new \Exception(__('No valid prompt available for processing.', 'story-flow'));
                }

				error_log('Send prompt to OpenAI and get response...');
				error_log(print_r($prompt, true));

                // Send prompt to OpenAI and get response
                $response = $this->send_to_openai($prompt);

				error_log(print_r($response, true));
				error_log('Save generated content...');

                // Save the generated content
                $this->save_generated_content($response, $item->pitch_id);

                // Mark the item as "completed"
                //$this->update_queue_status($item->id, 'completed');
            } catch (\Exception $e) {
                // Handle failure
                //$this->update_queue_status($item->id, 'failed');
                error_log(sprintf(__('Queue processing error: %s', 'story-flow'), $e->getMessage()));
            }
        }
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
	 * Retrieves the appropriate prompt for a pitch based on its category or topic and replaces placeholders.
	 *
	 * @param int $pitch_id The pitch ID.
	 * @return string|null The generated prompt with replaced placeholders, or null if not found.
	 */
	private function get_prompt_for_pitch($pitch_id) {
		global $wpdb;

		// Retrieve pitch details
		$pitch = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT category, topic, suggested_pitch, main_seo_keyword FROM {$wpdb->prefix}" . SF__TABLE_PITCH_SUGGESTIONS . " WHERE id = %d",
				$pitch_id
			)
		);

		if (!$pitch) {
			return null;
		}

		// Retrieve the most specific prompt (category + topic, fallback to category only)
		$prompt_template = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT prompt FROM {$wpdb->prefix}" . SF__TABLE_PROMPTS . "
				WHERE category = %s AND (topic = %s OR topic IS NULL)
				ORDER BY topic DESC LIMIT 1",
				$pitch->category,
				$pitch->topic
			)
		);

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
    private function get_default_prompt() {
        return get_option('sf_default_prompt', null);
    }

	/**
	 * Sends a prompt to OpenAI and retrieves the response.
	 *
	 * @param string $prompt The prompt text.
	 * @return string The generated content from OpenAI.
	 */
	private function send_to_openai($prompt) {
		$response = $this->openai_client->chat()->create([
			'model' => 'gpt-3.5-turbo', // Use 'gpt-3.5-turbo' se preferir menor custo
			'messages' => [
				['role' => 'system', 'content' => 'Você é um assistente que escreve textos jornalísticos claros e informativos.'],
				['role' => 'user', 'content' => $prompt],
			],
			'max_tokens' => 1300,
			'temperature' => 0.5,
			'top_p' => 0.9,
			'frequency_penalty' => 0.2,
			'presence_penalty' => 0.3,
		]);

		return trim($response['choices'][0]['message']['content'] ?? '');

		// $response = $this->openai_client->completions()->create([
		// 	'model' => 'gpt-4',
		// 	'prompt' => $prompt,
		// 	'max_tokens' => 1300, // Sufficient for detailed articles
		// 	'temperature' => 0.5, // Lower for factual accuracy
		// 	'top_p' => 0.9, // Balanced variety
		// 	'frequency_penalty' => 0.2, // Reduce repetition
		// 	'presence_penalty' => 0.3, // Encourage topic expansion
		// ]);

		// return trim($response['choices'][0]['text'] ?? '');
	}


	/**
	 * Saves the generated content as a normal post and links it to the pitch in the database.
	 *
	 * @param string $content The generated content.
	 * @param int $pitch_id The pitch ID associated with the content.
	 */
	private function save_generated_content($content, $pitch_id) {
		global $wpdb;

		// Cria o post no WordPress
		$post_id = wp_insert_post([
			'post_title'   => wp_trim_words($content, 10, '...'), // Gera um título com as primeiras palavras do conteúdo
			'post_content' => $content,
			'post_status'  => 'draft', // Salva como rascunho para revisão
			'post_type'    => 'post', // Tipo de post padrão
			'post_author'  => get_current_user_id() ?: 0, // Define o autor como o usuário atual ou um ID padrão
		]);

		// Verifica se o post foi criado com sucesso
		if (is_wp_error($post_id)) {
			error_log('Failed to insert post: ' . $post_id->get_error_message());
			return;
		}

		// Adiciona a meta key `_generated_by_ai` com o valor `true`
		add_post_meta($post_id, '_generated_by_ai', 'true');

		// // Atualiza a tabela do pitch com o ID do post gerado
		// $pitch_table = $wpdb->prefix . 'sf_pitches';
		// $updated = $wpdb->update(
		// 	$pitch_table,
		// 	['generated_post_id' => $post_id], // Atualiza o campo com o ID do post gerado
		// 	['id' => $pitch_id], // Identifica o pitch correspondente
		// 	['%d'], // Formato do valor a ser atualizado
		// 	['%d']  // Formato do identificador
		// );

		// Verifica se a atualização foi bem-sucedida
		// if ($updated === false) {
		// 	error_log('Failed to update pitch with generated post ID.');
		// 	return;
		// }

		// (Opcional) Registra uma mensagem de log ou exibe uma notificação de sucesso
		error_log('Generated post saved successfully with ID: ' . $post_id . ' and linked to pitch ID: ' . $pitch_id);
	}

}

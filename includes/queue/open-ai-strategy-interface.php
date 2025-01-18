<?php

namespace StoryFlow\Queue;

use StoryFlow\AI\OpenAIStrategyInterface;

class Queue_Processor {

    /**
     * @var OpenAIStrategyInterface The strategy for connecting to OpenAI or external API.
     */
    private $strategy;

    /**
     * Constructor.
     *
     * @param OpenAIStrategyInterface $strategy The connection strategy.
     */
    public function __construct(OpenAIStrategyInterface $strategy) {
        $this->strategy = $strategy;
    }

    /**
     * Process the queue of AI generation tasks.
     *
     * @return void
     */
    public function processQueue() {
        global $wpdb;

        $table_name = $wpdb->prefix . SF__TABLE_QUEUE;
        $queue_items = $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE status = 'pending' ORDER BY created_at ASC LIMIT 10",
            ARRAY_A
        );

        if (empty($queue_items)) {
            return; // No pending items in the queue
        }

        foreach ($queue_items as $item) {
            $prompt = $this->buildPrompt($item);

            // Generate content using the current strategy
            $generated_content = $this->strategy->processPrompt($prompt);

            if (!empty($generated_content)) {
                // Update the database with the generated content
                $wpdb->update(
                    $table_name,
                    [
                        'status' => 'completed',
                        'result' => $generated_content,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $item['id']],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
            } else {
                // Mark as failed if no content is generated
                $wpdb->update(
                    $table_name,
                    [
                        'status' => 'failed',
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $item['id']],
                    ['%s', '%s'],
                    ['%d']
                );
            }
        }
    }

    /**
     * Build the prompt based on the queue item.
     *
     * @param array $item The queue item data.
     * @return string The generated prompt.
     */
    private function buildPrompt(array $item): string {
        // Example prompt generation logic
        return sprintf("Generate a detailed article based on the topic: %s", $item['topic'] ?? 'unspecified topic');
    }
}

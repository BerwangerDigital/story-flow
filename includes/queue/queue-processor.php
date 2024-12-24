<?php

namespace StoryFlow\Queue;

use StoryFlow\Services\AIContentGenerator;
use StoryFlow\Admin\Prompts\Prompt_Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Queue_Processor
 *
 * Handles the processing of queued items.
 */
class Queue_Processor {

    /**
     * Table name for the queue.
     *
     * @var string
     */
    private $queue_table;

    /**
     * AI Content Generator Service.
     *
     * @var AIContentGenerator
     */
    private $ai_generator;

    /**
     * Prompt Repository for retrieving prompts.
     *
     * @var Prompt_Repository
     */
    private $prompt_repository;

    /**
     * Constructor for the Queue_Processor class.
     *
     * @param AIContentGenerator $ai_generator Instance of the AI content generator.
     * @param Prompt_Repository  $prompt_repository Instance of the prompt repository.
     */
    public function __construct(AIContentGenerator $ai_generator, Prompt_Repository $prompt_repository) {
        global $wpdb;

        $this->queue_table = $wpdb->prefix . SF__TABLE_QUEUE;
        $this->ai_generator = $ai_generator;
        $this->prompt_repository = $prompt_repository;
    }

    /**
     * Process the next item in the queue.
     *
     * @return void
     */
    public function process_next() {
        global $wpdb;

        // Get the next pending item
        $item = $wpdb->get_row("
            SELECT *
            FROM {$this->queue_table}
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT 1
        ", ARRAY_A);

        if ( ! $item ) {
            return; // No items in the queue
        }

        $pitch_id = $item['pitch_id'];

        // Fetch pitch details (category and topic)
        $pitch = $this->get_pitch_details($pitch_id);

        if ( ! $pitch ) {
            $this->mark_as_failed($item['id'], __('Pitch not found.', 'story-flow'));
            return;
        }

        // Get the appropriate prompt based on category and topic
        $prompt = $this->prompt_repository->get_prompt_by_category_and_topic(
            $pitch['category'],
            $pitch['topic']
        );

        if ( ! $prompt ) {
            $this->mark_as_failed($item['id'], __('No matching prompt found.', 'story-flow'));
            return;
        }

        // Send the prompt to the AI generator
        $generated_content = $this->ai_generator->generate($prompt, $pitch);

        if ( ! $generated_content ) {
            $this->mark_as_failed($item['id'], __('Failed to generate content.', 'story-flow'));
            return;
        }

        // Save the generated content
        $this->save_generated_content($pitch_id, $generated_content);

        // Mark the queue item as completed
        $this->mark_as_completed($item['id']);
    }

    /**
     * Fetch details of a pitch by ID.
     *
     * @param int $pitch_id Pitch ID.
     *
     * @return array|null Pitch details or null if not found.
     */
    private function get_pitch_details($pitch_id) {
        global $wpdb;

        $pitch_table = $wpdb->prefix . SF__TABLE_PITCH_SUGGESTIONS;

        return $wpdb->get_row($wpdb->prepare("
            SELECT category, topic
            FROM {$pitch_table}
            WHERE id = %d
        ", $pitch_id), ARRAY_A);
    }

    /**
     * Save the generated content for a pitch.
     *
     * @param int    $pitch_id Pitch ID.
     * @param string $content  Generated content.
     *
     * @return void
     */
    private function save_generated_content($pitch_id, $content) {
        global $wpdb;

        $pitch_table = $wpdb->prefix . SF__TABLE_PITCH_SUGGESTIONS;

        $wpdb->update(
            $pitch_table,
            [
                'status'      => 'generated',
                'suggested_pitch' => $content,
                'updated_at'  => current_time('mysql'),
            ],
            ['id' => $pitch_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Mark a queue item as failed.
     *
     * @param int    $queue_id Queue item ID.
     * @param string $reason   Reason for failure.
     *
     * @return void
     */
    private function mark_as_failed($queue_id, $reason) {
        global $wpdb;

        $wpdb->update(
            $this->queue_table,
            [
                'status'      => 'failed',
                'updated_at'  => current_time('mysql'),
                'error_reason' => $reason,
            ],
            ['id' => $queue_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Mark a queue item as completed.
     *
     * @param int $queue_id Queue item ID.
     *
     * @return void
     */
    private function mark_as_completed($queue_id) {
        global $wpdb;

        $wpdb->update(
            $this->queue_table,
            [
                'status'     => 'completed',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $queue_id],
            ['%s', '%s'],
            ['%d']
        );
    }
}

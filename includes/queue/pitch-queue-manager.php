<?php

namespace StoryFlow\Queue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Pitch_Queue_Manager {

    /**
     * @var string The table name for the queue.
     */
    private $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;

        // Table name
        $this->table_name = $wpdb->prefix . SF_TABLE_QUEUE;
    }

    /**
     * Add a pitch to the queue if it doesn't already exist.
     *
     * @param int $pitch_id The ID of the pitch to add.
     * @return void
     */
    public function add_to_queue($pitch_id) {
        global $wpdb;

        // Check if the pitch already exists in the queue with pending status
        $exists = $this->checkIfExists($pitch_id, 'pending');

        if (! $exists) {
            $wpdb->insert(
                $this->table_name,
                [
                    'pitch_id'   => $pitch_id,
                    'status'     => 'pending',
                    'created_at' => current_time('mysql', 1),
                ],
                ['%d', '%s', '%s']
            );
        }
    }

    /**
     * Check if a pitch already exists in the queue with the given status.
     *
     * @param int $pitch_id The ID of the pitch.
     * @param string $status The status to check.
     * @return bool True if the record exists, false otherwise.
     */
    private function checkIfExists($pitch_id, $status): bool {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE pitch_id = %d AND status = %s",
                $pitch_id,
                $status
            )
        );

        return $count > 0;
    }

    /**
     * Add a pitch to the queue with priority.
     *
     * @param int $pitch_id The ID of the pitch to add.
     * @return void
     */
    public function add_to_queue_with_priority($pitch_id) {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE pitch_id = %d AND status = %s",
                $pitch_id,
                'pending'
            )
        );

        if (!$exists) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table_name} SET created_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE status = %s",
                    'pending'
                )
            );

            $wpdb->insert(
                $this->table_name,
                [
                    'pitch_id' => $pitch_id,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s']
            );
        }
    }

    /**
     * Checks for `assign` status and enqueues them.
     *
     * @return void
     */
    public function check_assign_and_enqueue() {
        global $wpdb;
        $pitch_table = $wpdb->prefix . SF_TABLE_PITCH_SUGGESTIONS;

        $assignments = $wpdb->get_results(
            $wpdb->prepare( "SELECT id FROM $pitch_table WHERE status = %s", 'assign' )
        );

        foreach ( $assignments as $assignment ) {
            $this->add_to_queue( $assignment->id );

            // Update the status to processing
            $wpdb->update( $pitch_table, [ 'status' => 'processing' ], [ 'id' => $assignment->id ] );
        }
    }
}

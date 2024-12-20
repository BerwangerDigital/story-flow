<?php

namespace StoryFlow\API;

class API_Assignments {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'story-flow/v1', '/assignments', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_assignments' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'story-flow/v1', '/assignments', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'create_assignment' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( 'story-flow/v1', '/assignments/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_assignment' ],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_assignments() {
        $db = new \StoryFlow\Database\DB_Assignments();
        return $db->get();
    }

    public function create_assignment( $request ) {
        $db = new \StoryFlow\Database\DB_Assignments();
        return $db->insert( $request->get_json_params() );
    }

    public function delete_assignment( $request ) {
        $db = new \StoryFlow\Database\DB_Assignments();
        return $db->delete( $request['id'] );
    }
}

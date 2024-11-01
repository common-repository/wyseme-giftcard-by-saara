<?php

class WYSEME_STATUS_API extends WP_REST_Controller
{
    public $namespace;
    public $status_resource;

    // Here initialize our namespace and resource name.
    public function __construct() {
        $this->namespace         = '/wyseme/v1';
        $this->status_resource    = 'status';
    }

    // Register our routes.
    public function init() {

        register_rest_route(
            $this->namespace, '/' . $this->status_resource, array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_status' ),
                    'args'                => $this->get_collection_params(),
                ),
            )
        );
    }

    /**
     * Check permissions for the posts.
     *
     * @param WP_REST_Request $request Current request.
     */
    public function get_items_permissions_check( $request ) {
        if ( ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'You cannot view the post resource.' ), array( 'status' => $this->authorization_status_code() ) );
        }
        return true;
    }

    /**
     * @param $request
     * @return WP_Error|WP_HTTP_Response|WP_REST_Response
     */
    public function get_status( $request ) {
        
        $status['data']['status'] = true;
        return rest_ensure_response($status);
    }
    
    // Sets up the proper HTTP status code for authorization.
    public function authorization_status_code() {

        $status = 401;
    
        if ( is_user_logged_in() ) {
            $status = 403;
        }
    
        return $status;
    }
}

<?php
/**
 * REST API for Jejak Journal.
 *
 * @package JejakJournal
 */

namespace JejakJournal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', __NAMESPACE__ . '\\rest_register_routes' );

/**
 * Register REST routes.
 */
function rest_register_routes() {
	register_rest_route(
		'jejak-journal/v1',
		'/entry/(?P<year>\d{4})/(?P<month>\d{1,2})',
		array(
			'methods'             => 'GET',
			'callback'            => __NAMESPACE__ . '\\rest_get_entry',
			'permission_callback' => __NAMESPACE__ . '\\rest_permission',
			'args'                => array(
				'year'  => array(
					'required' => true,
					'type'     => 'integer',
				),
				'month' => array(
					'required' => true,
					'type'     => 'integer',
				),
			),
		)
	);

	register_rest_route(
		'jejak-journal/v1',
		'/entry/(?P<year>\d{4})/(?P<month>\d{1,2})',
		array(
			'methods'             => 'POST',
			'callback'            => __NAMESPACE__ . '\\rest_create_entry',
			'permission_callback' => __NAMESPACE__ . '\\rest_permission',
		)
	);

	register_rest_route(
		'jejak-journal/v1',
		'/entry/(?P<id>\d+)/highlights',
		array(
			'methods'             => 'PUT',
			'callback'            => __NAMESPACE__ . '\\rest_update_highlights',
			'permission_callback' => __NAMESPACE__ . '\\rest_permission',
		)
	);

	register_rest_route(
		'jejak-journal/v1',
		'/entry/(?P<id>\d+)/todos',
		array(
			'methods'             => 'PUT',
			'callback'            => __NAMESPACE__ . '\\rest_update_todos',
			'permission_callback' => __NAMESPACE__ . '\\rest_permission',
		)
	);

	register_rest_route(
		'jejak-journal/v1',
		'/entry/(?P<id>\d+)/notes',
		array(
			'methods'             => 'PUT',
			'callback'            => __NAMESPACE__ . '\\rest_update_notes',
			'permission_callback' => __NAMESPACE__ . '\\rest_permission',
		)
	);
}

/**
 * Permission check: user must be logged in and have capability.
 *
 * @return bool|\WP_Error
 */
function rest_permission() {
	if ( ! is_user_logged_in() ) {
		return new \WP_Error( 'rest_not_logged_in', __( 'You must be logged in.', 'jejak-journal' ), array( 'status' => 401 ) );
	}
	if ( ! DB::user_can_manage() ) {
		return new \WP_Error( 'rest_no_permission', __( 'You do not have permission.', 'jejak-journal' ), array( 'status' => 403 ) );
	}
	return true;
}

/**
 * GET /jejak-journal/v1/entry/{year}/{month}
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_get_entry( $request ) {
	$user_id = get_current_user_id();
	$year    = (int) $request['year'];
	$month   = (int) $request['month'];

	$entry = DB::get_entry( $user_id, $year, $month );
	if ( ! $entry ) {
		return new \WP_Error( 'not_found', __( 'No entry for this month.', 'jejak-journal' ), array( 'status' => 404 ) );
	}

	return rest_ensure_response( $entry );
}

/**
 * POST /jejak-journal/v1/entry/{year}/{month}
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_create_entry( $request ) {
	$user_id = get_current_user_id();
	$year    = (int) $request['year'];
	$month   = (int) $request['month'];

	$existing = DB::get_entry( $user_id, $year, $month );
	if ( $existing ) {
		return rest_ensure_response( $existing );
	}

	$entry = DB::create_entry( $user_id, $year, $month );
	if ( ! $entry ) {
		return new \WP_Error( 'create_failed', __( 'Could not create entry.', 'jejak-journal' ), array( 'status' => 500 ) );
	}

	return rest_ensure_response( $entry );
}

/**
 * PUT /jejak-journal/v1/entry/{id}/highlights
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_update_highlights( $request ) {
	$entry_id = (int) $request['id'];
	$body     = $request->get_json_params();

	if ( ! is_array( $body ) || ! isset( $body['data'] ) ) {
		return new \WP_Error( 'invalid_data', __( 'Invalid highlights data.', 'jejak-journal' ), array( 'status' => 400 ) );
	}

	$highlights = $body['data'];
	$updated_at = $body['updated_at'] ?? null;

	if ( ! is_array( $highlights ) ) {
		return new \WP_Error( 'invalid_data', __( 'Invalid highlights data.', 'jejak-journal' ), array( 'status' => 400 ) );
	}

	$affected = DB::update_highlights( $entry_id, $highlights, $updated_at );

	if ( false === $affected ) {
		return new \WP_Error( 'db_error', __( 'Database error.', 'jejak-journal' ), array( 'status' => 500 ) );
	}

	if ( $updated_at && 0 === $affected ) {
		return new \WP_Error(
			'conflict',
			__( 'This entry was updated on another device. Please reload.', 'jejak-journal' ),
			array( 'status' => 409 )
		);
	}

	$new_updated_at = DB::get_updated_at( $entry_id );

	return rest_ensure_response(
		array(
			'success'    => true,
			'updated_at' => $new_updated_at,
		)
	);
}

/**
 * PUT /jejak-journal/v1/entry/{id}/todos
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_update_todos( $request ) {
	$entry_id = (int) $request['id'];
	$body     = $request->get_json_params();

	if ( ! is_array( $body ) || ! isset( $body['data'] ) ) {
		return new \WP_Error( 'invalid_data', __( 'Invalid todos data.', 'jejak-journal' ), array( 'status' => 400 ) );
	}

	$todos      = $body['data'];
	$updated_at = $body['updated_at'] ?? null;

	if ( ! is_array( $todos ) ) {
		return new \WP_Error( 'invalid_data', __( 'Invalid todos data.', 'jejak-journal' ), array( 'status' => 400 ) );
	}

	$affected = DB::update_todos( $entry_id, $todos, $updated_at );

	if ( false === $affected ) {
		return new \WP_Error( 'db_error', __( 'Database error.', 'jejak-journal' ), array( 'status' => 500 ) );
	}

	if ( $updated_at && 0 === $affected ) {
		return new \WP_Error(
			'conflict',
			__( 'This entry was updated on another device. Please reload.', 'jejak-journal' ),
			array( 'status' => 409 )
		);
	}

	$new_updated_at = DB::get_updated_at( $entry_id );

	return rest_ensure_response(
		array(
			'success'    => true,
			'updated_at' => $new_updated_at,
		)
	);
}

/**
 * PUT /jejak-journal/v1/entry/{id}/notes
 *
 * @param \WP_REST_Request $request Request.
 * @return \WP_REST_Response|\WP_Error
 */
function rest_update_notes( $request ) {
	$entry_id = (int) $request['id'];
	$body     = $request->get_json_params();

	if ( ! is_array( $body ) || ! isset( $body['data'] ) ) {
		return new \WP_Error( 'invalid_data', __( 'Invalid notes data.', 'jejak-journal' ), array( 'status' => 400 ) );
	}

	$notes      = $body['data'];
	$updated_at = $body['updated_at'] ?? null;

	if ( ! is_array( $notes ) ) {
		return new \WP_Error( 'invalid_data', __( 'Invalid notes data.', 'jejak-journal' ), array( 'status' => 400 ) );
	}

	$affected = DB::update_journal_notes( $entry_id, $notes, $updated_at );

	if ( false === $affected ) {
		return new \WP_Error( 'db_error', __( 'Database error.', 'jejak-journal' ), array( 'status' => 500 ) );
	}

	if ( $updated_at && 0 === $affected ) {
		return new \WP_Error(
			'conflict',
			__( 'This entry was updated on another device. Please reload.', 'jejak-journal' ),
			array( 'status' => 409 )
		);
	}

	$new_updated_at = DB::get_updated_at( $entry_id );

	return rest_ensure_response(
		array(
			'success'    => true,
			'updated_at' => $new_updated_at,
		)
	);
}

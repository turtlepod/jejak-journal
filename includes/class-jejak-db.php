<?php
/**
 * Database schema and CRUD for Jejak Journal.
 *
 * @package JejakJournal
 */

namespace JejakJournal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database handler class.
 */
class DB {

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	/**
	 * DB version for schema migrations.
	 *
	 * @var int
	 */
	const DB_VERSION = 1;

	/**
	 * Install/upgrade database tables.
	 */
	public static function install() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Journal entries table (post type backed by wp_posts, meta stored here).
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jejak_journal_entries (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			year INT(4) UNSIGNED NOT NULL,
			month TINYINT(2) UNSIGNED NOT NULL,
			slug VARCHAR(50) NOT NULL,
			highlights LONGTEXT DEFAULT NULL,
			todos LONGTEXT DEFAULT NULL,
			journal_notes LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY user_month (user_id, year, month),
			KEY user_id (user_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'jejak_journal_db_version', self::DB_VERSION );
	}

	/**
	 * Get or create a journal entry for a user + year + month.
	 *
	 * @param int $user_id User ID.
	 * @param int $year    Year.
	 * @param int $month   Month (1-12).
	 * @return array|null
	 */
	public static function get_entry( $user_id, $year, $month ) {
		global $wpdb;

		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}jejak_journal_entries WHERE user_id = %d AND year = %d AND month = %d",
				$user_id,
				$year,
				$month
			),
			ARRAY_A
		);

		if ( $entry ) {
			$entry['highlights']    = $entry['highlights'] ? json_decode( $entry['highlights'], true ) : array();
			$entry['todos']         = $entry['todos'] ? json_decode( $entry['todos'], true ) : array();
			$entry['journal_notes'] = $entry['journal_notes'] ? json_decode( $entry['journal_notes'], true ) : self::generate_empty_journal( (int) $month, (int) $year );
			return $entry;
		}

		return null;
	}

	/**
	 * Get the current updated_at timestamp for an entry.
	 *
	 * @param int $id Entry ID.
	 * @return string|null
	 */
	public static function get_updated_at( $id ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT updated_at FROM {$wpdb->prefix}jejak_journal_entries WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Create a new journal entry.
	 *
	 * @param int $user_id User ID.
	 * @param int $year    Year.
	 * @param int $month   Month (1-12).
	 * @return array|null Created entry or null.
	 */
	public static function create_entry( $user_id, $year, $month ) {
		global $wpdb;
		$table = $wpdb->prefix . 'jejak_journal_entries';

		$month_name = gmdate( 'F', mktime( 0, 0, 0, $month, 1, 2000 ) );
		$slug       = 'jj-' . strtolower( $month_name ) . '-' . $year;
		$journal    = self::generate_empty_journal( $month, $year );

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'       => $user_id,
				'year'          => $year,
				'month'         => $month,
				'slug'          => $slug,
				'highlights'    => wp_json_encode( array() ),
				'todos'         => wp_json_encode( array() ),
				'journal_notes' => wp_json_encode( $journal ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return null;
		}

		return self::get_entry( $user_id, $year, $month );
	}

	/**
	 * Update highlights for an entry — atomic with optimistic locking.
	 *
	 * @param int         $entry_id   Entry ID.
	 * @param array       $highlights Array of highlight items.
	 * @param string|null $updated_at Client's last-known updated_at for lock check.
	 * @return int Rows affected (0 = conflict if updated_at was provided).
	 */
	public static function update_highlights( $entry_id, $highlights, $updated_at = null ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $updated_at ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}jejak_journal_entries SET highlights = %s WHERE id = %d AND updated_at = %s",
					wp_json_encode( $highlights ),
					$entry_id,
					$updated_at
				)
			);
			return false === $result ? false : (int) $result;
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}jejak_journal_entries SET highlights = %s WHERE id = %d",
				wp_json_encode( $highlights ),
				$entry_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false === $result ? false : (int) $result;
	}

	/**
	 * Update todos for an entry — atomic with optimistic locking.
	 *
	 * @param int         $entry_id   Entry ID.
	 * @param array       $todos      Array of todo items.
	 * @param string|null $updated_at Client's last-known updated_at for lock check.
	 * @return int Rows affected (0 = conflict if updated_at was provided).
	 */
	public static function update_todos( $entry_id, $todos, $updated_at = null ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $updated_at ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}jejak_journal_entries SET todos = %s WHERE id = %d AND updated_at = %s",
					wp_json_encode( $todos ),
					$entry_id,
					$updated_at
				)
			);
			return false === $result ? false : (int) $result;
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}jejak_journal_entries SET todos = %s WHERE id = %d",
				wp_json_encode( $todos ),
				$entry_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false === $result ? false : (int) $result;
	}

	/**
	 * Update journal notes for an entry — atomic with optimistic locking.
	 *
	 * @param int         $entry_id      Entry ID.
	 * @param array       $journal_notes Array of journal day entries.
	 * @param string|null $updated_at    Client's last-known updated_at for lock check.
	 * @return int|false Rows affected, or false on SQL error.
	 */
	public static function update_journal_notes( $entry_id, $journal_notes, $updated_at = null ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $updated_at ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}jejak_journal_entries SET journal_notes = %s WHERE id = %d AND updated_at = %s",
					wp_json_encode( $journal_notes ),
					$entry_id,
					$updated_at
				)
			);
			return false === $result ? false : (int) $result;
		}

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}jejak_journal_entries SET journal_notes = %s WHERE id = %d",
				wp_json_encode( $journal_notes ),
				$entry_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false === $result ? false : (int) $result;
	}

	/**
	 * Generate empty journal structure for a month.
	 *
	 * @param int $month Month.
	 * @param int $year  Year.
	 * @return array
	 */
	public static function generate_empty_journal( $month, $year ) {
		$days_in_month = (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) );
		$journal       = array();

		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$timestamp = mktime( 0, 0, 0, $month, $day, $year );
			$day_name  = gmdate( 'D', $timestamp );
			$date_str  = gmdate( 'Y-m-d', $timestamp );
			$journal[] = array(
				'day'      => $day,
				'date'     => $date_str,
				'day_name' => $day_name,
				'notes'    => '',
			);
		}

		return $journal;
	}

	/**
	 * Get allowed roles from settings.
	 *
	 * @return array
	 */
	public static function get_allowed_roles() {
		$roles = get_option( 'jejak_journal_roles', array( 'administrator' ) );
		if ( ! is_array( $roles ) ) {
			$roles = array( 'administrator' );
		}
		return $roles;
	}

	/**
	 * Get all entries for a user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_all_entries( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}jejak_journal_entries WHERE user_id = %d ORDER BY year ASC, month ASC",
				$user_id
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['highlights']    = $row['highlights'] ? json_decode( $row['highlights'], true ) : array();
			$row['todos']         = $row['todos'] ? json_decode( $row['todos'], true ) : array();
			$row['journal_notes'] = $row['journal_notes'] ? json_decode( $row['journal_notes'], true ) : array();
		}

		return $rows;
	}

	/**
	 * Upsert an entry (create or update).
	 *
	 * @param int   $user_id User ID.
	 * @param int   $year    Year.
	 * @param int   $month   Month.
	 * @param array $data    Entry data (highlights, todos, journal_notes).
	 * @return bool
	 */
	public static function upsert_entry( $user_id, $year, $month, $data ) {
		global $wpdb;
		$table      = $wpdb->prefix . 'jejak_journal_entries';
		$existing   = self::get_entry( $user_id, $year, $month );
		$month_name = gmdate( 'F', mktime( 0, 0, 0, $month, 1, 2000 ) );
		$slug       = 'jj-' . strtolower( $month_name ) . '-' . $year;

		$values = array(
			'highlights'    => isset( $data['highlights'] ) ? wp_json_encode( $data['highlights'] ) : '[]',
			'todos'         => isset( $data['todos'] ) ? wp_json_encode( $data['todos'] ) : '[]',
			'journal_notes' => isset( $data['journal_notes'] ) ? wp_json_encode( $data['journal_notes'] ) : '[]',
		);

		if ( $existing ) {
			return (bool) $wpdb->update(
				$table,
				$values,
				array( 'id' => $existing['id'] ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		return (bool) $wpdb->insert(
			$table,
			array_merge(
				array(
					'user_id' => $user_id,
					'year'    => $year,
					'month'   => $month,
					'slug'    => $slug,
				),
				$values
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Check if a user can manage journals.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_can_manage( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		// Always allow administrators.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$user    = get_userdata( $user_id );
		$roles   = $user ? $user->roles : array();
		$allowed = self::get_allowed_roles();

		return ! empty( array_intersect( $roles, $allowed ) );
	}
}

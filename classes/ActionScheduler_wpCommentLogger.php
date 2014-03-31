<?php

/**
 * Class ActionScheduler_wpCommentLogger
 */
class ActionScheduler_wpCommentLogger extends ActionScheduler_Logger {
	const AGENT = 'ActionScheduler';
	const TYPE = 'action_log';

	/**
	 * @param string $action_id
	 * @param string $message
	 * @param DateTime $date
	 *
	 * @return string The log entry ID
	 */
	public function log( $action_id, $message, DateTime $date = NULL ) {
		if ( empty($date) ) {
			$date = new DateTime();
		} else {
			$date = clone( $date );
		}
		$comment_id = $this->create_wp_comment( $action_id, $message, $date );
		return $comment_id;
	}

	protected function create_wp_comment( $action_id, $message, DateTime $date ) {
		$date->setTimezone( ActionScheduler_TimezoneHelper::get_local_timezone() );
		$comment_data = array(
			'comment_post_ID' => $action_id,
			'comment_date' => $date->format('Y-m-d H:i:s'),
			'comment_author' => self::AGENT,
			'comment_content' => $message,
			'comment_agent' => self::AGENT,
			'comment_type' => self::TYPE,
		);
		return wp_insert_comment($comment_data);
	}

	/**
	 * @param string $entry_id
	 *
	 * @return ActionScheduler_LogEntry
	 */
	public function get_entry( $entry_id ) {
		$comment = $this->get_comment( $entry_id );
		if ( empty($comment) || $comment->comment_type != self::TYPE ) {
			return new ActionScheduler_NullLogEntry();
		}
		return new ActionScheduler_LogEntry( $comment->comment_post_ID, $comment->comment_content, $comment->comment_type );
	}

	/**
	 * @param string $action_id
	 *
	 * @return ActionScheduler_LogEntry[]
	 */
	public function get_logs( $action_id ) {
		$status = 'all';
		if ( get_post_status($action_id) == 'trash' ) {
			$status = 'post-trashed';
		}
		$comments = get_comments(array(
			'post_id' => $action_id,
			'orderby' => 'comment_date_gmt',
			'order' => 'ASC',
			'type' => self::TYPE,
			'status' => $status,
		));
		$logs = array();
		foreach ( $comments as $c ) {
			$entry = $this->get_entry( $c );
			if ( !empty($entry) ) {
				$logs[] = $entry;
			}
		}
		return $logs;
	}

	protected function get_comment( $comment_id ) {
		return get_comment( $comment_id );
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function init() {
		add_action( 'action_scheduler_before_process_queue', array( $this, 'disable_comment_counting' ), 10, 0 );
		add_action( 'action_scheduler_after_process_queue', array( $this, 'enable_comment_counting' ), 10, 0 );
		add_action( 'action_scheduler_stored_action', array( $this, 'log_stored_action' ), 10, 1 );
		add_action( 'action_scheduler_canceled_action', array( $this, 'log_canceled_action' ), 10, 1 );
		add_action( 'action_scheduler_before_execute', array( $this, 'log_started_action' ), 10, 1 );
		add_action( 'action_scheduler_after_execute', array( $this, 'log_completed_action' ), 10, 1 );
		add_action( 'action_scheduler_failed_execution', array( $this, 'log_failed_action' ), 10, 2 );
		add_action( 'action_scheduler_reset_action', array( $this, 'log_reset_action' ), 10, 1 );

		add_filter( 'comments_clauses', array( $this, 'exclude_comments' ), 11, 1 ); // hook after WC_Comments::exclude_order_comments()
		add_action( 'comment_feed_where', array( $this, 'exclude_comments_from_feed' ) );
	}

	public function disable_comment_counting() {
		wp_defer_comment_counting(true);
	}
	public function enable_comment_counting() {
		wp_defer_comment_counting(false);
	}

	public function log_stored_action( $action_id ) {
		$this->log( $action_id, __('action created', 'action-scheduler') );
	}

	public function log_canceled_action( $action_id ) {
		$this->log( $action_id, __('action canceled', 'action-scheduler') );
	}

	public function log_started_action( $action_id ) {
		$this->log( $action_id, __('action started', 'action-scheduler') );
	}

	public function log_completed_action( $action_id ) {
		$this->log( $action_id, __('action complete', 'action-scheduler') );
	}

	public function log_failed_action( $action_id, Exception $exception ) {
		$this->log( $action_id, sprintf(__('action failed: %s', 'action-scheduler'), $exception->getMessage() ));
	}

	public function log_reset_action( $action_id ) {
		$this->log( $action_id, __('action reset', 'action_scheduler') );
	}

	/**
	 * Exclude action scheduler comments from admin queries to hide them from the "Comments" screen
	 * and the admin dashboard widget.
	 *
	 * Requires WooCommerce to have run WC_Comments::exclude_order_comments() to join the posts table.
	 *
	 * @param array $clauses
	 * @return array
	 */
	public function exclude_comments( $clauses ) {
		global $wpdb, $typenow, $pagenow;

		if ( is_admin() ) {
			$clauses['where'] .= " AND $wpdb->posts.post_type NOT IN ('" . ActionScheduler_wpPostStore::POST_TYPE . "') ";
			error_log( '$clauses = ' . print_r( $clauses, true ) );
		}

		return $clauses;
	}

	/**
	 * Exclude order comments from RSS feeds
	 *
	 * @param string $where
	 * @return string
	 */
	public function exclude_comments_from_feed( $where ) {
		global $wpdb;

		if ( $where ) {
			$where .= ' AND ';
		}

		$where .= " $wpdb->posts.post_type NOT IN ('" . ActionScheduler_wpPostStore::POST_TYPE . "') ";

	    return $where;
	}
}
 
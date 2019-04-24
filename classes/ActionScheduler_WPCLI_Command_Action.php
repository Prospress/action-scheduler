<?php

/**
 * WP CLI command for actions.
 */
class ActionScheduler_WPCLI_Command_Action {

	/**
	 * Creates a single, recurring, or cron action.
	 *
	 * Alias for "generate" command, for creating individual actions.
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : The name of the hook to schedule.
	 *
	 * <start>
	 * : String to indicate the start time.
	 *
	 * [--args=<args>]
	 * : A JSON string of the arguments to pass to the action.
	 *
	 * [--group=<group>]
	 * : Add task to specified group.
	 *
	 * [--interval=<interval>]
	 * : Number of seconds between recurring events.
	 *
	 * [--cron=<cron>]
	 * : Cron schedule string.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 */
	public function create( $args, $assoc_args ) {
		$command = new ActionScheduler_WPCLI_Command_Action_Generate( $args, $assoc_args );
		$command->execute();
	}

	/**
	 * Deletes an existing action.
	 *
	 * ## OPTIONS
	 *
	 * <action_id>
	 * : ID of the action to delete.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 */
	public function delete( $args, $assoc_args ) {
		$store = ActionScheduler::store();
		$action_id = absint( $args[0] );

		try {
			$store->delete_action( $action_id );
		} catch ( InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		$action = $store->fetch_action( $action_id );

		if ( ! is_a( $action, 'ActionScheduler_NullAction' ) ) {
			\WP_CLI::error( sprintf( 'Unable to delete action %s.', $action_id ) );
		}

		\WP_CLI::success( sprintf( 'Deleted action %s.', $action_id ) );
	}

	/**
	 * Verifies whether an action exists.
	 *
	 * ## OPTIONS
	 *
	 * <action_id>
	 * : ID of the action to check if exists.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 */
	public function exists( $args, $assoc_args ) {
		$store = ActionScheduler::store();

		$action_id = absint( $args[0] );
		$action = $store->fetch_action( $action_id );

		if ( ! empty( $action ) && ! is_a( $action, 'ActionScheduler_NullAction' ) ) {
			\WP_CLI::success( sprintf( 'Action with ID %s exists.', $action_id ) );
		}
	}

	/**
	 * Generate one or multiple actions.
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : The name of the hook to schedule.
	 *
	 * <start>
	 * : String to indicate the start time.
	 *
	 * [--args=<args>]
	 * : A JSON string of the arguments to pass to the action.
	 *
	 * [--group=<group>]
	 * : Add task to specified group.
	 *
	 * [--interval=<interval>]
	 * : Number of seconds between recurring events.
	 *
	 * [--limit=<limit>]
	 * : Number of recurring events to schedule.
	 *
	 * [--cron=<cron>]
	 * : Cron schedule string.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 */
	public function generate( $args, $assoc_args ) {
		$command = new ActionScheduler_WPCLI_Command_Action_Generate( $args, $assoc_args );
		$command->execute();
	}

	/**
	 * Get details about an action.
	 *
	 * ## OPTIONS
	 *
	 * <action_id>
	 * : ID of action to get details about.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 */
	public function get( $args, $assoc_args ) {
		$store = ActionScheduler::store();
		$action_id = absint( $args[0] );
		$action = $store->fetch_action( $action_id );

		if ( empty( $action ) || is_a( $action, 'ActionScheduler_NullAction' ) ) {
			\WP_CLI::error( sprintf( '%s is not an action.', $action_id ) );
		}

		$fields = array(
			'id'     => $action_id,
			'hook'   => $action->get_hook(),
			'args'   => $action->get_args(),
			'status' => $store->get_status( $action_id ),
			'date'   => ! empty( $action->get_schedule()->next() ) ? $action->get_schedule()->next()->format( 'Y-m-d H:i:s T' ) : '—',
			'group'  => ! empty( $action->get_group() ) ? $action->get_group() : '—',
		);

		$rows = array();

		foreach ( $fields as $field => $value ) {
			$rows[] = array(
				'field' => $field,
				'value' => $value,
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
	}

	/**
	 * Gets a list of actions.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : A comma separated list of fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * [--per-page]
	 * : Number of actions to display in the table.
	 *
	 * [--offset]
	 * : Offset to start display of actions.
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each action:
	 *
	 * * hook
	 * * args
	 * * status
	 * * date
	 *
	 * These fields are optionally available:
	 *
	 * * id
	 * * group
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 */
	public function list( $args, $assoc_args ) {
		$store = ActionScheduler::store();
		$total_count = (int) $store->query_actions( array(), 'count' );

		if ( 0 === $total_count ) {
			\WP_CLI::error( 'No actions to list.' );
		}

		$fields   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'fields', array( 'hook', 'args', 'status', 'date' ) );
		$per_page = \WP_CLI\Utils\get_flag_value( $assoc_args, 'per-page', 10 );
		$offset   = \WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 );

		if ( is_string( $fields ) ) {
			$fields = explode( ',', $fields );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $fields );

		if ( empty( $args[1] ) ) {
			$action_ids = $store->query_actions( array( 'per_page' => $per_page, 'offset' => $offset ) );
		} else {
			$action_ids = array_unique( array_map( 'absint', explode( ',', $args[1] ) ) );
		}

		if ( 'ids' == $formatter->format ) {
			echo implode( ' ', $action_ids );
			return;
		} else if ( 'count' === $formatter->format ) {
			$formatter->display_items( $action_ids );
			return;
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $action_ids ) * count( $fields ) );
		$rows = array();

		$action_ids = array_map( 'intval', $action_ids );

		foreach ( $action_ids as $action_id ) {
			$action = $store->fetch_action( $action_id );
			$row = array();

			if ( is_a( $action, 'ActionScheduler_NullAction' ) ) {
				\WP_CLI::warning( 'Action with ID \'' . $action_id . '\' does not exist.' );
				foreach ( $fields as $field ) {
					$progress_bar->tick();
				}
				continue;
			}

			foreach ( $fields as $field ) {
				switch ( $field ) {

					case 'id':
						$row['id'] = $action_id;
						break;

					case 'hook':
						$row['hook'] = $action->get_hook();
						break;

					case 'date':
						$row['date'] = $store->get_date_gmt( $action_id )->format( 'Y-m-d H:i:s T' );
						break;

					case 'group':
						$row['group'] = $action->get_group();
						break;

					case 'status':
						$row['status'] = $store->get_status( $action_id );
						break;

					case 'args':
						$row['args'] = $action->get_args();
						break;

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();
		$formatter->display_items( $rows );

		if ( 'table' === $formatter->format )
			\WP_CLI::success( sprintf( 'Displaying %d - %d of %d actions.', $offset, ( $offset + $per_page ), $total_count ) );
	}

	public function _list( $args, $assoc_args ) {
		$store = ActionScheduler::store();
		$total_count = (int) $store->query_actions( array(), 'count' );

		if ( 0 === $total_count ) {
			\WP_CLI::error( 'No actions to list.' );
		}

		$available_columns = array( 'id', 'hook', 'date', 'group', 'status', 'args' );
		$default_columns = array( 'hook', 'args', 'status', 'date' );
		$columns = ActionScheduler_WPCLI::get_columns( $assoc_args, $default_columns, $available_columns );

		$per_page = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'per-page', 20 ) );
		$offset = absint( \WP_CLI\Utils\get_flag_value( $assoc_args, 'offset', 0 ) );

		if ( empty( $args[1] ) ) {
			$action_ids = $store->query_actions( array( 'per_page' => $per_page, 'offset' => $offset ) );
		} else {
			$action_ids = array_unique( array_map( 'absint', explode( ',', $args[1] ) ) );
		}

		$progress_bar = \WP_CLI\Utils\make_progress_bar( 'Collecting data:', count( $action_ids ) * count( $columns ) );
		$rows = array();

		foreach ( $action_ids as $action_id ) {
			$action = $store->fetch_action( $action_id );
			$row = array();

			if ( is_a( $action, 'ActionScheduler_NullAction' ) ) {
				\WP_CLI::warning( 'Action with ID \'' . $action_id . '\' does not exist.' );
				foreach ( $columns as $column ) {
					$progress_bar->tick();
				}
				continue;
			}

			foreach ( $columns as $column ) {
				switch ( $column ) {

					case 'id':
						$row['id'] = $action_id;
						break;

					case 'hook':
						$row['hook'] = $action->get_hook();
						break;

					case 'date':
						$row['date'] = $store->get_date_gmt( $action_id )->format( 'Y-m-d H:i:s T' );
						break;

					case 'group':
						$row['group'] = $action->get_group();
						break;

					case 'status':
						$row['status'] = $store->get_status( $action_id );
						break;

					case 'args':
						$row['args'] = $action->get_args();
						break;

				}

				$progress_bar->tick();
			}

			$rows[] = $row;
		}

		$progress_bar->finish();

		\WP_CLI\Utils\format_items( 'table', $rows, $columns );
		\WP_CLI::success( sprintf( 'Listed actions %d - %d of %d.', $offset, min( ( $offset + $per_page ), $total_count ), $total_count ) );
	}

	/**
	 * Runs the specified action.
	 *
	 * ## OPTIONS
	 *
	 * <action_id>
	 * : ID of the action to run.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 */
	public function run( $args, $assoc_args ) {
		$store = ActionScheduler::store();
		$action_id = absint( $args[0] );
		$action = $store->fetch_action( $action_id );

		$command = new ActionScheduler_WPCLI_QueueRunner();
		$command->process_action( $action_id );

		if ( did_action( $action->get_hook() ) ) {
			\WP_CLI::success( sprintf( 'Executed action %s.', $action_id ) );
		} else {
			\WP_CLI::error( sprintf( 'Unable to execute action %s.', $action_id ) );
		}
	}

	/**
	 * Cancel an action.
	 *
	 * ## OPTIONS
	 *
	 * <action_id>
	 * : ID of the action to cancel.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Keyed arguments.
	 */
	public function cancel( $args, $assoc_args ) {
		$store = ActionScheduler::store();
		$action_id = absint( $args[0] );

		try {
			$store->cancel_action( $action_id );
		} catch ( InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		$action = $store->fetch_action( $action_id );

		if ( ! is_a( $action, 'ActionScheduler_CanceledAction' ) ) {
			\WP_CLI::error( sprintf( 'Unable to cancel action %s.', $action_id ) );
		}

		\WP_CLI::success( sprintf( 'Canceled action %s.', $action_id ) );
	}

	public function test( $args, $assoc_args ) {
		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'id', 'title', 'date' ) );
		$data = array(
			array(
				'id' => 1,
				'title' => 'Hello',
				'date' => '2019-04-16',
			),
			array(
				'id' => 2,
				'title' => 'World',
				'date' => '2019-04-17',
			),
		);

		if ( 'ids' == $formatter->format ) {
			echo implode( ' ', array_column( $data, 'id' ) );
		} else if ( 'count' === $formatter->format ) {
			$formatter->display_items( $data );
		} else {
			$formatter->display_items( $data );
		}
	}

}

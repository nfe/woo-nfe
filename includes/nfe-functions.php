<?php
/**
 * WooCommerce NFe Custom Functions.
 *
 * @author   NFe.io
 *
 * @version  1.0.4
 *
 * @package WooCommerce_NFe/NFe_Functions
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Function to fetch fields from the NFe WooCommerce Integration.
 *
 * @param string $value value to fetch.
 *
 * @return string
 */
function nfe_get_field( $value = '' ) {
	$nfe_fields = get_option( 'woocommerce_woo-nfe_settings', array() );

	if ( empty( $value ) ) {
		return is_array( $nfe_fields ) ? $nfe_fields : array();
	}

	if ( ! is_array( $nfe_fields ) || ! isset( $nfe_fields[ $value ] ) ) {
		return '';
	}

	return $nfe_fields[ $value ];
}

/**
 * Gets the active RTC validation profile.
 *
 * @return string
 */
function nfe_rtc_validation_profile() {
	$profile = nfe_get_field( 'nfe_rtc_validation_profile' );

	if ( ! in_array( $profile, array( 'compativel', 'equilibrado', 'estrito' ), true ) ) {
		return 'equilibrado';
	}

	return $profile;
}

/**
 * Make sure address is required and if all the fields are available.
 *
 * Native billing fields are read through the WC_Order getters, since under HPOS
 * they are table columns and not meta. Only the fields added by the Brazilian
 * checkout fields plugin are custom order meta.
 *
 * @param int|WC_Order $order order ID or order object.
 *
 * @return bool
 */
function nfe_order_address_filled( $order ) {
	// If address is not required, go along.
	if ( nfe_require_address() === false ) {
		return true;
	}

	$order = is_a( $order, 'WC_Order' ) ? $order : nfe_wc_get_order( $order );

	if ( ! $order ) {
		return false;
	}

	$fields = array(
		// Custom meta, added by the Brazilian checkout fields plugin.
		'neighborhood' => nfe_get_order_meta( $order, '_billing_neighborhood' ),
		'number'       => nfe_get_order_meta( $order, '_billing_number' ),
		// Native order fields: columns under HPOS, never meta.
		'address_1'    => $order->get_billing_address_1(),
		'postcode'     => $order->get_billing_postcode(),
		'state'        => $order->get_billing_state(),
		'city'         => $order->get_billing_city(),
		'country'      => $order->get_billing_country(),
	);

	$count = 0;
	foreach ( $fields as $field => $value ) {
		if ( empty( $value ) ) {
			$count = 1;

			break;
		}
	}

	// If there is one or more fields missing, flag it.
	if ( $count >= 1 ) {
		return false;
	}

	return true;
}

/**
 * Past Issue Check (It answers the question: Can we issue a past order?).
 *
 * @param WC_Order $order order object.
 *
 * @return bool
 */
function nfe_issue_past_orders( $order ) {
	$past_days = nfe_get_field( 'issue_past_days' );

	if ( empty( $past_days ) ) {
		return false;
	}

	$days = '-' . $past_days . ' days';

	$date_created = is_a( $order, 'WC_Order' ) ? $order->get_date_created() : null;

	if ( ! $date_created ) {
		return false;
	}

	// $order->post does not exist under HPOS: the creation date comes from the
	// order getter, and both sides of the comparison are absolute timestamps.
	if ( strtotime( $days ) < $date_created->getTimestamp() ) {
		return true;
	}

	return false;
}

/**
 * WooCommerce 2.2 support for wc_get_order.
 *
 * @param int $order_id order ID.
 *
 * @return WC_Order order object.
 */
function nfe_wc_get_order( $order_id ) {
	return ( function_exists( 'wc_get_order' ) )
		? wc_get_order( $order_id )
		: WC_Order( $order_id );
}

/**
 * Reads an order meta value through the WooCommerce CRUD API.
 *
 * Works with both HPOS and the legacy post storage. Do not use it for native
 * order fields (billing address, e-mail, dates): those have their own getters
 * and are columns, not meta, under HPOS.
 *
 * @since 1.5.0
 *
 * @param int|WC_Order $order    order ID or order object.
 * @param string       $key      meta key.
 * @param mixed        $fallback value returned when the order or the meta is missing.
 *
 * @return mixed
 */
function nfe_get_order_meta( $order, $key, $fallback = '' ) {
	$order = is_a( $order, 'WC_Order' ) ? $order : nfe_wc_get_order( $order );

	if ( ! $order ) {
		return $fallback;
	}

	$value = $order->get_meta( $key, true );

	return ( '' === $value || null === $value ) ? $fallback : $value;
}

/**
 * Writes an order meta value through the WooCommerce CRUD API.
 *
 * Pass false to $save when several meta values are written in the same flow, so
 * a single $order->save() persists all of them.
 *
 * @since 1.5.0
 *
 * @param int|WC_Order $order order ID or order object.
 * @param string       $key   meta key.
 * @param mixed        $value meta value.
 * @param bool         $save  whether to persist immediately.
 *
 * @return WC_Order|false the order object, so callers can group writes, or false when not found.
 */
function nfe_set_order_meta( $order, $key, $value, $save = true ) {
	$is_object = is_a( $order, 'WC_Order' );
	$order     = $is_object ? $order : nfe_wc_get_order( $order );

	if ( ! $order ) {
		return false;
	}

	$order->update_meta_data( $key, $value );

	// Deferring the save only makes sense when the caller holds the object and
	// will save it later. Given an ID, the resolved object is local to this
	// call, so honouring $save = false would drop the write without a word.
	if ( $save || ! $is_object ) {
		$order->save();
	}

	return $order;
}

/**
 * Is the WooCommerce High-Performance Order Storage the authoritative storage?
 *
 * Both storages are supported: the answer only decides how the order query is
 * expressed, since the legacy CPT data store silently drops 'meta_query'.
 *
 * @since 1.5.0
 *
 * @return bool
 */
function nfe_hpos_enabled() {
	if ( ! class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' )
		|| ! method_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil', 'custom_orders_table_usage_is_enabled' ) ) {
		return false;
	}

	return (bool) \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
}

/**
 * Translates the plugin custom query vars into meta queries for the legacy store.
 *
 * The CPT order data store discards the 'meta_query' argument of wc_get_orders(),
 * so every meta based lookup of this plugin travels as a dedicated query var and
 * is turned into a WP_Query meta clause here. Under HPOS the same lookups are
 * passed straight as 'meta_query' and this filter never runs.
 *
 * @since 1.5.0
 *
 * @param array $wp_query_args WP_Query arguments built by the CPT data store.
 * @param array $query_vars    original wc_get_orders() arguments.
 *
 * @return array
 */
function nfe_cpt_get_orders_query( $wp_query_args, $query_vars ) {
	if ( ! empty( $query_vars['nfe_invoice_id'] ) ) {
		$wp_query_args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'key'     => '_nfe_invoice_id',
			'value'   => $query_vars['nfe_invoice_id'],
			'compare' => '=',
		);
	}

	if ( ! empty( $query_vars['nfe_issued_status'] ) ) {
		$wp_query_args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'key'     => 'nfe_issued',
			'value'   => sprintf( ':"%s";', $query_vars['nfe_issued_status'] ),
			'compare' => 'LIKE',
		);
	}

	if ( ! empty( $query_vars['nfe_backfill_pending'] ) ) {
		$wp_query_args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'AND',
			array(
				'key'     => 'nfe_issued',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_nfe_invoice_id',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_nfe_backfill_skipped',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	if ( ! empty( $query_vars['nfe_issued_exists'] ) ) {
		$wp_query_args['meta_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'key'     => 'nfe_issued',
			'compare' => 'EXISTS',
		);
	}

	return $wp_query_args;
}
add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'nfe_cpt_get_orders_query', 10, 2 );

/**
 * Cron hook that drives the '_nfe_invoice_id' backfill.
 */
const NFE_BACKFILL_HOOK = 'nfe_backfill_invoice_ids_event';

/**
 * Runs one-off upgrade steps when the installed version changes.
 *
 * Keyed on a stored version rather than a per-task flag, so later changes can
 * hang their own steps off the same routine. The option is only written after
 * every step has run, so an interrupted upgrade is retried on the next load
 * instead of being silently skipped.
 *
 * @since 1.5.0
 *
 * @return void
 */
function nfe_maybe_upgrade() {
	$installed = get_option( 'nfe_plugin_version', '' );
	$current   = defined( 'WOO_NFE_VERSION' ) ? WOO_NFE_VERSION : '';

	if ( '' === $current || $installed === $current ) {
		return;
	}

	nfe_purge_pdf_cache();

	/**
	 * Fires once after the plugin has been upgraded.
	 *
	 * @since 1.5.0
	 *
	 * @param string $installed Previously installed version ('' on first run).
	 * @param string $current   Version now running.
	 */
	do_action( 'nfe_upgraded', $installed, $current );

	update_option( 'nfe_plugin_version', $current, false );
}

/**
 * Removes the PDF cache the plugin used to keep under uploads/.
 *
 * Those files are fiscal documents carrying the buyer's name, tax ID and
 * address. They were written with predictable names into a publicly served
 * directory, so anyone who guessed a URL could read one without passing the
 * plugin's authorisation at all. The cache is gone from the code; this clears
 * what earlier versions already wrote to disk.
 *
 * Only files this plugin is known to have created are deleted, and the
 * directory is removed with rmdir(), which refuses to act unless it is empty --
 * so anything unexpected in there is left untouched rather than destroyed.
 *
 * @since 1.5.0
 *
 * @return void
 */
function nfe_purge_pdf_cache() {
	$upload_dir = wp_upload_dir();

	if ( empty( $upload_dir['basedir'] ) ) {
		return;
	}

	$dir = trailingslashit( $upload_dir['basedir'] ) . 'nfe/';

	if ( ! is_dir( $dir ) ) {
		return;
	}

	$targets = glob( $dir . 'nfse-*.pdf' );
	$targets = is_array( $targets ) ? $targets : array();

	// The two guards a previous change added to keep the directory from being
	// listed or served; they go away with the directory itself.
	foreach ( array( 'index.php', '.htaccess' ) as $guard ) {
		if ( file_exists( $dir . $guard ) ) {
			$targets[] = $dir . $guard;
		}
	}

	foreach ( $targets as $target ) {
		if ( is_file( $target ) ) {
			wp_delete_file( $target );
		}
	}

	/*
	 * Succeeds only when nothing else is left in there, which is the safety
	 * property this relies on: anything unexpected in the folder survives.
	 *
	 * WP_Filesystem is deliberately not used. It has no directory removal that
	 * works without initialising a filesystem context, and initialising one
	 * during an upgrade can prompt the administrator for FTP credentials -- a
	 * heavy, user-visible failure mode for removing one empty directory.
	 */
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- A non-empty directory is an expected outcome, not an error; WP_Filesystem would risk a credentials prompt mid-upgrade.
}

/**
 * Schedules the backfill of '_nfe_invoice_id' when it has not run yet.
 *
 * Orders issued before the flat meta existed only carry the invoice ID inside
 * the serialized 'nfe_issued' array, so an invoice lookup would never find
 * them. Resolution by external ID already covers the usual case, and this
 * backfill closes the gap for the rest. It runs once per install, in batches,
 * off a scheduled event so no request pays for it.
 *
 * @since 1.5.0
 *
 * @return void
 */
function nfe_maybe_schedule_backfill() {
	if ( 'yes' === get_option( 'nfe_invoice_id_backfill_done' ) ) {
		return;
	}

	if ( wp_next_scheduled( NFE_BACKFILL_HOOK ) ) {
		return;
	}

	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, NFE_BACKFILL_HOOK );
}

/**
 * Clears the backfill schedule when the plugin is deactivated.
 *
 * The event is this plugin's own, so it must not be left behind in the cron
 * array once the plugin stops being loaded.
 *
 * @since 1.5.0
 *
 * @return void
 */
function nfe_clear_backfill_schedule() {
	wp_clear_scheduled_hook( NFE_BACKFILL_HOOK );
}

/**
 * Runs one backfill batch and reschedules itself until there is nothing left.
 *
 * Since each batch queries only the orders that still lack the flat meta, the
 * end is reached when a batch either finds nothing or can no longer make
 * progress - the tail of orders whose 'nfe_issued' holds no usable invoice ID.
 * A query that could not run is never mistaken for the end: it is retried,
 * because nothing else would ever reset the flag.
 *
 * @since 1.5.0
 *
 * @return void
 */
function nfe_run_invoice_id_backfill() {
	if ( 'yes' === get_option( 'nfe_invoice_id_backfill_done' ) ) {
		return;
	}

	$limit  = 50;
	$result = nfe_backfill_invoice_ids( $limit );

	// The query could not run (WooCommerce missing or the order query failed).
	// Retry later instead of declaring the migration finished.
	if ( false === $result ) {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, NFE_BACKFILL_HOOK );

		return;
	}

	$scanned = isset( $result['scanned'] ) ? (int) $result['scanned'] : 0;

	// Every order examined leaves the pending set - either with the flat ID or
	// with the skip marker - so an empty batch is the only end condition. A
	// batch that wrote nothing is NOT the end: the orders it skipped were
	// marked, and the next batch moves on to the ones behind them.
	if ( 0 === $scanned ) {
		update_option( 'nfe_invoice_id_backfill_done', 'yes', false );
		delete_option( 'nfe_invoice_id_backfill_runs' );

		return;
	}

	// Safety valve. Persistence failures are invisible here (save() swallows
	// and logs its own errors), so an order that never records its meta would
	// come back in every batch. Bounding the number of runs turns that into a
	// stop instead of a cron loop that never ends.
	$runs = absint( get_option( 'nfe_invoice_id_backfill_runs', 0 ) ) + 1;

	if ( $runs > 5000 ) {
		update_option( 'nfe_invoice_id_backfill_done', 'yes', false );
		delete_option( 'nfe_invoice_id_backfill_runs' );

		return;
	}

	update_option( 'nfe_invoice_id_backfill_runs', $runs, false );
	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, NFE_BACKFILL_HOOK );
}

/**
 * Resolves the order straight from the external ID sent back by NFe.io.
 *
 * The plugin sends 'WOO-NFE-{order_id}' as the invoice externalId, and NFe.io
 * echoes it back on the webhook event, where it is documented as the best key
 * to match the event with the order. Parsing it resolves the order with no meta
 * query at all, works the same under HPOS and the legacy storage, and still
 * finds orders issued before '_nfe_invoice_id' existed.
 *
 * The match is only accepted after confirming the order really belongs to the
 * invoice in the event, so a malformed or spoofed external ID cannot steer the
 * update to an unrelated order.
 *
 * The suffixed form 'WOO-NFE-{order_id}-{n}' is accepted ahead of the re-issue
 * support added by the SDK change.
 *
 * @since 1.5.0
 *
 * @param string $external_id external ID as received in the event.
 * @param string $invoice_id  NFe.io invoice ID of the same event, used to confirm the match.
 *
 * @return WC_Order|false the matching order, or false when it cannot be resolved with confidence.
 */
function nfe_find_order_by_external_id( $external_id, $invoice_id = '' ) {
	$external_id = is_scalar( $external_id ) ? trim( (string) $external_id ) : '';

	if ( '' === $external_id ) {
		return false;
	}

	if ( ! preg_match( '/^WOO-NFE-(\d+)(?:-\d+)?$/', $external_id, $matches ) ) {
		return false;
	}

	$invoice_id = is_scalar( $invoice_id ) ? trim( (string) $invoice_id ) : '';

	// The external ID alone is a guessable, sequential value on a public and
	// unauthenticated endpoint, so it is never enough on its own: without an
	// invoice ID to confirm the match against, this path is not taken at all
	// and the caller falls back to the invoice lookup.
	if ( '' === $invoice_id ) {
		return false;
	}

	$order = nfe_wc_get_order( (int) $matches[1] );

	// Order and refund IDs share one sequence, and wc_get_order() happily
	// returns a WC_Order_Refund, which has no add_order_note().
	if ( ! is_a( $order, 'WC_Order' ) ) {
		return false;
	}

	$issued = nfe_get_order_meta( $order, 'nfe_issued' );

	// An order that never started an issuing flow can never be the target of an
	// invoice event, whatever the external ID claims.
	if ( empty( $issued ) ) {
		return false;
	}

	$known_id = nfe_get_order_meta( $order, '_nfe_invoice_id' );

	if ( empty( $known_id ) && is_array( $issued ) && ! empty( $issued['id'] ) && is_scalar( $issued['id'] ) ) {
		$known_id = $issued['id'];
	}

	// Without an invoice ID on the order there is nothing to confirm the event
	// against, so this path refuses to guess. It costs no legitimate case: an
	// order whose issuing succeeded always carries the ID - written from the
	// API response at issuing time, or held in 'nfe_issued' for orders issued
	// by earlier versions. What is left without one is an order whose issuing
	// FAILED and kept the 'Processing' marker, and those must never accept an
	// invoice event - otherwise anyone could write forged fiscal data into them
	// by walking the sequential external IDs against an unauthenticated endpoint.
	if ( empty( $known_id ) ) {
		return false;
	}

	if ( (string) $known_id !== $invoice_id ) {
		return false;
	}

	return $order;
}

/**
 * Finds the order that holds a given NFe.io invoice ID.
 *
 * Queries the flat '_nfe_invoice_id' meta by exact match and works the same on
 * both storages. Orders issued before this meta existed are covered by
 * nfe_backfill_invoice_ids().
 *
 * @since 1.5.0
 *
 * @param string $invoice_id NFe.io invoice ID.
 *
 * @return WC_Order|false the order, or false when there is no match.
 */
function nfe_find_order_by_invoice_id( $invoice_id ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return false;
	}

	$invoice_id = is_scalar( $invoice_id ) ? trim( (string) $invoice_id ) : '';

	if ( '' === $invoice_id ) {
		return false;
	}

	// The status is left to the wc_get_orders() default (every registered order
	// status, trash excluded).
	$args = array(
		'limit'  => 1,
		'return' => 'objects',
	);

	if ( nfe_hpos_enabled() ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_nfe_invoice_id',
				'value'   => $invoice_id,
				'compare' => '=',
			),
		);
	} else {
		$args['nfe_invoice_id'] = $invoice_id;
	}

	$orders = wc_get_orders( $args );

	if ( ! is_array( $orders ) || empty( $orders ) ) {
		return false;
	}

	$order = reset( $orders );

	return is_a( $order, 'WC_Order' ) ? $order : false;
}

/**
 * Writes '_nfe_invoice_id' on one batch of orders that still lack it.
 *
 * The batch is defined by the data itself - orders that have 'nfe_issued' and
 * do not have the flat meta yet - instead of by an offset. Every write removes
 * the order from the next query, so there is no cursor to keep, no page to
 * shift and no order skipped when the set changes mid-run.
 *
 * @since 1.5.0
 *
 * @param int $limit how many orders to process in this batch.
 *
 * @return array|false counters ('scanned', 'updated'), or false when the query could not run.
 */
function nfe_backfill_invoice_ids( $limit = 50 ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return false;
	}

	$limit = max( 1, (int) $limit );

	$args = array(
		'limit'   => $limit,
		'orderby' => 'ID',
		'order'   => 'ASC',
		'return'  => 'objects',
		'status'  => array_keys( wc_get_order_statuses() ),
	);

	if ( nfe_hpos_enabled() ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'AND',
			array(
				'key'     => 'nfe_issued',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => '_nfe_invoice_id',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_nfe_backfill_skipped',
				'compare' => 'NOT EXISTS',
			),
		);
	} else {
		$args['nfe_backfill_pending'] = true;
	}

	$orders = wc_get_orders( $args );

	if ( ! is_array( $orders ) ) {
		return false;
	}

	$result = array(
		'scanned' => count( $orders ),
		'updated' => 0,
	);

	foreach ( $orders as $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			continue;
		}

		$issued     = $order->get_meta( 'nfe_issued', true );
		$invoice_id = ( is_array( $issued ) && ! empty( $issued['id'] ) && is_scalar( $issued['id'] ) )
			? trim( (string) $issued['id'] )
			: '';

		// An order with no usable invoice ID - typically one left with the
		// 'Processing' marker by a failed issuing - is marked as examined so it
		// leaves the pending set. Without it a handful of such orders would hold
		// a slot in every batch forever, starving the rest of the migration and
		// making an empty batch indistinguishable from the end of the list.
		if ( '' === $invoice_id ) {
			$order->update_meta_data( '_nfe_backfill_skipped', 1 );
			$order->save();

			continue;
		}

		// One save per order: the only meta written here is the flat invoice ID.
		$order->update_meta_data( '_nfe_invoice_id', $invoice_id );
		$order->save();

		++$result['updated'];
	}

	return $result;
}

/**
 * Counts the orders whose invoice is in a given NFe.io status.
 *
 * Used by the dashboard widget. Kept apart from the single order lookup: this
 * one only needs the total, so it asks wc_get_orders() for a paginated result
 * and reads ->total instead of hydrating every order.
 *
 * @since 1.5.0
 *
 * @param string $status NFe.io invoice status (ex.: 'Issued', 'Cancelled').
 *
 * @return int
 */
function nfe_count_orders_by_invoice_status( $status ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return 0;
	}

	$status = is_scalar( $status ) ? trim( (string) $status ) : '';

	if ( '' === $status ) {
		return 0;
	}

	$args = array(
		'limit'    => 1,
		'paginate' => true,
		'return'   => 'ids',
	);

	if ( nfe_hpos_enabled() ) {
		// The status lives inside the serialized 'nfe_issued' array, so it is
		// matched by the serialized fragment, as the previous WP_Query did.
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'nfe_issued',
				'value'   => sprintf( ':"%s";', $status ),
				'compare' => 'LIKE',
			),
		);
	} else {
		$args['nfe_issued_status'] = $status;
	}

	$results = wc_get_orders( $args );

	if ( ! is_object( $results ) || ! isset( $results->total ) ) {
		return 0;
	}

	return (int) $results->total;
}

/**
 * Status when the NFe in being processed.
 *
 * @since 1.2.4
 *
 * @return array
 */
function nfe_processing_status() {
	return array( 'WaitingCalculateTaxes', 'WaitingDefineRpsNumber', 'WaitingSend', 'WaitingSendCancel', 'WaitingReturn', 'WaitingDownload' );
}

/**
 * Does an address is required?
 *
 * @return bool
 */
function nfe_require_address() {
	$result = nfe_get_field( 'require_address' );

	if ( empty( $result ) ) {
		return true;
	}

	if ( 'no' === $result ) {
		return false;
	}

	return true;
}

/**
 * Get NFe status label.
 *
 * Covers the eleven `flowStatus` values the NFe.io API actually emits, per the
 * authoritative enum in the NFS-e OpenAPI spec (`nf-servico-v1.yaml`):
 * CancelFailed (-2), IssueFailed (-1), Issued (1), Cancelled (2),
 * PullFromCityHall (3) and the six Waiting* values (10-15), plus the plugin's
 * own 'Processing' marker, which is local state and not an API value.
 *
 * Note on granularity: a one-off rejection by the city hall and an exhausted
 * retry budget share the same `flowStatus`. They are told apart by the event
 * type in the `X-Hook-Event` header, or by the 'max retry' prefix in
 * `flowMessage` - never by this value.
 *
 * @since 1.5.0 Corrected 'CancelledFailed' to 'CancelFailed' (which is what the
 *              API sends, so the old key never matched) and added
 *              'PullFromCityHall', which had no label at all.
 *
 * @param string $status status.
 *
 * @return string
 */
function nfe_status_label( $status ) {
	// Check processing status first.
	if ( in_array( $status, nfe_processing_status(), true ) ) {
		return __( 'Processing NFe', 'nota-fiscal-nfe-io-for-woocommerce' );
	}

	$valid_status = array(
		'Issued'           => __( 'NFe Issued', 'nota-fiscal-nfe-io-for-woocommerce' ),
		'Cancelled'        => __( 'NFe Cancelled', 'nota-fiscal-nfe-io-for-woocommerce' ),
		'CancelFailed'     => __( 'NFe Cancelling Failed', 'nota-fiscal-nfe-io-for-woocommerce' ),
		'IssueFailed'      => __( 'NFe Issuing Failed', 'nota-fiscal-nfe-io-for-woocommerce' ),
		'PullFromCityHall' => __( 'NFe Retrieved from City Hall', 'nota-fiscal-nfe-io-for-woocommerce' ),
		'Processing'       => __( 'NFe Processing', 'nota-fiscal-nfe-io-for-woocommerce' ),
	);

	if ( isset( $valid_status[ $status ] ) ) {
		return $valid_status[ $status ];
	}

	return '';
}

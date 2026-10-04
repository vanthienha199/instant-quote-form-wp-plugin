<?php
/**
 * Quote requests: a private custom post type with an inbox-style admin list.
 *
 * @package InstantQuoteForm
 */

defined( 'ABSPATH' ) || exit;

/**
 * Submissions storage and admin screens.
 */
class IQF_Requests {

	const POST_TYPE = 'iqf_request';
	const STATUSES  = array( 'new', 'contacted', 'booked', 'closed' );

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'status_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filter' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_status' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu_badge' ), 99 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
	}

	/**
	 * Not public, no front-end URLs, and nobody can create one by hand.
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'               => __( 'Quote requests', 'instant-quote-form' ),
					'singular_name'      => __( 'Quote request', 'instant-quote-form' ),
					'menu_name'          => __( 'Quote requests', 'instant-quote-form' ),
					'edit_item'          => __( 'Quote request', 'instant-quote-form' ),
					'search_items'       => __( 'Search requests', 'instant-quote-form' ),
					'not_found'          => __( 'No quote requests yet. They appear here as soon as a customer sends the form.', 'instant-quote-form' ),
					'not_found_in_trash' => __( 'No quote requests in the trash.', 'instant-quote-form' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 26,
				'menu_icon'       => 'dashicons-feedback',
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/**
	 * Store a validated request.
	 *
	 * @param array $data     Clean form data.
	 * @param array $estimate Server-side estimate.
	 * @return int|WP_Error Post ID.
	 */
	public static function create( array $data, array $estimate ) {
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				/* translators: 1: customer name, 2: service. */
				'post_title'  => sprintf( __( '%1$s, %2$s', 'instant-quote-form' ), $data['name'], $data['service_label'] ),
				'meta_input'  => array(
					'_iqf_name'      => $data['name'],
					'_iqf_email'     => $data['email'],
					'_iqf_phone'     => $data['phone'],
					'_iqf_service'   => $data['service'],
					'_iqf_quantity'  => $data['quantity'],
					'_iqf_stories'   => $data['stories'],
					'_iqf_frequency' => $data['frequency'],
					'_iqf_extras'    => $data['extras'],
					'_iqf_date'      => $data['date'],
					'_iqf_message'   => $data['message'],
					'_iqf_total'     => $estimate['total'],
					'_iqf_lines'     => $estimate['lines'],
					'_iqf_status'    => 'new',
				),
			),
			true
		);
		return $id;
	}

	/**
	 * Status labels.
	 */
	public static function status_labels() {
		return array(
			'new'       => __( 'New', 'instant-quote-form' ),
			'contacted' => __( 'Contacted', 'instant-quote-form' ),
			'booked'    => __( 'Booked', 'instant-quote-form' ),
			'closed'    => __( 'Closed', 'instant-quote-form' ),
		);
	}

	/**
	 * List columns.
	 *
	 * @param array $cols Default columns.
	 */
	public static function columns( $cols ) {
		return array(
			'cb'           => $cols['cb'],
			'title'        => __( 'Customer', 'instant-quote-form' ),
			'iqf_service'  => __( 'Job', 'instant-quote-form' ),
			'iqf_when'     => __( 'Preferred date', 'instant-quote-form' ),
			'iqf_total'    => __( 'Estimate', 'instant-quote-form' ),
			'iqf_status'   => __( 'Status', 'instant-quote-form' ),
			'date'         => __( 'Received', 'instant-quote-form' ),
		);
	}

	/**
	 * Column values, all escaped.
	 *
	 * @param string $col Column.
	 * @param int    $id  Post ID.
	 */
	public static function column( $col, $id ) {
		$s = IQF_Settings::get();
		switch ( $col ) {
			case 'iqf_service':
				$svc = $s['services'][ get_post_meta( $id, '_iqf_service', true ) ] ?? null;
				if ( $svc ) {
					printf( '%s<br><span class="iqf-muted">%d %s</span>', esc_html( $svc['label'] ), (int) get_post_meta( $id, '_iqf_quantity', true ), esc_html( $svc['unit'] ) );
				}
				break;
			case 'iqf_when':
				$d = get_post_meta( $id, '_iqf_date', true );
				echo $d ? esc_html( date_i18n( 'D, M j', strtotime( $d ) ) ) : '<span class="iqf-muted">' . esc_html__( 'Flexible', 'instant-quote-form' ) . '</span>';
				break;
			case 'iqf_total':
				echo '<span class="iqf-money">' . esc_html( IQF_Pricing::money( (float) get_post_meta( $id, '_iqf_total', true ), $s ) ) . '</span>';
				break;
			case 'iqf_status':
				$st     = get_post_meta( $id, '_iqf_status', true ) ?: 'new';
				$labels = self::status_labels();
				printf( '<span class="iqf-pill iqf-%1$s">%2$s</span>', esc_attr( $st ), esc_html( $labels[ $st ] ?? $st ) );
				break;
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array $cols Columns.
	 */
	public static function sortable( $cols ) {
		$cols['iqf_total'] = 'iqf_total';
		return $cols;
	}

	/**
	 * Status dropdown above the list.
	 *
	 * @param string $post_type Current post type.
	 */
	public static function status_filter( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}
		$current = isset( $_GET['iqf_status'] ) ? sanitize_key( wp_unslash( $_GET['iqf_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<label class="screen-reader-text" for="iqf_status">' . esc_html__( 'Filter by status', 'instant-quote-form' ) . '</label>';
		echo '<select name="iqf_status" id="iqf_status"><option value="">' . esc_html__( 'All statuses', 'instant-quote-form' ) . '</option>';
		foreach ( self::status_labels() as $key => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Apply the status filter and the estimate sort.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function apply_filter( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || self::POST_TYPE !== $q->get( 'post_type' ) ) {
			return;
		}
		$status = isset( $_GET['iqf_status'] ) ? sanitize_key( wp_unslash( $_GET['iqf_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $status, self::STATUSES, true ) ) {
			$q->set( 'meta_key', '_iqf_status' );
			$q->set( 'meta_value', $status );
		}
		if ( 'iqf_total' === $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_iqf_total' );
			$q->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Edit screen: read-only details plus a status box.
	 */
	public static function meta_boxes() {
		add_meta_box( 'iqf_details', __( 'Request details', 'instant-quote-form' ), array( __CLASS__, 'details_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'iqf_status', __( 'Status', 'instant-quote-form' ), array( __CLASS__, 'status_box' ), self::POST_TYPE, 'side', 'high' );
	}

	/**
	 * Read-only details.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function details_box( $post ) {
		$s     = IQF_Settings::get();
		$m     = static function ( $k ) use ( $post ) {
			return get_post_meta( $post->ID, '_iqf_' . $k, true );
		};
		$rows  = array(
			__( 'Name', 'instant-quote-form' )    => esc_html( $m( 'name' ) ),
			__( 'Email', 'instant-quote-form' )   => '<a href="mailto:' . esc_attr( $m( 'email' ) ) . '">' . esc_html( $m( 'email' ) ) . '</a>',
			__( 'Phone', 'instant-quote-form' )   => esc_html( $m( 'phone' ) ?: '-' ),
			__( 'Stories', 'instant-quote-form' ) => esc_html( $m( 'stories' ) ),
			__( 'How often', 'instant-quote-form' ) => esc_html( ucfirst( $m( 'frequency' ) ) ),
			__( 'Preferred date', 'instant-quote-form' ) => esc_html( $m( 'date' ) ? date_i18n( get_option( 'date_format' ), strtotime( $m( 'date' ) ) ) : __( 'Flexible', 'instant-quote-form' ) ),
			__( 'Notes', 'instant-quote-form' )   => nl2br( esc_html( $m( 'message' ) ?: '-' ) ),
		);
		echo '<table class="iqf-details">';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
		echo '</table><h4>' . esc_html__( 'Estimate sent to the customer', 'instant-quote-form' ) . '</h4><table class="iqf-lines">';
		foreach ( (array) $m( 'lines' ) as $line ) {
			printf( '<tr><td>%s</td><td class="num">%s</td></tr>', esc_html( $line['label'] ), esc_html( IQF_Pricing::money( $line['amount'], $s ) ) );
		}
		printf( '<tr class="total"><td>%s</td><td class="num">%s</td></tr></table>', esc_html__( 'Total', 'instant-quote-form' ), esc_html( IQF_Pricing::money( (float) $m( 'total' ), $s ) ) );
	}

	/**
	 * Status box with its own nonce.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function status_box( $post ) {
		wp_nonce_field( 'iqf_status_' . $post->ID, 'iqf_status_nonce' );
		$current = get_post_meta( $post->ID, '_iqf_status', true ) ?: 'new';
		echo '<select name="iqf_status" class="widefat">';
		foreach ( self::status_labels() as $key => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Save status: nonce, autosave and capability checks first.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_status( $post_id, $post ) {
		if ( ! isset( $_POST['iqf_status_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['iqf_status_nonce'] ) ), 'iqf_status_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_POST['iqf_status'] ?? '' ) );
		if ( in_array( $status, self::STATUSES, true ) ) {
			update_post_meta( $post_id, '_iqf_status', $status );
		}
	}

	/**
	 * Count of new requests next to the menu item.
	 */
	public static function menu_badge() {
		global $menu;
		$new = self::count_new();
		if ( ! $new || ! is_array( $menu ) ) {
			return;
		}
		foreach ( $menu as $i => $item ) {
			if ( 'edit.php?post_type=' . self::POST_TYPE === $item[2] ) {
				$menu[ $i ][0] .= ' <span class="awaiting-mod count-' . (int) $new . '"><span class="pending-count">' . number_format_i18n( $new ) . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Number of requests still marked New.
	 */
	public static function count_new() {
		$q = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'meta_key'       => '_iqf_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'new', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
			)
		);
		return (int) $q->found_posts;
	}

	/**
	 * Replace Quick Edit with a mailto action.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 */
	public static function row_actions( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );
		$email = get_post_meta( $post->ID, '_iqf_email', true );
		if ( $email ) {
			$actions['iqf_reply'] = '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html__( 'Reply by email', 'instant-quote-form' ) . '</a>';
		}
		return $actions;
	}
}

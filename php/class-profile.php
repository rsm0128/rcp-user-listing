<?php
/**
 * Profile.
 *
 * @package UserListingRCP
 */

namespace UserListingRCP;

/**
 * Profile class.
 */
class Profile extends Singletone {
	/**
	 * CPT name.
	 *
	 * @var string
	 */
	const CPT_NAME = 'profile';

	/**
	 * User meta key name for profile post id.
	 *
	 * @var string
	 */
	const PROFILE_META_KEY = 'profile_id';

	/**
	 * Company name meta key.
	 *
	 * @var string
	 */
	const COMPAMNY_META_KEY = 'member_business_name';

	/**
	 * Business location latitude user meta key.
	 *
	 * @var string
	 */
	const LATITUDE_META_KEY = 'bus_loc_lat';

	/**
	 * Business location longitude meta key.
	 *
	 * @var string
	 */
	const LONGITUDE_META_KEY = 'bus_loc_long';

	/**
	 * Company name meta key.
	 *
	 * @var string
	 */
	const MAP_META_KEY = 'business_location_map';

	/**
	 * Initiate the resources.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_profile_cpt' ) );
		add_action( 'user_register', array( $this, 'add_profile_post' ), 99, 1 );
		add_action( 'delete_user', array( $this, 'delete_profile_post' ), 99, 1 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_init', array( $this, 'crete_profile_for_existing_users' ) );

		add_shortcode( 'profile_listing', array( $this, 'listing_markup' ) );

		add_filter( 'update_user_metadata', array( $this, 'on_user_meta_update' ), 10, 4 );
		add_action( 'wp_ajax_sync_user_bus_location', array( $this, 'on_ajax_user_location_sync' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	/**
	 * Init action handler. Register profile CPT.
	 */
	public function register_profile_cpt() {
		$args = array(
			'label'           => __( 'Profile', 'msrcp' ),
			'public'          => true,
			'has_archive'     => true,
			'capability_type' => 'post',
			'capabilities'    => array(
				'create_posts' => false,
			),
			'map_meta_cap'    => true,
			'supports'        => array( 'title', 'editor', 'author' ),
		);
		register_post_type( self::CPT_NAME, $args );
	}

	public function enqueue_script() {
		$version = '1.0';
		wp_register_script( 'rcp-user-listing-js', plugins_url( 'js/listing.js', MSRCP_PATH ), array( 'jquery' ), $version, true );
	}

	/**
	 * Get business name by user_id.
	 * Returns COMPAMNY_META_KEY value or `No company {user_id}`
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function generate_profile_title( $user_id ) {
		$business_name = get_user_meta( $user_id, self::COMPAMNY_META_KEY, true );
		if ( $business_name ) {
			return $business_name;
		}

		return 'No company for ' . $user_id;
	}

	/**
	 * Adds profile cpt post on user creation.
	 *
	 * @param int   $user_id  User ID.
	 */
	public function add_profile_post( $user_id ) {
		// Generate post_title with fname and lname.
		$post_title = $this->generate_profile_title( $user_id );

		$post_data = array(
			'post_title'  => $post_title,
			'post_author' => $user_id,
			'post_type'   => self::CPT_NAME,
			'post_status' => 'draft',
		);

		if ( class_exists( '\RCP\Database\Queries\Membership' ) ) {
			$membership      = new \RCP\Database\Queries\Membership(); // phpcs:ignore
			$user_membership = $membership->get_item_by( 'user_id', $user_id );
			if ( ! empty( $user_membership ) && 'active' === $user_membership->get_status() ) {
				$post_data['post_status'] = 'publish';
			}
		} else {
			$post_data['post_status'] = 'publish';
		}

		$post_id = wp_insert_post( $post_data );

		update_user_meta( $user_id, self::PROFILE_META_KEY, $post_id );
	}

	/**
	 * Delete profile post.
	 *
	 * @param int $user_id
	 */
	public function delete_profile_post( $user_id ) {
		$profile_id = get_user_meta( $user_id, self::PROFILE_META_KEY, true );
		if ( empty( $profile_id ) ) {
			return;
		}

		wp_delete_post( $profile_id, true );
	}

	/**
	 * Return profile post id by user id.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_profile_by_user( $user_id ) {
		return get_user_meta( $user_id, self::PROFILE_META_KEY, true );
	}

	/**
	 * All users with valid profile.
	 *
	 * @return array User ID array.
	 */
	private function get_all_users_with_profile() {
		global $wpdb;

		// Get all users with profile.
		$post_authors = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_author
				FROM $wpdb->posts
				WHERE post_type = %s
				GROUP BY post_author",
				self::CPT_NAME
			)
		);

		return $post_authors;
	}

	/**
	 * All users with duplicated profile.
	 *
	 * @return array User ID array.
	 */
	private function get_all_users_with_duplicate_profile() {
		global $wpdb;

		// Get all users with profile.
		$duplicated_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT post_author, COUNT(ID) AS count
				FROM $wpdb->posts
				WHERE post_type = %s
				GROUP BY post_author
				HAVING count > 1",
				self::CPT_NAME
			),
			ARRAY_A
		);

		$post_authors = array_column( $duplicated_posts, 'post_author' );

		return $post_authors;
	}

	/**
	 * All users with valid profile.
	 *
	 * @return array User ID array.
	 */
	private function get_all_users_with_active_membership() {
		global $wpdb;
		$tbl_memberships = $wpdb->prefix . 'rcp_memberships';

		// Get all users with profile.
		$post_authors = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore
				"SELECT DISTINCT user_id FROM $tbl_memberships WHERE status = %s GROUP BY user_id",
				'active'
			)
		);

		return $post_authors;
	}

	/**
	 * Create profile post for existing users
	 */
	public function crete_profile_for_existing_users() {
		$transient_name = 'doing_migration';
		if ( 'yes' === get_transient( $transient_name ) ) {
			return;
		}

		set_transient( $transient_name, 'yes', 3600 );

		$users_with_profile = $this->get_all_users_with_profile();

		// Get all users with active membership.
		$all_users = get_users(
			array(
				'role__not_in' => 'administrator',
				'exclude'      => $users_with_profile,
			)
		);

		foreach ( $all_users as $user ) {
			$this->add_profile_post( $user->ID );
		}

		delete_transient( $transient_name );
	}

	/**
	 * Show admin notice for duplicated entries.
	 */
	public function admin_notices() {
		global $pagenow;
		if ( 'edit.php' === $pagenow && isset( $_GET['post_type'] ) && ( 'profile' === $_GET['post_type'] ) ) {
			$duplicate_user_ids = $this->get_all_users_with_duplicate_profile();
			if ( ! empty( $duplicate_user_ids ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<div>Duplicated profiles found.</div>
				<?php
				foreach ( $duplicate_user_ids as $user_id ) {
					$posts      = get_posts(
						array(
							'post_type'   => self::CPT_NAME,
							'post_status' => 'any',
							'author'      => $user_id,
						)
					);
					$post_links = array();
					foreach ( $posts as $dup_post ) {
						$post_links[] = sprintf( '<a href="%s">%s</a>', get_edit_post_link( $dup_post ), $dup_post->post_title );
					}
					echo '<p>';
					echo join( ', ', $post_links );
					echo '</p>';
				}
				?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Update profile title on user update.
	 * @param null|bool $check      Whether to allow updating metadata for the given type.
	 * @param int       $user_id    ID of the object metadata is for.
	 * @param string    $meta_key   Metadata key.
	 * @param mixed     $meta_value Metadata value. Must be serializable if non-scalar.
	 */
	public function on_user_meta_update( $check, $user_id, $meta_key, $meta_value ) {
		if ( self::COMPAMNY_META_KEY === $meta_key ) {
			$this->update_profile_title( $user_id, $meta_value );
		} elseif ( self::MAP_META_KEY === $meta_key ) {
			$this->update_lat_long( $user_id, $meta_value );
		}

		return $check;
	}

	/**
	 * Update profile title on user update.
	 * @param int       $user_id  ID of the object metadata is for.
	 * @param mixed     $bus_name Business name.
	 * @return boolean  True on success.
	 */
	private function update_profile_title( $user_id, $bus_name ) {
		$profile_id = $this->get_profile_by_user( $user_id );
		if ( ! empty( $profile_id ) ) {
			wp_update_post(
				array(
					'ID'         => $profile_id,
					'post_title' => $bus_name,
					'post_name'  => sanitize_title( $bus_name ),
				)
			);
			return true;
		}

		return false;
	}

	/**
	 * Update user latitude and longitude meta data by position string.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $lat_long_str Latitude longitude zoom comma separate string.
	 * @return bool true on success, false on fail.
	 */
	private function update_lat_long( $user_id, $lat_long_str ) {
		$pos_arr = explode( ',', $lat_long_str );
		if ( count( $pos_arr ) >= 2 ) {
			update_user_meta( $user_id, self::LATITUDE_META_KEY, $pos_arr[0] );
			update_user_meta( $user_id, self::LONGITUDE_META_KEY, $pos_arr[1] );
			return true;
		}

		return false;
	}

	/**
	 * Ajax request handler for user business location sync.
	 */
	public function on_ajax_user_location_sync() {
		// Permission check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'msg' => 'You don\'t have right permission to do this action',
				)
			);
		}

		$this->bulk_sync_position();

		wp_send_json_success(
			array(
				'msg' => 'Sync success',
			)
		);
	}

	/**
	 * Sync all users latitude and longitude info
	 */
	private function bulk_sync_position() {
		global $wpdb;

		// Get all users with position info
		$users = $wpdb->get_results(
			$wpdb->prepare( "SELECT user_id, meta_value from {$wpdb->usermeta} WHERE meta_key=%s AND meta_value <> ''", self::MAP_META_KEY ),
			ARRAY_A
		);

		// Update users
		foreach ( $users as $user ) {
			$this->update_lat_long( $user['user_id'], $user['meta_value'] );
		}
	}

	/**
	 * Profile listing html.
	 */
	public function listing_markup( $atts ) {
		$per_page     = ! empty( $_GET['per_page'] ) ? (int) $_GET['per_page'] : 20;
		$current_page = get_query_var( 'paged' );
		$name         = ! empty( $_GET['_name'] ) ? sanitize_text_field( $_GET['_name'] ) : '';
		$service      = ! empty( $_GET['_service'] ) ? sanitize_text_field( $_GET['_service'] ) : '';
		$near_me      = ! empty( $_GET['near_me'] );
		$lat          = ! empty( $_GET['lat'] ) ? $_GET['lat'] : '';
		$long         = ! empty( $_GET['long'] ) ? $_GET['long'] : '';

		if ( ! empty( $lat ) && ! empty( $long ) ) {
			$position = array(
				'lat'  => $_GET['lat'],
				'long' => $_GET['long'],
			);
		} else {
			$position = array();
		}

		$add_args = array();
		if ( $name != '' ) {
			$add_args['_name'] = $name;
		}
		if ( $service != '' ) {
			$add_args['_service'] = $service;
		}
		if ( ! empty( $_GET['per_page'] ) ) {
			$add_args['per_page'] = $per_page;
		}
		if ( $near_me != '' ) {
			$add_args['near_me'] = $near_me;
		}

		wp_enqueue_script( 'rcp-user-listing-js' );
		wp_localize_script(
			'rcp-user-listing-js',
			'msrcpAjax',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);

		$query_result = $this->search_profiles(
			array(
				'per_page'   => $per_page,
				'page'       => $current_page,
				'name'       => $name,
				'service'    => $service,
				'is_near_me' => $near_me,
				'position'   => $position,
			)
		);

		$profiles    = $query_result['rows'];
		$total_count = $query_result['count'];

		ob_start();
		?>

		<div class="profile-directory">
			<div class="profile-search">
			<h2 class="profile-search__title">Member Search</h2>
				<div class="profile-search__body">
					<h3 class="profile-search__search_by">Search By</h3>
					<form action="<?php echo esc_url( get_permalink() ); ?>" method="GET">
						<div class="profile-search__fields">
							<input class="profile-search__field-name" type="text" name="_name" value="<?php echo esc_attr( $name ); ?>" placeholder="Name">
							<input class="profile-search__field-service" type="text" name="_service" value="<?php echo esc_attr( $service ); ?>" placeholder="Services">
						</div>
						<div class="profile-search__nearme">
							<label for="input-near-me">Near Me</label>
							<input id="input-near-me" class="profile-search__nearme" type="checkbox" name="near_me" value="1" <?php checked( $near_me, 1, true ); ?>>
						</div>
						<input type="hidden" id="lat" name="lat" value="<?php echo esc_attr( $lat ); ?>">
						<input type="hidden" id="long" name="long" value="<?php echo esc_attr( $long ); ?>">
						<input class="profile-search__submit" type="submit" value="Search">
					</form>
				</div>
			</div><!-- end of .profile-search -->
			<div class="profile-directory-body">
				<?php if ( ! is_wp_error( $profiles ) && ! empty( $profiles ) ) { ?>
					<div class="profile-items">
						<?php
						foreach ( $profiles as $profile ) {
							$profile_id = $profile['ID'];
							?>
							<div class="profile-directory__item">
								<a class="profile-directory__item-link" href="<?php echo esc_url( get_permalink( $profile_id ) ); ?>"><?php echo esc_html( get_the_title( $profile_id ) ); ?></a>
							</div>
							<?php
						}
						?>
					</div><!-- end of .profile-items -->
					<div class="profile-pagination">
						<?php
						global $wp;
						echo paginate_links(
							array(
								'base'     => get_permalink() . '%_%',
								'format'   => '?paged=%#%',
								'total'    => ceil( $total_count / $per_page ),
								'current'  => max( 1, $current_page ),
								'add_args' => $add_args,
							)
						);
						?>
					</div>
				<?php } else { ?>
					<div class="profile-not-found"><?php echo esc_html_e( 'No Profile Found.', 'msrcp' ); ?></div>
				<?php } ?>
			</div><!-- end of .profile-directory-body -->
		</div><!-- end of .profile-directory -->

		<?php
		return ob_get_clean();
	}

	/**
	 * Search profiles
	 *
	 * @param array $args Search args.
	 * @return array ['count' => number, 'rows' => array].
	 */
	private function search_profiles( $args ) {
		$defaults = array(
			'per_page'   => 20,
			'page'       => 1,
			'name'       => '',
			'service'    => '',
			'is_near_me' => false,
			'position'   => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		$per_page     = (int) $args['per_page'];
		$current_page = (int) $args['page'];

		global $wpdb;
		$tbl_usermeta = $wpdb->usermeta;

		$select = 'SELECT posts.*';
		$from   = " FROM {$wpdb->posts} as posts";
		$join   = sprintf( ' INNER JOIN %s AS usermeta ON usermeta.meta_value = posts.ID AND usermeta.meta_key = "%s"', $tbl_usermeta, esc_sql( self::PROFILE_META_KEY ) );
		$where  = sprintf( ' WHERE posts.post_status="publish" AND posts.post_type="%s"', esc_sql( self::CPT_NAME ) );
		if ( ! empty( $args['name'] ) ) {
			$where .= ' AND posts.post_title like "%' . esc_sql( $args['name'] ) . '%"';
		}

		$offset = $per_page * max( 0, ( $current_page - 1 ) );
		$limit  = sprintf( ' LIMIT %d, %d', $offset, $per_page );

		if ( ! empty( $args['service'] ) ) {
			$all_services = get_terms(
				array(
					'taxonomy'   => 'service',
					'hide_empty' => false,
					'search'     => $args['service'],
					'fields'     => 'tt_ids',
				)
			);

			if ( empty( $all_services ) ) {
				return array();
			}

			$service_regexp = ',' . join( '|,', $all_services );

			// $tbl_tr = $wpdb->term_relationships;
			// $join .= sprintf( ' INNER JOIN %s AS tr ON tr.object_id = ', $tbl_tr );
			$join  .= sprintf( ' INNER JOIN %s AS usermeta2 ON usermeta.user_id = usermeta2.user_id AND usermeta2.meta_key = "service_areas"', $tbl_usermeta );
			$where .= sprintf( ' AND concat(",", usermeta2.meta_value) REGEXP "%s"', $service_regexp );
		}

		if ( ! empty( $args['is_near_me'] ) && ! empty( $args['position'] ) ) {
			$lat   = esc_sql( $args['position']['lat'] );
			$long  = esc_sql( $args['position']['long'] );
			$miles = 1;
			$dist  = 0.00021 * $miles;

			// $select .= " ( POW(lati.meta_value - {$lat}, 2) + POW(longi.meta_value - {$long}, 2) ) AS distance";
			$join  .= " LEFT JOIN {$wpdb->usermeta} longi ON longi.user_id = usermeta.user_id AND longi.meta_key = 'longitude'
				LEFT JOIN {$wpdb->usermeta} lati ON lati.user_id = usermeta.user_id AND lati.meta_key = 'latitude'";
			$where .= " AND ( POW(lati.meta_value - {$lat}, 2) + POW(longi.meta_value - {$long}, 2) ) <= {$dist}";
		}

		$sql = $select . $from . $join . $where . $limit;

		var_dump( $sql );

		$count_sql = "SELECT COUNT(*) FROM {$wpdb->posts} as posts" . $join . $where;
		return array(
			'count' => $wpdb->get_var( $count_sql ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'rows'  => $wpdb->get_results( $sql, ARRAY_A ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}
}

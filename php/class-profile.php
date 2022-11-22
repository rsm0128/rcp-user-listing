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
	 * Initiate the resources.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_profile_cpt' ) );
		add_action( 'user_register', array( $this, 'add_profile_post' ), 99, 1 );
		add_action( 'delete_user', array( $this, 'delete_profile_post' ), 99, 1 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_init', array( $this, 'crete_profile_for_existing_users' ) );

		add_filter( 'update_user_metadata', array( $this, 'update_profile_title' ), 10, 4 );
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
			$membership      = new \RCP\Database\Queries\Membership();
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
	public function update_profile_title( $check, $user_id, $meta_key, $meta_value ) {
		if ( self::COMPAMNY_META_KEY === $meta_key ) {
			$profile_id = $this->get_profile_by_user( $user_id );
			if ( ! empty( $profile_id ) ) {
				wp_update_post(
					array(
						'ID'         => $profile_id,
						'post_title' => $meta_value,
					)
				);
			}
		}

		return $check;
	}
}

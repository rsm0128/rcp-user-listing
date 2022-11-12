<?php
/**
 * Profile.
 *
 * @package MetaShortcodeRcp
 */

namespace MetaShortcodeRcp;

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
	const USER_META_KEY = 'profile_id';

	/**
	 * Initiate the resources.
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_profile_cpt' ) );
		add_action( 'user_register', array( $this, 'add_profile_post' ), 99, 2 );
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
		);
		register_post_type( self::CPT_NAME, $args );
	}

	/**
	 * Adds profile cpt post on user creation.
	 *
	 * @param int   $user_id  User ID.
	 * @param array $userdata The raw array of data passed to wp_insert_user().
	 */
	public function add_profile_post( $user_id, $userdata ) {
		// Generate post_title with fname and lname.
		$first_name = empty( $userdata['first_name'] ) ? '' : $userdata['first_name'];
		$last_name  = empty( $userdata['last_name'] ) ? '' : $userdata['last_name'];
		if ( ! empty( $first_name ) && ! empty( $last_name ) ) {
			$post_title = $first_name . ' ' . $last_name;
		} else {
			$post_title = $userdata['user_login'];
		}

		$post_data = array(
			'post_title'  => $post_title,
			'post_author' => $user_id,
			'post_type'   => self::CPT_NAME,
			'post_status' => 'draft',
		);

		$membership      = new \RCP\Database\Queries\Membership();
		$user_membership = $membership->get_item_by( 'user_id', $user_id );
		if ( ! empty( $user_membership ) && 'active' === $user_membership->status ) {
			$post_data['post_status'] = 'publish';
		}

		$post_id = wp_insert_post( $post_data );

		update_user_meta( $user_id, self::USER_META_KEY, $post_id );
	}

	/**
	 * Return profile post id by user id.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_profile_by_user( $user_id ) {
		return get_user_meta( $user_id, self::USER_META_KEY, true );
	}

}

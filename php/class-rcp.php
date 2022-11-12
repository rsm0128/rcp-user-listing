<?php
/**
 * Restrict Content Pro Integration.
 *
 * @package MetaShortcodeRcp
 */

namespace MetaShortcodeRcp;

use MetaShortcodeRcp\Profile;

/**
 * Restrict Content Pro Integration class.
 */
class RCP extends Singletone {

	/**
	 * Initiate the resources.
	 */
	public function init() {
		add_action( 'rcp_new_membership_added', array( $this, 'rcp_new_membership_added' ), 10, 2 );
		add_action( 'rcp_membership_post_activate', array( $this, 'rcp_membership_post_activate' ), 10, 2 );

		add_filter( 'rcp_set_membership_status_value', array( $this, 'rcp_set_membership_status_value' ), 10, 4 );

		add_action( 'rcp_membership_post_cancel', array( $this, 'rcp_membership_post_cancel' ), 10, 2 );
		add_action( 'rcp_membership_post_disable', array( $this, 'rcp_membership_post_cancel' ), 10, 2 );
		add_action( 'rcp_after_membership_admin_update', array( $this, 'rcp_after_membership_admin_update' ), 10, 2 );
	}

	/**
	 * Activate user profile on membership add.
	 *
	 * @param int   $membership_id ID of the membership that was just added.
	 * @param array $data          Membership data.
	 */
	public function rcp_new_membership_added( $membership_id, $data ) {
		if ( 'active' === $data['status'] ) {
			$this->activate_profile_by_user( $data['user_id'] );
		}
	}

	public function rcp_membership_post_activate( $membership_id, $membership ) {
		$this->activate_profile_by_user( $membership->get_user_id() );
	}

	/**
	 *
	 * @param string         $new_status    New status being set.
	 * @param string         $old_status    Old status from before this change.
	 * @param int            $membership_id ID of this membership.
	 * @param RCP_Membership $this          Membership object.
	 */
	public function rcp_set_membership_status_value( $new_status, $old_status, $membership_id, $membership ) {
		if ( 'active' === $new_status ) {
			$this->activate_profile_by_user( $membership->get_user_id() );
		} else {
			$this->deactivate_profile_by_user( $membership->get_user_id() );
		}
		return $new_status;
	}

	/**
	 * Activate profile by user id.
	 *
	 * @param int $user_id User ID.
	 */
	private function activate_profile_by_user( $user_id ) {
		static $cache = array();

		if ( isset( $cache[ $user_id ] ) ) {
			return;
		}

		$profile_id = (int) Profile::get_profile_by_user( $user_id );
		if ( ! empty( $profile_id ) ) {
			wp_publish_post( $profile_id );
			$cache[ $user_id ] = true;
		}
	}

	/**
	 * Deactivate profile on membership cancel or disable.
	 * @param int            $membership_id ID of the membership that was just added.
	 * @param RCP_Membership $membership    Membership data.
	 */
	public function rcp_membership_post_cancel( $membership_id, $membership ) {
		$this->deactivate_profile_by_user( $membership->get_user_id() );
	}

	/**
	 * Deactivate profile on membership cancel or disable.
	 * @param RCP_Membership $membership    Membership data.
	 * @param array          $args Args for admin update.
	 */
	public function rcp_after_membership_admin_update( $membership, $args ) {
		if ( 'active' === $args['status'] ) {
			$this->activate_profile_by_user( $membership->get_user_id() );
		}
	}

	/**
	 * Activate profile by user id.
	 *
	 * @param int $user_id User ID.
	 */
	private function deactivate_profile_by_user( $user_id ) {
		static $cache = array();

		if ( isset( $cache[ $user_id ] ) ) {
			return;
		}

		$profile_id = (int) Profile::get_profile_by_user( $user_id );
		if ( ! empty( $profile_id ) ) {
			wp_update_post(
				array(
					'ID'          => $profile_id,
					'post_status' => 'draft',
				)
			);
			$cache[ $user_id ] = true;
		}
	}
}

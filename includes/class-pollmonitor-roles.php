<?php
/**
 * Handles Role-Based Access Control (RBAC) for PollMonitor
 */

class PollMonitor_Roles {

	public static function init() {
		// Custom roles should be registered on plugin activation
		self::add_roles();
		self::assign_admin_caps();
	}

	private static function add_roles() {
		// 1. Field Observer Role
		// They can only read frontend, submit their own incidents/results. No admin access.
		add_role(
			'pollmonitor_observer',
			__( 'Field Observer', 'pollmonitor' ),
			array(
				'read'                   => true,
				'pollmonitor_submit'     => true,
				'upload_files'           => true, // Needed if they upload photo evidence to media library
			)
		);

		// 2. Regional Validator Role
		// They can read all, approve/edit incidents in their region (assigned via taxonomy/meta).
		add_role(
			'pollmonitor_validator',
			__( 'Regional Validator', 'pollmonitor' ),
			array(
				'read'                   => true,
				'pollmonitor_submit'     => true,
				'pollmonitor_validate'   => true,
				'publish_posts'          => true,
				'edit_published_posts'   => true,
				'edit_others_posts'      => true, // Need to edit observer submissions
                // capabilities for specific CPTs can be mapped here later
			)
		);
	}

	private static function assign_admin_caps() {
		// Ensure standard Admins have all new capabilities
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( 'pollmonitor_submit' );
			$role->add_cap( 'pollmonitor_validate' );
			$role->add_cap( 'pollmonitor_manage_all' );
		}
	}

    public static function remove_roles() {
        // Called on plugin uninstallation, not deactivation, usually
        remove_role( 'pollmonitor_observer' );
        remove_role( 'pollmonitor_validator' );
    }
}

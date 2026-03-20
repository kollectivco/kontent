<?php
/**
 * Roles and capabilities for Kontentainment Charts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AMC_Capabilities {
	/**
	 * Capability registry.
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'amc_view_dashboard',
			'amc_manage_charts',
			'amc_manage_library',
			'amc_manage_weeks',
			'amc_publish_charts',
			'amc_manage_settings',
		);
	}

	/**
	 * Install roles and capabilities.
	 *
	 * @return void
	 */
	public static function install() {
		self::assign_caps_to_role( 'administrator', self::all() );
		self::assign_caps_to_role(
			'editor',
			array(
				'amc_view_dashboard',
				'amc_manage_charts',
				'amc_manage_library',
				'amc_manage_weeks',
				'amc_publish_charts',
			)
		);

		add_role(
			'amc_data_manager',
			'Data Manager',
			array(
				'read'               => true,
				'amc_view_dashboard' => true,
				'amc_manage_charts'  => true,
				'amc_manage_library' => true,
				'amc_manage_weeks'   => true,
			)
		);

		add_role(
			'amc_viewer',
			'Viewer',
			array(
				'read'               => true,
				'amc_view_dashboard' => true,
			)
		);
	}

	/**
	 * Assign caps to an existing role.
	 *
	 * @param string $role_name Role name.
	 * @param array  $caps Capabilities.
	 * @return void
	 */
	private static function assign_caps_to_role( $role_name, $caps ) {
		$role = get_role( $role_name );

		if ( ! $role ) {
			return;
		}

		foreach ( $caps as $cap ) {
			$role->add_cap( $cap );
		}
	}
}

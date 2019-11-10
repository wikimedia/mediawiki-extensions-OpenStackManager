<?php
class OpenStackManagerHooks {

	/**
	 * getUserPermissionsErrors hook
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array &$result
	 * @return bool
	 */
	public static function getUserPermissionsErrors( Title $title, User $user, $action, &$result ) {
		if ( $title->inNamespace( NS_HIERA ) ) {
			if ( $action === 'create' || $action === 'edit' ) {
				$result = [ 'openstackmanager-hiera-disabled' ];
				return false;
			}
		}
		return true;
	}
}

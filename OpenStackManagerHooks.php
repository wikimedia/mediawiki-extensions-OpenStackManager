<?php
class OpenStackManagerHooks {

	/**
	* getUserPermissionsErrors hook
	*
	* @param Title $title
	* @param User $user
	* @param string $action
	* @param $result
	* @return bool
	*/
	public static function getUserPermissionsErrors( Title $title, User $user, $action, &$result ) {
		if ( !$title->inNamespace( NS_HIERA ) ) {
			return true;
		}
		if ( $action === 'create' || $action === 'edit' ) {
			if ( !$user->isLoggedIn() ) {
				$result = [ 'openstackmanager-notloggedin' ];
				return false;
			}
			$userLDAP = new OpenStackNovaUser();
			if ( !$userLDAP->exists() ) {
				$result = [ 'openstackmanager-nonovacred-admincreate' ];
			}
			$project = strtolower( $title->getRootText() );
			if ( !$userLDAP->inRole( 'projectadmin', $project ) && !$user->isAllowed( 'editallhiera' ) ) {
				$result = [ 'openstackmanager-hiera-noadmin', $project ];
				return false;
			}
		}
		return true;
	}
}

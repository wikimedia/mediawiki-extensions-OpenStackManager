<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaRole {
	public $roleid;
	public $roleDN;
	public $roleInfo;
	public $project;

	private $rolename;

	/**
	 * @param $roleid
	 * @param null $project, optional
	 */
	function __construct( $roleid, $project ) {
		$this->roleid = $roleid;
		$this->project = $project;
		OpenStackNovaLdapConnection::connect();

		# Get the name by searching the global role list
		$controller = OpenstackNovaProject::getController();
		$globalrolelist = $controller->getKeystoneRoles();
		$this->rolename = 'unknown role';
		foreach ( $globalrolelist as $id => $name ) {
			if ( $id == $this->roleid ) {
				$this->rolename = $name;
				break;
			}
		}
	}

	/**
	 * @return void
	 */
	function loadMembers() {
		global $wgMemc;

		$roleid = $this->roleid;

		# This caches assignment for all roles in project, not just this one.  Should
		#  save us a bit of time if we check another role later.
		$key = wfMemcKey( 'openstackmanager', "role-assignments", $this->project->getId() );
		$this->members = array();
		$assignments = $wgMemc->get( $key );

		if ( ! is_array( $assignments ) ) {
			$controller = OpenstackNovaProject::getController();

			$assignments = $controller->getRoleAssignmentsForProject( $this->project->getId() );
			$wgMemc->set( $key, $assignments, '3600' );
		}

		if ( in_array( $this->roleid, array_keys( $assignments ) ) ) {
			foreach ($assignments[$this->roleid] as $userid ) {
				$this->members[] = $this->project->memberForUid( $userid );
			}
		}
	}

	/**
	 * @return string
	 */
	function getRoleName() {
		return $this->rolename;
	}

	/**
	 * @return string
	 */
	function getRoleId() {
		return $this->roleid;
	}

	/**
	 * @return array
	 */
	function getMembers() {
		$this->loadMembers();
		return $this->members;
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function deleteMember( $username ) {
		global $wgAuth;

		$user = new OpenStackNovaUser( $username );
		$userid = $user->getUid();
		$controller = OpenstackNovaProject::getController();
		if ( $controller->revokeRoleForProjectAndUser( $this->roleid,
								$this->project->getId(),
								$userid ) ) {
			$user = new OpenStackNovaUser( $userid );
			$this->deleteMemcKeys( $user );
			$wgAuth->printDebug( "Successfully removed $userid from role $this->rolename", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to remove $userid from role $this->rolename", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function addMember( $username ) {
		global $wgAuth;

		$user = new OpenStackNovaUser( $username );
		$userid = $user->getUid();
                $controller = OpenstackNovaProject::getController();
		if ( $controller->grantRoleForProjectAndUser( $this->roleid,
								$this->project->getId(),
								$userid ) ) {
			$wgAuth->printDebug( "Successfully added $userid to $this->rolename", NONSENSITIVE );
			$user = new OpenStackNovaUser( $userid );
			$this->deleteMemcKeys( $user );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $userid to role $this->rolename", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @param $user
	 * @return String string
	 */
	function deleteMemcKeys( $user ) {
		global $wgMemc;

		$projectid = $this->project->getId();
		$role = $this->getRoleId();
		$key = wfMemcKey( 'openstackmanager', "role-assignments", $projectid );
		$wgMemc->delete( $key );
		$username = $user->getUsername();
		$key = wfMemcKey( 'openstackmanager', "fulltoken-$projectid", $username );
		$wgMemc->delete( $key );
		$key = wfMemcKey( 'openstackmanager', 'roles', $user->getUsername() );
		$wgMemc->delete( $key );
		$roleid = $this->roleid;
		$key = wfMemcKey( 'openstackmanager', "role-$roleid-members", $this->project->projectname );
		$wgMemc->delete( $key );
	}

	/**
	 * @param $userLDAP
	 * @return bool
	 */
	function userInRole( $userLDAP ) {
		$this->loadMembers();

		if ( !$userLDAP ) {
			return false;
		}
		$member = explode( '=', $userLDAP );
		$member = explode( ',', $member[1] );
		$member = $member[0];

		return in_array( $member, $this->members );
	}

	/**
	 * @static
	 * @param  $rolename
	 * @param  $project
	 * @return null|OpenStackNovaRole
	 */
	static function getProjectRoleByName( $rolename, $project ) {
		$controller = OpenstackNovaProject::getController();
		$globalrolelist = $controller->getKeystoneRoles();
		foreach ( $globalrolelist as $id => $name ) {
			if ( $name == $rolename ) {
				return new OpenStackNovaRole( $id, $project );
			}
		}
		return null;
	}
}

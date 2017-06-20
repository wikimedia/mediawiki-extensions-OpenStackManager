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

		$this->rolename = OpenStackNovaRole::getRoleNameForId( $this->roleid );
	}

	/**
	 * @return void
	 */
	function loadMembers() {
		global $wgMemc;

		$roleid = $this->roleid;
		$projectid = $this->project->getId();

		$memberskey = wfMemcKey( 'openstackmanager', "role-members-$projectid", $roleid );
		$this->members = $wgMemc->get( $memberskey );
		if ( is_array( $this->members ) ) {
			return;
		}

		# This caches assignment for all roles in project, not just this one.  Should
		#  save us a bit of time if we check another role later.
		$assignmentkey = wfMemcKey( 'openstackmanager', "role-assignments", $this->project->getId() );
		$assignments = $wgMemc->get( $assignmentkey );

		if ( !is_array( $assignments ) ) {
			$controller = OpenstackNovaProject::getController();

			$assignments = $controller->getRoleAssignmentsForProject( $projectid );
			$wgMemc->set( $assignmentkey, $assignments, '3600' );
		}

		$this->members = [];
		if ( in_array( $this->roleid, array_keys( $assignments ) ) ) {
			foreach ( $assignments[$this->roleid] as $userid ) {
				$this->members[] = $this->project->memberForUid( $userid );
			}
		}

		$wgMemc->set( $memberskey, $this->members, '3600' );
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
		$ldap = LdapAuthenticationPlugin::getInstance();
		$user = new OpenStackNovaUser( $username );
		$userid = $user->getUid();
		$controller = OpenstackNovaProject::getController();
		if ( $controller->revokeRoleForProjectAndUser( $this->roleid,
								$this->project->getId(),
								$userid ) ) {
			$user = new OpenStackNovaUser( $userid );
			$this->deleteMemcKeys( $user );
			$ldap->printDebug( "Successfully removed $userid from role $this->rolename", NONSENSITIVE );
			return true;
		} else {
			$ldap->printDebug( "Failed to remove $userid from role $this->rolename", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function addMember( $username ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$user = new OpenStackNovaUser( $username );
		$userid = $user->getUid();
		$controller = OpenstackNovaProject::getController();
		if ( $controller->grantRoleForProjectAndUser( $this->roleid,
								$this->project->getId(),
								$userid ) ) {
			$ldap->printDebug( "Successfully added $userid to $this->rolename", NONSENSITIVE );
			$user = new OpenStackNovaUser( $userid );
			$this->deleteMemcKeys( $user );
			return true;
		} else {
			$ldap->printDebug( "Failed to add $userid to role $this->rolename", NONSENSITIVE );
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
		$key = wfMemcKey( 'openstackmanager', "role-members-$projectid", $role );
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

	/**
	 * @static
	 * @param  $roleid
	 * @return role name
	 */
	static function getRoleNameForId( $roleid ) {
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'globalrolelist' );
		$globalrolelist = $wgMemc->get( $key );
		if ( !is_array( $globalrolelist ) ) {
			$controller = OpenstackNovaProject::getController();
			$globalrolelist = $controller->getKeystoneRoles();

			# Roles basically never change, so this can be a long-lived cache
			$wgMemc->set( $key, $globalrolelist );
		}

		return isset( $globalrolelist[$roleid] ) ? $globalrolelist[$roleid] : "unknown role";
	}
}

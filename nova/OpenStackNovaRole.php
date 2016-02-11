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
	 * @param  $roleid
	 * @param null $project, optional
	 */
	function __construct( $roleid, $project ) {
		$this->roleid = $roleid;
		$this->project = $project;
		OpenStackNovaLdapConnection::connect();
		$this->fetchRoleInfo();
	}

	/**
	 * @return void
	 */
	function fetchRoleInfo() {
		global $wgMemc;
		$controller = OpenstackNovaProject::getController();

		$roleid = $this->roleid;
		$key = wfMemcKey( 'openstackmanager', "role-$roleid-members", $this->project->projectname );
		$this->members = $wgMemc->get( $key );

		if ( ! is_array( $this->members ) ) {
			$this->members = array();

			# This isn't great -- Keystone doesn't have an API to list
			#  members of a given role so we have to check potential members one by one
			foreach ( $this->project->getMemberIds() as $userid ) {
				$roles = $controller->getRolesForProjectAndUser( $this->project->getId(), $userid );
				if ( in_array( $this->roleid, array_keys( $roles ) ) ) {
					$this->members[] = $this->project->memberForUid( $userid );
				}
			}
			$wgMemc->set( $key, $this->members, '3600' );
		}

		# And, get the name by searching the global role list
		$this->rolename = 'unknown role';
		foreach ( $controller->getKeystoneRoles() as $id => $name ) {
			if ( $id == $this->roleid ) {
				$this->rolename = $name;
				break;
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
	 * @return array
	 */
	function getMembers() {
		return $this->members;
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function deleteMember( $username ) {
		global $wgAuth;

		if ( isset( $this->roleInfo[0]['roleoccupant'] ) ) {
			$members = $this->roleInfo[0]['roleoccupant'];
			array_shift( $members );
			$user = new OpenStackNovaUser( $username );
			if ( ! $user->userDN ) {
				$wgAuth->printDebug( "Failed to find userDN in deleteMember", NONSENSITIVE );
				return false;
			}
			$index = array_search( $user->userDN, $members );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find userDN in member list", NONSENSITIVE );
				return false;
			}
			unset( $members[$index] );
			$values = array();
			$values['roleoccupant'] = array();
			foreach ( $members as $member ) {
				$values['roleoccupant'][] = $member;
			}
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->roleDN, $values );
			if ( $success ) {
				$this->deleteMemcKeys( $user );
				$this->fetchRoleInfo();
				$wgAuth->printDebug( "Successfully removed $user->userDN from $this->roleDN", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->roleDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function addMember( $username ) {
		global $wgAuth;

		$members = array();
		if ( isset( $this->roleInfo[0]['roleoccupant'] ) ) {
			$members = $this->roleInfo[0]['roleoccupant'];
			array_shift( $members );
		}
		$user = new OpenStackNovaUser( $username );
		if ( ! $user->userDN ) {
			$wgAuth->printDebug( "Failed to find userDN in addMember", NONSENSITIVE );
			return false;
		}
		$members[] = $user->userDN;
		$values = array();
		$values['roleoccupant'] = $members;
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->roleDN, $values );
		if ( $success ) {
			$this->fetchRoleInfo();
			$wgAuth->printDebug( "Successfully added $user->userDN to $this->roleDN", NONSENSITIVE );
			$this->deleteMemcKeys( $user );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $user->userDN to $this->roleDN", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @param $user
	 * @return String string
	 */
	function deleteMemcKeys( $user ) {
		global $wgMemc;

		$projectname = $this->project->getProjectName();
		$role = $this->getRoleName();
		$key = wfMemcKey( 'openstackmanager', "projectrole-$projectname-$role", $user->userDN );
		$wgMemc->delete( $key );
		$username = $user->getUsername();
		$key = wfMemcKey( 'openstackmanager', "fulltoken-$projectname", $username );
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
	 * @param  $rolename
	 * @param  $project OpenStackNovaProject
	 * @return bool
	 */
	static function createRole( $rolename, $project ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser;

		OpenStackNovaLdapConnection::connect();

		$role = array();
		$role['objectclass'][] = 'organizationalrole';
		$role['cn'] = $rolename;
		$role['roleoccupant'] = $wgOpenStackManagerLDAPUser;
		$roledn = 'cn=' . $rolename . ',' . $project->projectDN;
		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $roledn, $role );
		# TODO: If role addition fails, find a way to fail gracefully
		# Though, if the project was added successfully, it is unlikely
		# that role addition will fail.
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added role $rolename", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add role $rolename", NONSENSITIVE );
			return false;
		}
	}
}

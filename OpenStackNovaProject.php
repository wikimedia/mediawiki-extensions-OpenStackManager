<?php

class OpenStackNovaProject {

	var $projectname;
	var $projectDN;
	var $projectInfo;
	var $roles;

	static $rolenames = array( 'sysadmin', 'netadmin' );

	/**
	 * @param  $projectname
	 */
	function __construct( $projectname ) {
		$this->projectname = $projectname;
		$this->connect();
		$this->fetchProjectInfo();
	}

	/**
	 * @return void
	 */
	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}

	/**
	 * @return void
	 */
	function fetchProjectInfo() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN,
								'(&(cn=' . $this->projectname . ')(owner=*))' );
		$this->projectInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		$this->projectDN = $this->projectInfo[0]['dn'];
		$this->roles = array();
		foreach ( self::$rolenames as $rolename ) {
			$this->roles[] = OpenStackNovaRole::getProjectRoleByName( $rolename, $this );
		}
	}

	/**
	 * @return  String
	 */
	function getProjectName() {
		return $this->projectname;
	}

	/**
	 * @return
	 */
	function getRoles() {
		return $this->roles;
	}

	/**
	 * @return array
	 */
	function getMembers() {
		$members = array();
		if ( isset( $this->projectInfo[0]['member'] ) ) {
			$memberdns = $this->projectInfo[0]['member'];
			array_shift( $memberdns );
			foreach ( $memberdns as $memberdn ) {
				$member = explode( '=', $memberdn );
				$member = explode( ',', $member[1] );
				$member = $member[0];
				$members[] = $member;
			}
		}

		return $members;
	}

	/**
	 * @param  $username string
	 * @return bool
	 */
	function deleteMember( $username ) {
		global $wgAuth;

		if ( isset( $this->projectInfo[0]['member'] ) ) {
			$members = $this->projectInfo[0]['member'];
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
			$values['member'] = array();
			foreach ( $members as $member ) {
				$values['member'][] = $member;
			}
			wfSuppressWarnings();
			$success = ldap_modify( $wgAuth->ldapconn, $this->projectDN, $values );
			wfRestoreWarnings();
			if ( $success ) {
				foreach ( $this->roles as $role ) {
					$success = $role->deleteMember( $username );
					#TODO: Find a way to fail gracefully if role member
					# deletion fails
				}
				$this->fetchProjectInfo();
				$wgAuth->printDebug( "Successfully removed $user->userDN from $this->projectDN", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->projectDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param  $username string
	 * @return bool
	 */
	function addMember( $username ) {
		global $wgAuth;

		$members = array();
		if ( isset( $this->projectInfo[0]['member'] ) ) {
			$members = $this->projectInfo[0]['member'];
			array_shift( $members );
		}
		$user = new OpenStackNovaUser( $username );
		if ( ! $user->userDN ) {
			$wgAuth->printDebug( "Failed to find userDN in addMember", NONSENSITIVE );
			return false;
		}
		$members[] = $user->userDN;
		$values = array();
		$values['member'] = $members;
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->projectDN, $values );
		wfRestoreWarnings();
		if ( $success ) {
			$this->fetchProjectInfo();
			$wgAuth->printDebug( "Successfully added $user->userDN to $this->projectDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $user->userDN to $this->projectDN", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @static
	 * @param  $projectname
	 * @return null|OpenStackNovaProject
	 */
	static function getProjectByName( $projectname ) {
		$project = new OpenStackNovaProject( $projectname );
		if ( $project->projectInfo ) {
			return $project;
		} else {
			return null;
		}
	}

	/**
	 * @static
	 * @return array
	 */
	static function getAllProjects() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$projects = array();
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, '(owner=*)' );
		wfRestoreWarnings();
		if ( $result ) {
			wfSuppressWarnings();
			$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
			wfRestoreWarnings();
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$project = new OpenStackNovaProject( $entry['cn'][0] );
					array_push( $projects, $project );
				}
			}
		}

		return $projects;
	}

	/**
	 * @static
	 * @param  $projectname
	 * @return bool
	 */
	static function createProject( $projectname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$project = array();
		$project['objectclass'][] = 'groupofnames';
		$project['objectclass'][] = 'posixgroup';
		$project['cn'] = $projectname;
		$project['owner'] = $wgOpenStackManagerLDAPUser;
		$project['gidnumber'] = OpenStackNovaUser::getNextIdNumber( $wgAuth, 'gidnumber' );
		$projectdn = 'cn=' . $projectname . ',' . $wgOpenStackManagerLDAPProjectBaseDN;

		wfSuppressWarnings();
		$success = ldap_add( $wgAuth->ldapconn, $projectdn, $project );
		wfRestoreWarnings();
		$project = new OpenStackNovaProject( $projectname );
		if ( $success ) {
			foreach ( self::$rolenames as $rolename ) {
				$role = OpenStackNovaRole::createRole( $rolename, $project );
				# TODO: If role addition fails, find a way to fail gracefully
				# Though, if the project was added successfully, it is unlikely
				# that role addition will fail.
			}
			$wgAuth->printDebug( "Successfully added project $projectname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add project $projectname", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @static
	 * @param  $projectname String
	 * @return bool
	 */
	static function deleteProject( $projectname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$project = new OpenStackNovaProject( $projectname );
		if ( ! $project ) {
			return false;
		}
		$dn = $project->projectDN;

		# Projects can have roles as sub-entries, we need to delete them first
		$result = ldap_list( $wgAuth->ldapconn, $dn, 'objectclass=*' );
		$roles = ldap_get_entries( $wgAuth->ldapconn, $result );
		array_shift( $roles );
		foreach ( $roles as $role ) {
			$roledn = $role['dn'];
			wfSuppressWarnings();
			$success = ldap_delete( $wgAuth->ldapconn, $roledn );
			wfRestoreWarnings();
			if ( $success ){
				$wgAuth->printDebug( "Successfully deleted role $roledn", NONSENSITIVE );
			} else {
				$wgAuth->printDebug( "Failed to delete role $roledn", NONSENSITIVE );
			}
		}
		wfSuppressWarnings();
		$success = ldap_delete( $wgAuth->ldapconn, $dn );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted project $projectname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete project $projectname", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @static
	 * @return void
	 */
	static function addNamespaces() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;
		global $wgExtraNamespaces;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, 'owner=*' );
		$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $entries ) {
			array_shift( $entries );
			foreach ( $entries as $entry ) {
				$id = (int)$entry['gidnumber'][0];
				$talkid = $id + 1;
				$name = ucwords( $entry['cn'][0] );
				$wgAuth->printDebug( "Adding namespace $name", NONSENSITIVE );
				$wgExtraNamespaces[$id] = $name;
				$wgExtraNamespaces[$talkid] = $name . '_talk';
			}
		} else {
			$wgAuth->printDebug( "Failed to find projects", NONSENSITIVE );
		}
	}

}

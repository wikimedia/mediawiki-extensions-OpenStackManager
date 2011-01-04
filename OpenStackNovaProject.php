<?php

class OpenStackNovaProject {

	var $projectname;
	var $projectDN;
	var $projectInfo;

	function __construct( $projectname ) {
		$this->projectname = $projectname;
		$this->connect();
		$this->fetchProjectInfo();
	}

	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}

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
	}

	function getProjectName() {
		return $this->projectname;
	}

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
			$values['member'] = array();
			foreach ( $members as $member ) {
				$values['member'][] = $member;
			}
			wfSuppressWarnings();
			$success = ldap_modify( $wgAuth->ldapconn, $this->projectDN, $values );
			wfRestoreWarnings();
			if ( $success ) {
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
		$values['member'] = $members;
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->projectDN, $values );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added $user->userDN to $this->projectDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $user->userDN to $this->projectDN", NONSENSITIVE );
			return false;
		}
	}

	static function getAllProjects() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		$wgAuth->connect();
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

	static function createProject( $projectname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$project['objectclass'][] = 'groupofnames';
		$project['objectclass'][] = 'posixgroup';
		$project['cn'] = $projectname;
		$project['owner'] = $wgOpenStackManagerLDAPUser;
		$project['gidnumber'] = OpenStackNovaUser::getNextIdNumber( $wgAuth, 'gidnumber' );
		$dn = 'cn=' . $projectname . ',' . $wgOpenStackManagerLDAPProjectBaseDN;

		wfSuppressWarnings();
		$success = ldap_add( $wgAuth->ldapconn, $dn, $project );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added project $projectname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add project $projectname", NONSENSITIVE );
			return false;
		}
	}

	static function deleteProject( $projectname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;


		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$project = new OpenStackNovaProject( $projectname );
		if ( ! $project ) {
			return false;
		}
		$dn = $project->projectDN;

		# Projects can have roles as sub entries, fail if they exist
		# It is a bad idea to rely on LDAP failure here, as some directories
		# may simply delete sub entries.
		$result = ldap_list( $wgAuth->ldapconn, $dn, 'objectclass=*' );
		$roles = ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $roles['count'] != "0" ) {
			return false;
		}
		wfSuppressWarnings();
		$success = ldap_delete( $wgAuth->ldapconn, $dn );
		wfRestoreWarnings();
		if ( $success ) {
			return true;
		} else {
			return false;
		}
	}

	static function addNamespaces() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;
		global $wgExtraNamespaces;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, 'owner=*' );
		$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $entries ) {
			array_shift($entries);
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

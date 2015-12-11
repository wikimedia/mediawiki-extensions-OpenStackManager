<?php

/**
 * Most OpenStackNovaProjects will have an corresponding
 * OpenStackNovaProjectGroup.  These are posixgroups that
 * are named after the Project Name.  This allows for
 * management of unix group permissions, without potentially
 * conflicting project names with existing unix groups.
 * e.g. If someone creates a project named 'root', the
 * corresponding posix group will be called 'project-root'
 * instead of 'root'.
 *
 * OpenStackNovaProject should manage the creation and
 * addition of members to this group.  Its members
 * are the canonical source of members for the group.
 * If ever the Project member list changes,
 * the ProjectGroup member list should be updated to match.
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaProjectGroup  {
	public $projectName;
	public $projectGroupInfo;
	public $projectGroupDN;
	public $loaded;

	static $prefix = 'project-';

	/**
	 * @param  $projectname
	 * @param bool $load
	 */
	function __construct( $projectName, $load=true ) {
		$this->projectName = $projectName;
		if ( $load ) {
			OpenStackNovaLdapConnection::connect();
			$this->fetchProjectGroupInfo();
		} else {
			$this->loaded = false;
		}
	}

	/**
	 * Fetch the project group from LDAP and initialize the object
	 * @return void
	 */
	function fetchProjectGroupInfo( $refresh=true ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectGroupBaseDN;

		if ( $this->loaded and !$refresh ) {
			return;
		}
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectGroupBaseDN,
								'(&(cn=' . $this->getProjectGroupName() . ')(objectclass=groupofnames))' );
		$this->projectGroupInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( !isset( $this->projectGroupInfo[0] ) ) {
			$this->loaded = false;
			return;
		}

		$this->projectGroupDN = $this->projectGroupInfo[0]['dn'];
		$this->loaded = true;
	}

	/**
	 * Returns the project group name, which is
	 * just the corresponding project name prefixed
	 * by self::$prefix.
	 *
	 * @return string
	 */
	function getProjectGroupName() {
		return self::$prefix . $this->projectName;
	}

	/**
	 * Return all users who are a member of this project
	 *
	 * @return array
	 */
	function getMembers() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPDomain;

		$members = array();
		if ( isset( $this->projectGroupInfo[0]['member'] ) ) {
			$memberdns = $this->projectGroupInfo[0]['member'];
			array_shift( $memberdns );
			foreach ( $memberdns as $memberdn ) {
				$searchattr = $wgAuth->getConf( 'SearchAttribute', $wgOpenStackManagerLDAPDomain );
				if ( $searchattr ) {
					// We need to look up the search attr from the user entry
					// this is expensive, but must be done.
					// TODO: memcache this
					$userInfo = $wgAuth->getUserInfoStateless( $memberdn );
					$members[] = $userInfo[0][$searchattr][0];
				} else {

					$member = explode( '=', $memberdn );
					$member = explode( ',', $member[1] );
					$member = $member[0];
					$members[] = $member;
				}
			}
		}

		return $members;
	}

	/**
	 * Returns an array of all member DNs that belong to this project group.
	 *
	 * @return array
	 */
	function getMemberDNs() {
		$memberDNs = array();
		if ( isset( $this->projectGroupInfo[0]['member'] ) ) {
			$memberDNs = $this->projectGroupInfo[0]['member'];
			array_shift( $memberDNs );
			sort( $memberDNs );
		}
		return $memberDNs;
	}

	/**
	 * Remove a member from the project group based on username
	 *
	 * @param  $username string
	 * @return bool
	 */
	function deleteMember( $username ) {
		global $wgAuth;

		if ( isset( $this->projectGroupInfo[0]['member'] ) ) {
			$members = $this->projectGroupInfo[0]['member'];
			array_shift( $members );
			$user = new OpenStackNovaUser( $username );
			if ( ! $user->userDN ) {
				$wgAuth->printDebug( "Failed to find userDN for username $username in OpenStackNovaProjectGroup deleteMember.", NONSENSITIVE );
				return false;
			}
			$index = array_search( $user->userDN, $members );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find userDN " . $user->userDN . " in in ProjectGroup " . $this->projectGroupName . " member list", NONSENSITIVE );
				return false;
			}
			unset( $members[$index] );
			$values = array();
			$values['member'] = array();
			foreach ( $members as $member ) {
				$values['member'][] = $member;
			}
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->projectGroupDN, $values );
			if ( $success ) {
				$this->fetchProjectGroupInfo( true );
				$wgAuth->printDebug( "Successfully removed $user->userDN from $this->projectGroupDN", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->projectGroupDN: " . ldap_error($wgAuth->ldapconn), NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}


	/**
	 * Takes an array of memberDNs and saves it to the project group in LDAP.
	 *
	 * @param $memberDNs array as returned by getMemberDNs().
	 * @return bool
	 */
	function setMembers( $memberDNs ) {
		global $wgAuth;

		$values = array( 'member' => $memberDNs );
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->projectGroupDN, $values );

		if ( $success ) {
			// reload the ProjectGroup from LDAP.
			$this->fetchProjectGroupInfo( true );
			$wgAuth->printDebug( "Successfully set " . count( $memberDNs ) . " members to $this->projectGroupDN", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to set " . count( $memberDNs ) . " members to $this->projectGroupDN: " . ldap_error( $wgAuth->ldapconn ) . ". [" . join( ";", $memberDNs ) . "]", NONSENSITIVE );
		}

		return $success;
	}

	/**
	 * Add a member to this project based on username
	 *
	 * @param $username string
	 * @return bool
	 */
	function addMember( $username ) {
		global $wgAuth;

		$members = array();
		if ( isset( $this->projectGroupInfo[0]['member'] ) ) {
			$members = $this->projectGroupInfo[0]['member'];
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
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->projectGroupDN, $values );
		if ( $success ) {
			$this->fetchProjectGroupInfo( true );
			$wgAuth->printDebug( "Successfully added $user->userDN to $this->projectGroupDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $user->userDN to $this->projectGroupDN: " . ldap_error($wgAuth->ldapconn), NONSENSITIVE );
			return false;
		}
	}


	/**
	 * Create a new project group based on project name.
	 *
	 * @static
	 * @param  $projectname
	 * @return bool
	 */
	static function createProjectGroup( $projectname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectGroupBaseDN;

		OpenStackNovaLdapConnection::connect();

		$projectGroupName = self::$prefix . $projectname;
		$projectGroup = array();
		$projectGroup['objectclass'][] = 'posixgroup';
		$projectGroup['objectclass'][] = 'groupofnames';
		$projectGroup['cn'] = $projectGroupName;
		$projectGroup['gidnumber'] = OpenStackNovaUser::getNextIdNumber( $wgAuth, 'gidnumber' );
		$projectGroupDN = 'cn=' . $projectGroupName . ',' . $wgOpenStackManagerLDAPProjectGroupBaseDN;

		# TODO: If project group creation fails we need to be able to fail gracefully
		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $projectGroupDN, $projectGroup );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added project group $projectGroupName", NONSENSITIVE );
		}
		else {
			$wgAuth->printDebug( "Failed to add project group $projectGroupName: " . ldap_error( $wgAuth->ldapconn ), NONSENSITIVE );
		}
		return $success;
	}

	/**
	 * Deletes a project group based on project name.
	 *
	 * @param  $projectname String
	 * @return bool
	 */
	static function deleteProjectGroup( $projectname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectGroupBaseDN;

		OpenStackNovaLdapConnection::connect();

		$projectGroupName = self::$prefix . $projectname;
		$projectGroupDN = 'cn=' . $projectGroupName . ',' . $wgOpenStackManagerLDAPProjectGroupBaseDN;

		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $projectGroupDN );
		if ( $success ){
			$wgAuth->printDebug( "Successfully deleted project group $projectGroupDN", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to delete project group $projectGroupDN: " . ldap_error( $wgAuth->ldapconn ), NONSENSITIVE );
		}
		return $success;
	}

}

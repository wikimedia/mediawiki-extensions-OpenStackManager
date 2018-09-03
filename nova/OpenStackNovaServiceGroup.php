<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaServiceGroup {
	public $groupName;
	public $groupDN;
	public $groupInfo;
	public $project;

	/**
	 * @param string $groupName
	 * @param OpenStackNovaProject $project
	 */
	function __construct( $groupName, $project ) {
		$this->project = $project;
		OpenStackNovaLdapConnection::connect();

		$this->groupName = $groupName;
		$this->fetchGroupInfo( false );
	}

	/**
	 * @param bool $refreshCache
	 * @return void
	 */
	function fetchGroupInfo( $refreshCache = true ) {
		global $wgOpenStackManagerLDAPServiceGroupBaseDN;
		global $wgMemc;

		# Load service group entry
		$dn = $wgOpenStackManagerLDAPServiceGroupBaseDN;
		$query = '(cn=' . $this->groupName . ')';

		$key = wfMemcKey( 'openstackmanager', 'servicegroup', $this->groupName );

		if ( $refreshCache ) {
			$wgMemc->delete( $key );
			$groupInfo = null;
		} else {
			$groupInfo = $wgMemc->get( $key );
		}
		if ( is_array( $groupInfo ) ) {
			$this->groupInfo = $groupInfo;
		} else {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$result = LdapAuthenticationPlugin::ldap_search( $ldap->ldapconn, $dn, $query );
			$this->groupInfo = LdapAuthenticationPlugin::ldap_get_entries(
				$ldap->ldapconn, $result
			);
			$wgMemc->set( $key, $this->groupInfo, 3600 * 24 );
		}

		if ( $this->groupInfo['count'] != "0" ) {
			$this->groupDN = $this->groupInfo[0]['dn'];
		}

		$this->usersDN = "ou=people" . "," . $wgOpenStackManagerLDAPServiceGroupBaseDN;
	}

	/**
	 * @return string
	 */
	function getGroupName() {
		return $this->groupName;
	}

	/**
	 * @return string
	 */
	function getSpecialUserDN() {
		$userDN = "uid=" . $this->groupName . "," . $this->usersDN;
		return $userDN;
	}

	/**
	 * @return array
	 */
	function getUidMembers() {
		$members = [];
		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$memberdns = $this->groupInfo[0]['member'];
			if ( $memberdns['count'] === 0 ) {
				return $members;
			}
			array_shift( $memberdns );
			foreach ( $memberdns as $memberdn ) {
				$info = explode( ',', $memberdn );
				$info = explode( '=', $info[0] );
				$attr = $info[0];
				$member = $info[1];
				if ( $attr === 'uid' ) {
					$members[] = $member;
				} else {
					$ldap = LdapAuthenticationPlugin::getInstance();
					$userInfo = $ldap->getUserInfoStateless( $memberdn );
					$members[] = $userInfo[0]['uid'][0];
				}
			}
		}
		return $members;
	}

	/**
	 * @return array
	 */
	function getMembers() {
		global $wgOpenStackManagerLDAPDomain;

		$members = [];
		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$memberdns = $this->groupInfo[0]['member'];
			array_shift( $memberdns );
			foreach ( $memberdns as $memberdn ) {
				$searchattr = $ldap->getConf( 'SearchAttribute', $wgOpenStackManagerLDAPDomain );
				if ( $searchattr ) {
					$userInfo = $ldap->getUserInfoStateless( $memberdn );
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
	 * @param string $username
	 * @return bool
	 */
	function isMember( $username ) {
		return in_array( strtolower( $username ), array_map( 'strtolower', $this->getMembers() ) );
	}

	/**
	 * @param string $username
	 * @return bool
	 */
	function deleteMember( $username ) {
		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$members = $this->groupInfo[0]['member'];
			array_shift( $members );

			$user = new OpenStackNovaUser( $username );
			if ( !$user->userDN ) {
				$ldap->printDebug( "Failed to find $username in deleteMember", NONSENSITIVE );
				return false;
			}

			$index = array_search( $user->userDN, $members );
			if ( $index === false ) {
				$ldap->printDebug( "Failed to find userDN in member list", NONSENSITIVE );
				return false;
			}
			unset( $members[$index] );
			$values = [];
			$values['member'] = [];
			foreach ( $members as $member ) {
				$values['member'][] = $member;
			}
			$success = LdapAuthenticationPlugin::ldap_modify(
				$ldap->ldapconn, $this->groupDN, $values
			);
			if ( $success ) {
				$this->fetchGroupInfo();
				$ldap->printDebug(
					"Successfully removed $user->userDN from $this->groupDN", NONSENSITIVE
				);
			} else {
				$ldap->printDebug(
					"Failed to remove $user->userDN from $this->groupDN", NONSENSITIVE
				);
				return false;
			}
		} else {
			return false;
		}
		return true;
	}

	/**
	 * @param array $usernames
	 * @param array $serviceUsernames
	 * @return bool
	 */
	function setMembers( $usernames, $serviceUsernames = [] ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$members = [];
		foreach ( $usernames as $username ) {
			$userDN = "";
			$user = new OpenStackNovaUser( $username );
			if ( !$user->userDN ) {
				$ldap->printDebug( "Failed to find userDN in setMembers", NONSENSITIVE );
				return false;
			}
			$userDN = $user->userDN;

			$members[] = $userDN;
		}
		foreach ( $serviceUsernames as $serviceUsername ) {
			$userDN = "uid=" . $serviceUsername . "," . $this->usersDN;
			$members[] = $userDN;
		}
		$values = [];
		$values['member'] = $members;
		$success = LdapAuthenticationPlugin::ldap_modify(
			$ldap->ldapconn, $this->groupDN, $values
		);
		if ( $success ) {
			$this->fetchGroupInfo();
			$ldap->printDebug( "Successfully set members for $this->groupDN", NONSENSITIVE );
		} else {
			$ldap->printDebug( "Failed to set members for $this->groupDN", NONSENSITIVE );
			return false;
		}
		return true;
	}

	/**
	 * @param string $username
	 * @return bool
	 */
	function addMember( $username ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$members = [];
		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$members = $this->groupInfo[0]['member'];
			array_shift( $members );
		}

		$userDN = "";
		$user = new OpenStackNovaUser( $username );
		if ( !$user->userDN ) {
			$ldap->printDebug( "Failed to find userDN in addMember", NONSENSITIVE );
			return false;
		}
		$userDN = $user->userDN;

		$members[] = $userDN;
		$values = [];
		$values['member'] = $members;
		$success = LdapAuthenticationPlugin::ldap_modify(
			$ldap->ldapconn, $this->groupDN, $values
		);
		if ( $success ) {
			$this->fetchGroupInfo();
			$ldap->printDebug( "Successfully added $userDN to $this->groupDN", NONSENSITIVE );
		} else {
			$ldap->printDebug( "Failed to add $userDN to $this->groupDN", NONSENSITIVE );
			return false;
		}
		return true;
	}

	/**
	 * @static
	 * @param string $groupName
	 * @param OpenStackNovaProject $project
	 * @return null|OpenStackNovaServiceGroup
	 */
	static function getServiceGroupByName( $groupName, $project ) {
		$group = new OpenStackNovaserviceGroup( $groupName, $project );
		if ( $group->groupInfo ) {
			return $group;
		} else {
			return null;
		}
	}

	/**
	 * @static
	 * @param string $inGroupName
	 * @param OpenStackNovaProject $project
	 * @param string $initialUser
	 * @return null|OpenStackNovaServiceGroup
	 */
	static function createServiceGroup( $inGroupName, $project, $initialUser ) {
		global $wgOpenStackManagerLDAPDefaultShell;
		global $wgOpenStackManagerLDAPServiceGroupBaseDN;
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$projectPrefix = $project->getProjectName() . '.';
		# We don't want naming collisions between service groups and actual groups
		# or users.  So, prepend $projectPrefix to the requested group name.
		if ( strpos( $inGroupName, $projectPrefix, 0 ) === 0 ) {
			# The user was clever and already added the prefix.
			$groupName = $inGroupName;
			$simpleGroupName = substr( $inGroupName, strlen( $projectPrefix ) );
		} else {
			$groupName = $projectPrefix . $inGroupName;
			$simpleGroupName = $inGroupName;
		}

		if ( $initialUser ) {
			$user = new OpenStackNovaUser( $initialUser );
			if ( !$user->userDN ) {
				$ldap->printDebug(
					"Unable to find initial user $initialUser for new group $groupName",
					NONSENSITIVE
				);
				return null;
			}
			$initialUserDN = $user->userDN;
		}

		$key = wfMemcKey( 'openstackmanager', 'servicegroup', $groupName );
		$wgMemc->delete( $key );

		$group = [];
		$group['objectclass'][] = 'posixgroup';
		$group['objectclass'][] = 'groupofnames';
		$group['cn'] = $groupName;
		$groupdn = 'cn=' . $groupName . ',' . $wgOpenStackManagerLDAPServiceGroupBaseDN;
		$group['gidnumber'] = OpenStackNovaUser::getNextIdNumber( $ldap, 'gidnumber' );
		$group['member'] = [];
		if ( $initialUser ) {
			$group['member'][] = $initialUserDN;
		}

		$success = LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $groupdn, $group );
		if ( $success ) {
			$ldap->printDebug( "Successfully added service group $groupdn", NONSENSITIVE );
		} else {
			$ldap->printDebug( "Failed to add service group $groupdn", NONSENSITIVE );
			return null;
		}

		# stamp out regular expressions!
		$homeDir = $project->getServiceGroupHomedirPattern();
		$homeDir = str_ireplace( '%u', $simpleGroupName, $homeDir );
		$homeDir = str_ireplace( '%p', $projectPrefix, $homeDir );

		# Now create the special SG member
		$newGroup = self::getServiceGroupByName( $groupName, $project );
		$userdn = $newGroup->getSpecialUserDN();
		$user = [];
		$user['objectclass'][] = 'shadowaccount';
		$user['objectclass'][] = 'posixaccount';
		$user['objectclass'][] = 'person';
		$user['objectclass'][] = 'top';
		$user['loginshell'] = $wgOpenStackManagerLDAPDefaultShell;
		$user['homedirectory'] = $homeDir;
		$user['uidnumber'] = $group['gidnumber'];
		$user['gidnumber'] = $group['gidnumber'];
		$user['uid'] = $groupName;
		$user['sn'] = $groupName;
		$user['cn'] = $groupName;
		$success = LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $userdn, $user );

		if ( $success ) {
			$ldap->printDebug( "Successfully created service user $userdn", NONSENSITIVE );
		} else {
			$ldap->printDebug( "Failed to create service user $userdn", NONSENSITIVE );
			return null;
		}

		# Create Sudo policy so that members of the group can sudo as the service user
		if ( OpenStackNovaSudoer::createSudoer( 'runas-' . $groupName,
				$project->getProjectName(),
				[ "%" . $groupName ],
				[ $groupName ],
				[ 'ALL' ],
				[ '!authenticate' ] ) ) {
			$ldap->printDebug( "Successfully created run-as sudo policy for $groupName",
				NONSENSITIVE );
		} else {
			$ldap->printDebug( "Failed to  creat run-as sudo policy for $groupName",
				NONSENSITIVE );
		}

		return $newGroup;
	}

	/**
	 * @static
	 * @param string $groupName
	 * @param OpenStackNovaProject $project
	 * @return bool
	 */
	static function deleteServiceGroup( $groupName, $project ) {
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$group = self::getServiceGroupByName( $groupName, $project );
		if ( !$group ) {
			$ldap->printDebug(
				"We are trying to delete a nonexistent service group, $groupName", NONSENSITIVE
			);
			return false;
		}

		# Delete our special member.
		$success = LdapAuthenticationPlugin::ldap_delete(
			$ldap->ldapconn, $group->getSpecialUserDN()
		);
		if ( $success ) {
			$ldap->printDebug( "Successfully deleted service user $groupName", NONSENSITIVE );
		} else {
			$ldap->printDebug( "Failed to delete service user $groupName", NONSENSITIVE );
			return false;
		}

		# Now delete the group.
		$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $group->groupDN );
		if ( $success ) {
			$ldap->printDebug( "Successfully deleted service group $groupName", NONSENSITIVE );
			$key = wfMemcKey( 'openstackmanager', 'servicegroup', $groupName );
			$wgMemc->delete( $key );
		} else {
			$ldap->printDebug( "Failed to delete service group $groupName", NONSENSITIVE );
			return false;
		}

		return true;
	}
}

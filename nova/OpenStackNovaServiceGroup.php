<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaServiceGroup {
	var $groupName;
	var $groupDN;
	var $groupInfo;
	var $project;

	/**
	 * @param  $groupname string
	 * @param  $project OpenStackNovaProject
	 */
	function __construct( $groupName, $project ) {
		$this->groupName = $groupName;
		$this->project = $project;
		OpenStackNovaLdapConnection::connect();
		$this->fetchGroupInfo();
	}

	/**
	 * @return void
	 */
	function fetchGroupInfo() {
		global $wgAuth;

		# Load service group entry
		$dn = $this->project->getServiceGroupOUDN();
		$query = '(cn=' . $this->groupName . ')';
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $dn, $query );
		$this->groupInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $this->groupInfo['count'] != "0" ) {
			$this->groupDN = $this->groupInfo[0]['dn'];
		}

		$this->usersDN = "ou=people" . "," . $this->project->projectDN;
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
	function getMembers() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPDomain;

		$members = array();
		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$memberdns = $this->groupInfo[0]['member'];
			array_shift( $memberdns );
			foreach ( $memberdns as $memberdn ) {
				$searchattr = $wgAuth->getConf( 'SearchAttribute', $wgOpenStackManagerLDAPDomain );
				if ( $searchattr ) {
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
	 * @param $username
	 * @return bool
	 */
	function isMember( $username ) {
		return in_array( strtolower( $username ), array_map( 'strtolower', $this->getMembers() ) );
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function deleteMember( $username ) {
		global $wgAuth;

		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$members = $this->groupInfo[0]['member'];
			array_shift( $members );

			$user = new OpenStackNovaUser( $username );
			if ( ! $user->userDN ) {
				$wgAuth->printDebug( "Failed to find $username in deleteMember", NONSENSITIVE );
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
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->groupDN, $values );
			if ( $success ) {
				$this->fetchGroupInfo();
				$wgAuth->printDebug( "Successfully removed $user->userDN from $this->groupDN", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->groupDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param  $serviceusername
	 * @return bool
	 */
	function deleteServiceMember( $serviceUserName ) {
		global $wgAuth;

		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$members = $this->groupInfo[0]['member'];
			array_shift( $members );

			$userDN = "uid=" . $serviceUserName . "," . $this->usersDN ;
			$index = array_search( $userDN, $members );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find $userDN in member list", NONSENSITIVE );
				return false;
			}
			unset( $members[$index] );
			$values = array();
			$values['member'] = array();
			foreach ( $members as $member ) {
				$values['member'][] = $member;
			}
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->groupDN, $values );
			if ( $success ) {
				$this->fetchGroupInfo();
				$wgAuth->printDebug( "Successfully removed $userDN from $this->groupDN", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $userDN from $this->groupDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * @param  $serviceUserName
	 * @return bool
	 */
	function addServiceMember( $serviceUserName ) {
		global $wgAuth;

		$members = array();
		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$members = $this->groupInfo[0]['member'];
			array_shift( $members );
		}

		$userDN = "uid=" . $serviceUserName . "," . $this->usersDN ;
		$members[] = $userDN;
		$values = array();
		$values['member'] = $members;
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->groupDN, $values );
		if ( $success ) {
			$this->fetchGroupInfo();
			$wgAuth->printDebug( "Successfully added $userDN to $this->groupDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $userDN to $this->groupDN", NONSENSITIVE );
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
		if ( isset( $this->groupInfo[0]['member'] ) ) {
			$members = $this->groupInfo[0]['member'];
			array_shift( $members );
		}

		$userDN = "";
		$user = new OpenStackNovaUser( $username );
		if ( ! $user->userDN ) {
			$wgAuth->printDebug( "Failed to find userDN in addMember", NONSENSITIVE );
			return false;
		}
		$userDN = $user->userDN;

		$members[] = $userDN;
		$values = array();
		$values['member'] = $members;
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->groupDN, $values );
		if ( $success ) {
			$this->fetchGroupInfo();
			$wgAuth->printDebug( "Successfully added $userDN to $this->groupDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $userDN to $this->groupDN", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @static
	 * @param  $groupName
	 * @param  $project OpenStackNovaProject
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
	 * @param  $groupName
	 * @param  $project OpenStackNovaProject
	 * @param  $initialUser
	 * @return null|OpenStackNovaServiceGroup
	 */
	static function createServiceGroup( $inGroupName, $project, $initialUser ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser;
		global $wgOpenStackManagerServiceGroupPrefix;
		global $wgOpenStackManagerLDAPDefaultShell;

		OpenStackNovaLdapConnection::connect();

		# We don't want naming collisions between service groups and actual groups
		# or users.  So, prepend $wgOpenStackManagerServiceGroupPrefix to the requested group name.
		if ( strpos( $inGroupName, $wgOpenStackManagerServiceGroupPrefix, 0 ) === 0 ) {
			# The user was clever and already added the prefix.
			$groupName = $inGroupName;
			$simpleGroupName = substr( $inGroupName, strlen( $wgOpenStackManagerServiceGroupPrefix ) );
		} else {
			$groupName = $wgOpenStackManagerServiceGroupPrefix . $inGroupName;
			$simpleGroupName = $inGroupName;
		}

		$user = new OpenStackNovaUser( $initialUser );
		if ( ! $user->userDN ) {
			$wgAuth->printDebug( "Unable to find initial user $initialUser for new group $groupName", NONSENSITIVE );
			return false;
		}
		$initialUserDN = $user->userDN;

		$group = array();
		$group['objectclass'][] = 'posixgroup';
		$group['objectclass'][] = 'groupofnames';
		$group['cn'] = $groupName;
		$groupdn = 'cn=' . $groupName . ',' . 'ou=groups' . ',' . $project->projectDN;
		$group['gidnumber'] = OpenStackNovaUser::getNextIdNumber( $wgAuth, 'gidnumber' );
		$group['member'] = array();
		$group['member'][] = $initialUserDN;
		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $groupdn, $group );

		if ( $success ) {
			$wgAuth->printDebug( "Successfully added service group $groupdn", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to add service group $groupdn", NONSENSITIVE );
			return null;
		}

		# stamp out regular expressions!
		$homeDir = $project->getServiceGroupHomedirPattern();
		$homeDir = str_ireplace('%u', $simpleGroupName, $homeDir);
		$homeDir = str_ireplace('%p', $wgOpenStackManagerServiceGroupPrefix, $homeDir);

		# Now create the special SG member
		$newGroup = self::getServiceGroupByName( $groupName, $project );
		$userdn = $newGroup->getSpecialUserDN();
		$user = array();
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
		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $userdn, $user );

		if ( $success ) {
			$wgAuth->printDebug( "Successfully created service user $userdn", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to create service user $userdn", NONSENSITIVE );
			return null;
		}

		# Create Sudo policy so that the service user can chown files in its homedir
		if ( OpenStackNovaSudoer::createSudoer( $groupName . '-chmod',
				$project->projectname,
				array( $groupName ),
				array( 'ALL' ),
				array(),
				array( 'chown -R ' . $groupName . ':' . $groupName . ' ' . $homeDir ),
				array( '!authenticate' ) ) ) {
			$wgAuth->printDebug( "Successfully created chmod sudo policy for $groupName",
				NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to  creat chmod sudo policy for $groupName",
				NONSENSITIVE );
		}

		# Create Sudo policy so that members of the group can sudo as the service user
		if ( OpenStackNovaSudoer::createSudoer( 'runas-' . $groupName,
				$project->projectname,
				array( "%" . $groupName ),
				array( 'ALL' ),
				array( $groupName ),
				array( 'ALL' ),
				array( '!authenticate' ) ) ) {
			$wgAuth->printDebug( "Successfully created run-as sudo policy for $groupName",
				NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to  creat run-as sudo policy for $groupName",
				NONSENSITIVE );
		}

		return $newGroup;
	}

	/**
	 * @static
	 * @param  $groupName
	 * @param  $project OpenStackNovaProject
	 * @return bool
	 */
	static function deleteServiceGroup( $groupName, $project ) {
		global $wgAuth;
		$group = self::getServiceGroupByName( $groupName, $project );
		if ( !$group ) {
			$wgAuth->printDebug( "We are trying to delete a nonexistent service group, $groupName", NONSENSITIVE );
			return false;
		}

		# Delete our special member.
		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $group->getSpecialUserDN() );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted service user $groupName", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to delete service user $groupName", NONSENSITIVE );
			return false;
		}

		# Now delete the group.
		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $group->groupDN );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted service group $groupName", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to delete service group $groupName", NONSENSITIVE );
			return false;
		}

		return true;
	}
}

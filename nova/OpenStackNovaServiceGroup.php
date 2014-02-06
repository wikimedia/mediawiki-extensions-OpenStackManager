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

	var $old_shool_groupName;
	var $old_school_groupDN;
	var $old_school_groupInfo;

	/**
	 * @param  $groupname string
	 * @param  $project OpenStackNovaProject
	 */
	function __construct( $groupName, $project ) {
		global $wgOpenStackManagerServiceGroupPrefix;

		$this->old_school_groupName = $groupName;
		$this->project = $project;
		OpenStackNovaLdapConnection::connect();

		$simpleGroupName = substr( $groupName, strlen( $wgOpenStackManagerServiceGroupPrefix ) );
		$this->groupName = $project->getProjectName() . '.' . $simpleGroupName;
		$this->fetchGroupInfo( false );
	}

	/**
	 * @return void
	 */
	function fetchOldSchoolGroupInfo() {
		global $wgAuth;

		# Load service group entry
		$dn = $this->project->getServiceGroupOUDN();
		$query = '(cn=' . $this->old_school_groupName . ')';

		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $dn, $query );
		$this->old_school_groupInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $this->old_school_groupInfo['count'] != "0" ) {
			$this->old_school_groupDN = $this->old_school_groupInfo[0]['dn'];
		}

		$this->old_school_usersDN = "ou=people" . "," . $this->project->projectDN;
	}

	/**
	 * @return void
	 */
	function fetchGroupInfo( $refreshCache = true ) {
		global $wgAuth;
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
			$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $dn, $query );
			$this->groupInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
			$wgMemc->set( $key, $this->groupInfo, 3600 * 24 );
		}

		if ( $this->groupInfo['count'] != "0" ) {
			$this->groupDN = $this->groupInfo[0]['dn'];
		}

		$this->usersDN = "ou=people" . "," . $wgOpenStackManagerLDAPServiceGroupBaseDN;
		$this->fetchOldSchoolGroupInfo();
	}

	/**
	 * @return string
	 */
	function getGroupName() {
		return $this->old_school_groupName;
	}

	/**
	 * @return string
	 */
	function getSpecialUserDN() {
		$userDN = "uid=" . $this->groupName . "," . $this->usersDN;
		return $userDN;
	}

	/**
	 * @return string
	 */
	function getOldSchoolSpecialUserDN() {
		$userDN = "uid=" . $this->old_school_groupName . "," . $this->old_school_usersDN;
		return $userDN;
	}

	/**
	 * @return array
	 */
	function getUidMembers() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPDomain;

		$members = array();
		if ( isset( $this->old_school_groupInfo[0]['member'] ) ) {
			$memberdns = $this->old_school_groupInfo[0]['member'];
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
					$userInfo = $wgAuth->getUserInfoStateless( $memberdn );
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
		global $wgAuth;
		global $wgOpenStackManagerLDAPDomain;

		$members = array();
		if ( isset( $this->old_school_groupInfo[0]['member'] ) ) {
			$memberdns = $this->old_school_groupInfo[0]['member'];
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
			} else {
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->groupDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
		$this->deleteOldSchoolMember( $username );
		return true;
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function deleteOldSchoolMember( $username ) {
		global $wgAuth;

		if ( isset( $this->old_school_groupInfo[0]['member'] ) ) {
			$members = $this->old_school_groupInfo[0]['member'];
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
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->old_school_groupDN, $values );
			if ( $success ) {
				$this->fetchGroupInfo();
				$wgAuth->printDebug( "Successfully removed $user->userDN from $this->old_school_groupDN", NONSENSITIVE );
			} else {
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->old_school_groupDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
		return true;
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function setMembers( $usernames, $serviceUsernames=array() ) {
		global $wgAuth;

		$members = array();
		foreach ( $usernames as $username ) {
			$userDN = "";
			$user = new OpenStackNovaUser( $username );
			if ( ! $user->userDN ) {
				$wgAuth->printDebug( "Failed to find userDN in setMembers", NONSENSITIVE );
				return false;
			}
			$userDN = $user->userDN;

			$members[] = $userDN;
		}
		foreach ( $serviceUsernames as $serviceUsername ) {
			$userDN = "uid=" . $serviceUsername . "," . $this->usersDN;
			$members[] = $userDN;
		}
		$values = array();
		$values['member'] = $members;
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->groupDN, $values );
		if ( $success ) {
			$this->fetchGroupInfo();
			$wgAuth->printDebug( "Successfully set members for $this->groupDN", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to set members for $this->groupDN", NONSENSITIVE );
			return false;
		}
		$this->setOldSchoolMembers( $usernames, $serviceUsernames );
		return true;
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function setOldSchoolMembers( $usernames, $serviceUsernames=array() ) {
		global $wgAuth;

		$members = array();
		foreach ( $usernames as $username ) {
			$userDN = "";
			$user = new OpenStackNovaUser( $username );
			if ( ! $user->userDN ) {
				$wgAuth->printDebug( "Failed to find userDN in setMembers", NONSENSITIVE );
				return false;
			}
			$userDN = $user->userDN;

			$members[] = $userDN;
		}
		foreach ( $serviceUsernames as $serviceUsername ) {
			$userDN = "uid=" . $serviceUsername . "," . $this->old_school_usersDN;
			$members[] = $userDN;
		}
		$values = array();
		$values['member'] = $members;
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->old_school_groupDN, $values );
		if ( $success ) {
			$this->fetchGroupInfo();
			$wgAuth->printDebug( "Successfully set members for $this->groupDN", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to set members for $this->groupDN", NONSENSITIVE );
			return false;
		}
		return true;
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
		} else {
			$wgAuth->printDebug( "Failed to add $userDN to $this->groupDN", NONSENSITIVE );
			return false;
		}
		$this->addOldSchoolMember( $username );
		return true;
	}

	/**
	 * @param  $username
	 * @return bool
	 */
	function addOldSchoolMember( $username ) {
		global $wgAuth;

		$members = array();
		if ( isset( $this->old_school_groupInfo[0]['member'] ) ) {
			$members = $this->old_school_groupInfo[0]['member'];
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
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->old_school_groupDN, $values );
		if ( $success ) {
			$this->fetchGroupInfo();
			$wgAuth->printDebug( "Successfully added $userDN to $this->groupDN", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to add $userDN to $this->groupDN", NONSENSITIVE );
			return false;
		}
		return true;
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
	 * @return null|OpenStackNovaServiceGroup
	 */
	static function getServiceGroupByNewSchoolName( $groupName, $project ) {
		global $wgOpenStackManagerServiceGroupPrefix;

		$projectPrefix = $project->getProjectName() . '.';
		if ( strpos( $groupName, $projectPrefix, 0 ) === 0 ) {
			$simpleGroupName = substr( $groupName, strlen( $projectPrefix ) );
		} else {
			$simpleGroupName = $groupName;
		}

		$old_school_groupName = $wgOpenStackManagerServiceGroupPrefix . $simpleGroupName;

		$group = new OpenStackNovaserviceGroup( $old_school_groupName, $project );
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
		global $wgOpenStackManagerLDAPDefaultShell;
		global $wgOpenStackManagerServiceGroupPrefix;
		global $wgOpenStackManagerLDAPServiceGroupBaseDN;
		global $wgMemc;

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
		$old_school_groupName = $wgOpenStackManagerServiceGroupPrefix . $simpleGroupName;

		if ( $initialUser ) {
			$user = new OpenStackNovaUser( $initialUser );
			if ( ! $user->userDN ) {
				$wgAuth->printDebug( "Unable to find initial user $initialUser for new group $groupName", NONSENSITIVE );
				return null;
			}
			$initialUserDN = $user->userDN;
		}

		$key = wfMemcKey( 'openstackmanager', 'servicegroup', $groupName );
		$wgMemc->delete( $key );

		$group = array();
		$group['objectclass'][] = 'posixgroup';
		$group['objectclass'][] = 'groupofnames';
		$group['cn'] = $groupName;
		$groupdn = 'cn=' . $groupName . ',' . $wgOpenStackManagerLDAPServiceGroupBaseDN;
		$group['gidnumber'] = OpenStackNovaUser::getNextIdNumber( $wgAuth, 'gidnumber' );
		$group['member'] = array();
		if ( $initialUser ) {
			$group['member'][] = $initialUserDN;
		}

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
		$homeDir = str_ireplace('%p', $projectPrefix, $homeDir);

		# Now create the special SG member
		$newGroup = self::getServiceGroupByName( $old_school_groupName, $project );
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
				$project->getProjectName(),
				array( $groupName ),
				array( 'ALL' ),
				array(),
				array( '/bin/chown -R ' . $groupName . '\:' . $groupName . ' ' . $homeDir ),
				array( '!authenticate' ) ) ) {
			$wgAuth->printDebug( "Successfully created chmod sudo policy for $groupName",
				NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to  creat chmod sudo policy for $groupName",
				NONSENSITIVE );
		}

		# Create Sudo policy so that members of the group can sudo as the service user
		if ( OpenStackNovaSudoer::createSudoer( 'runas-' . $groupName,
				$project->getProjectName(),
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

		self::createOldSchoolServiceGroup( $simpleGroupName, $project, $initialUser );

		return $newGroup;
	}

	/**
	 * @static
	 * @param  $groupName
	 * @param  $project OpenStackNovaProject
	 * @param  $initialUser
	 * @return null|OpenStackNovaServiceGroup
	 */
	static function createOldSchoolServiceGroup( $inGroupName, $project, $initialUser ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser;
		global $wgOpenStackManagerServiceGroupPrefix;
		global $wgOpenStackManagerLDAPDefaultShell;

		OpenStackNovaLdapConnection::connect();

		# We don't want naming collisions between service groups and actual groups
		# of users.  So, prepend $wgOpenStackManagerServiceGroupPrefix to the requested group name.
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
			return null;
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
		$userdn = $newGroup->getOldSchoolSpecialUserDN();
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
				$project->getProjectName(),
				array( $groupName ),
				array( 'ALL' ),
				array(),
				array( '/bin/chown -R ' . $groupName . '\:' . $groupName . ' ' . $homeDir ),
				array( '!authenticate' ) ) ) {
			$wgAuth->printDebug( "Successfully created chmod sudo policy for $groupName",
				NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to  creat chmod sudo policy for $groupName",
				NONSENSITIVE );
		}

		# Create Sudo policy so that members of the group can sudo as the service user
		if ( OpenStackNovaSudoer::createSudoer( 'runas-' . $groupName,
				$project->getProjectName(),
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
	static function deleteNewSchoolServiceGroup( $groupName, $project ) {
		global $wgAuth;
		global $wgMemc;

		$group = self::getServiceGroupByNewSchoolName( $groupName, $project );
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
			$key = wfMemcKey( 'openstackmanager', 'servicegroup', $groupName );
			$wgMemc->delete( $key );
		} else {
			$wgAuth->printDebug( "Failed to delete service group $groupName", NONSENSITIVE );
			return false;
		}

		return true;
	}

	/**
	 * @static
	 * @param  $groupName
	 * @param  $project OpenStackNovaProject
	 * @return bool
	 */
	static function deleteServiceGroup( $groupName, $project ) {
		global $wgAuth;
		global $wgOpenStackManagerServiceGroupPrefix;

		$group = self::getServiceGroupByName( $groupName, $project );
		if ( !$group ) {
			$wgAuth->printDebug( "We are trying to delete a nonexistent service group, $groupName", NONSENSITIVE );
			return false;
		}

		# Delete our special member.
		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $group->getOldSchoolSpecialUserDN() );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted service user $groupName", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to delete service user $groupName", NONSENSITIVE );
			return false;
		}

		# Now delete the group.
		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $group->old_school_groupDN );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted service group $groupName", NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to delete service group $groupName", NONSENSITIVE );
			return false;
		}

		$simpleGroupName = substr( $groupName, strlen( $wgOpenStackManagerServiceGroupPrefix ) );
		$new_school_groupName = $project->getProjectName() . '.' . $simpleGroupName;
		self::deleteNewSchoolServiceGroup( $new_school_groupName, $project );

		return true;
	}
}

<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaProject {
	var $projectname;
	var $projectDN;
	var $projectInfo;
	var $roles;
	var $loaded;
	var $projectGroup;

	// list of roles
	static $rolenames = array( 'sysadmin', 'netadmin' );

	/**
	 * @param  $projectname
	 * @param bool $load
	 */
	function __construct( $projectname, $load=true ) {
		$this->projectname = $projectname;
		if ( $load ) {
			OpenStackNovaLdapConnection::connect();
			$this->fetchProjectInfo();
		} else {
			$this->loaded = false;
		}
	}

	/**
	 * Fetch the project from LDAP and initialize the object
	 * @return void
	 */
	function fetchProjectInfo( $refresh=true ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		if ( $this->loaded and !$refresh ) {
			return;
		}
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN,
								'(&(cn=' . $this->projectname . ')(objectclass=groupofnames))' );
		$this->projectInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
		$this->projectDN = $this->projectInfo[0]['dn'];
		$this->roles = array();
		foreach ( self::$rolenames as $rolename ) {
			$this->roles[] = OpenStackNovaRole::getProjectRoleByName( $rolename, $this );
		}
		// fetch the associated posix project group (project-$projectname)
		$this->fetchProjectGroup();

		$this->loaded = true;
	}


	/**
	 * Initializes the corresponding project group object for this project.
	 * If the ProjectGroup can't be loaded OR if the existing ProjectGroup
	 * is a virtual static group, then the ProjectGroup will be recreated
	 * from scratch and the members will be synced from this Project's
	 * list of members.
	 *
	 * @return void
	 */
	function fetchProjectGroup() {
		global $wgAuth;
		$this->projectGroup = new OpenStackNovaProjectGroup( $this->projectname );

		// If we couldn't find an corresponding Project Group,
		// then we should create one now.
		if ( !$this->projectGroup->loaded or $this->projectGroup->isVirtual() ) {
			$wgAuth->printDebug( $this->projectGroup->getProjectGroupName() . " either does not exist or is a virtual static group.  Recreating is as a real group and syncing members.", NONSENSITIVE );

			// Delete, recreate, and then sync the members.
			$deleteSuccess = OpenStackNovaProjectGroup::deleteProjectGroup( $this->projectname );
			// if we successfully deleted the ProjectGroup, then recreate it now.
			if ( $deleteSuccess ) {
				$createSuccess = OpenStackNovaProjectGroup::createProjectGroup( $this->projectname );
				// Aaaaand if we successfully created the group, then finally sync the members from this project now.
				if ( $createSuccess ) {
					$this->projectGroup = new OpenStackNovaProjectGroup( $this->projectname );
					$this->syncProjectGroupMembers();
				}
			}
		}
	}

	/**
	 * @return  string
	 */
	function getProjectName() {
		return $this->projectname;
	}


	/**
	 * Returns the corresponding ProjectGroup for this Project.
	 * If necessary, the ProjectGroup will be loaded from LDAP.
	 *
	 * @return OpenStackNovaProjectGroup
	 */
	function getProjectGroup() {
		if ( !$this->loaded ) {
			$this->fetchProjectGroup();
		}
		return $this->projectGroup;
	}

	/**
	 * Return all roles for this project
	 * @return array
	 */
	function getRoles() {
		return $this->roles;
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
		if ( isset( $this->projectInfo[0]['member'] ) ) {
			$memberdns = $this->projectInfo[0]['member'];
			// The first element in the member list is the count
			// of entries in the list.  We don't want that!
			// Shift it off.
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
	 * Returns an array of all member DNs that belong to this project.
	 *
	 * @return array
	 */
	function getMemberDNs() {
		$memberDNs = array();
		if ( isset( $this->projectInfo[0]['member'] ) ) {
			$memberDNs = $this->projectInfo[0]['member'];
			// The first element in the member list is the count
			// of entries in the list.  We don't want that!
			// Shift it off.
			array_shift( $memberDNs );
			sort( $memberDNs );
		}
		return $memberDNs;
	}

	function getProjectDN() {
		return $this->projectDN;
	}

	function getSudoersDN() {
		return 'ou=sudoers,' . $this->projectDN;
	}

	/**
	 * Remove a member from the project based on username
	 *
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
				$wgAuth->printDebug( "Failed to find userDN for username $username in OpenStackNovaProject deleteMember", NONSENSITIVE );
				return false;
			}
			$index = array_search( $user->userDN, $members );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find userDN " . $user->userDN . " in Project " . $this->projectname . " member list", NONSENSITIVE );
				return false;
			}
			unset( $members[$index] );
			$values = array();
			$values['member'] = array();
			foreach ( $members as $member ) {
				$values['member'][] = $member;
			}

			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->projectDN, $values );
			if ( $success ) {
				// If we successfully deleted the Project Member, then also
				// delete the member from the corresponding ProjectGroup.
				$this->projectGroup->deleteMember( $username );

				foreach ( $this->roles as $role ) {
					$role->deleteMember( $username );
					# @todo Find a way to fail gracefully if role member
					# deletion fails
				}
				$sudoers = OpenStackNovaSudoer::getAllSudoersByProject( $this->getProjectName() );
				foreach ( $sudoers as $sudoer ) {
					$success = $sudoer->deleteUser( $username );
					if ( $success ) {
						$wgAuth->printDebug( "Successfully removed $username from " . $sudoer->getSudoerName(), NONSENSITIVE );
					} else {
						$wgAuth->printDebug( "Failed to remove $username from " . $sudoer->getSudoerName(), NONSENSITIVE );
					}
				}
				$this->fetchProjectInfo(true);
				$wgAuth->printDebug( "Successfully removed $user->userDN from $this->projectDN", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->projectDN: " . ldap_error($wgAuth->ldapconn), NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
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

		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->projectDN, $values );


		if ( $success ) {
			// If we successfully added the member to this Project, then
			// also add the member to the corresponding ProjectGroup.
			$this->projectGroup->addMember( $username );

			$this->fetchProjectInfo( true );
			$wgAuth->printDebug( "Successfully added $user->userDN to $this->projectDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $user->userDN to $this->projectDN: " . ldap_error($wgAuth->ldapconn), NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Compares members between this Project and its
	 * corresponding ProjectGroup.  If they differ,
	 * Then the entire member list for the ProjectGroup
	 * will be overwritten with this list of members.
	 *
	 * @return int -1 on failure, 0 on nochange, and 1 on a successful sync
	 */
	function syncProjectGroupMembers() {
		$failure  = -1;
		$nochange =  0;
		$synced   =  1;

		// if we're not using project groups, just return $nochange.
		if ( !OpenStackNovaProject::useProjectGroup() ) {
			$retval = $nochange;
		}

		else {
			// These both return a sorted array of Member DNs
			$projectMemberDNs      = $this->getMemberDNs();
			$projectGroupMemberDNs = $this->projectGroup->getMemberDNs();

			// These two arrays should be exactly the same, 
			// so comparing them using == should work.
			// If they are not the same, then modify the
			// project group member list so that it exactly
			// matches the list from the project.
			if ( $projectMemberDNs != $projectGroupMemberDNs ) {
				$sync_success = $this->projectGroup->setMembers( $projectMemberDNs );
				$retval = $sync_success == true ? $synced : $failure;
			}
			else {
				$retval = $nochange;
			}
		}

		return $retval;
	}

	static function useProjectGroup() {
		global $wgOpenStackManagerLDAPProjectBaseDN;

		if ( $wgOpenStackManagerLDAPProjectBaseDN ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Return a project by its project name. Returns null if the project does not exist.
	 *
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

	static function getProjectsByName( $projectnames ) {
		$projects = array();
		foreach ( $projectnames as $projectname ) {
			$project = new OpenStackNovaProject( $projectname );
			if ( $project->projectInfo ) {
				array_push( $projects, $project );
			}
		}
		return $projects;
	}

	/**
	 * Return all existing projects. Returns an empty array if no projects exist. This function
	 * lazy loads the projects. Objects will be returned unloaded. If you wish to receive more
	 * than just the project's name, you'll need to call the project's fetchProjectInfo() function.
	 *
	 * @static
	 * @return array
	 */
	static function getAllProjects() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		OpenStackNovaLdapConnection::connect();

		$projects = array();
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, '(objectclass=groupofnames)' );
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$project = new OpenStackNovaProject( $entry['cn'][0], false );
					array_push( $projects, $project );
				}
			}
		}

		sort( $projects );
		return $projects;
	}

	/**
	 * Create a new project based on project name. This function will also create
	 * all roles needed by the project.
	 *
	 * @static
	 * @param  $projectname
	 * @return bool
	 */
	static function createProject( $projectname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgOpenStackManagerLDAPProjectGroupBaseDN;

		OpenStackNovaLdapConnection::connect();

		$project = array();
		$project['objectclass'][] = 'groupofnames';
		$project['cn'] = $projectname;
		$project['member'] = $wgOpenStackManagerLDAPUser;
		$projectdn = 'cn=' . $projectname . ',' . $wgOpenStackManagerLDAPProjectBaseDN;

		// if we're not going to use project groups, 
		// then create this project as a posixgroup
		if ( !OpenStackNovaProject::useProjectGroup() ) {
			$project['gidnumber'] = OpenStackNovaUser::getNextIdNumber( $wgAuth, 'gidnumber' );
			$project['objectclass'][] = 'posixgroup';
		}

		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $projectdn, $project );
		$project = new OpenStackNovaProject( $projectname );
		if ( $success ) {
			foreach ( self::$rolenames as $rolename ) {
				OpenStackNovaRole::createRole( $rolename, $project );
				# TODO: If role addition fails, find a way to fail gracefully
				# Though, if the project was added successfully, it is unlikely
				# that role addition will fail.
			}
			$sudoerOU = array();
			$sudoerOU['objectclass'][] = 'organizationalunit';
			$sudoerOU['ou'] = 'sudooers';
			$sudoerOUdn = 'ou=sudoers,' . $projectdn;
			LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $sudoerOUdn, $sudoerOU );
			# TODO: If sudoerOU creation fails we need to be able to fail gracefully
			$wgAuth->printDebug( "Successfully added project $projectname", NONSENSITIVE );

			// Now that we've created the Project, if we
			// are supposed to use a corresponding Project Group
			// to manage posix group permissions, do so now.
			if ( OpenStackNovaProject::useProjectGroup() ) {
				OpenStackNovaProjectGroup::createProjectGroup( $projectname );
				# TODO: If project group creation fails we need to be able to fail gracefully
			}
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add project $projectname", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Deletes a project based on project name. This function will also delete all roles
	 * associated with the project.
	 *
	 * @param  $projectname String
	 * @return bool
	 */
	static function deleteProject( $projectname ) {
		global $wgAuth;

		OpenStackNovaLdapConnection::connect();

		$project = new OpenStackNovaProject( $projectname );
		if ( ! $project ) {
			return false;
		}
		$dn = $project->projectDN;
		# Projects can have roles as sub-entries, we need to delete them first
		$result = LdapAuthenticationPlugin::ldap_list( $wgAuth->ldapconn, $dn, 'objectclass=*' );
		$roles = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
		array_shift( $roles );
		foreach ( $roles as $role ) {
			$roledn = $role['dn'];
			$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $roledn );
			if ( $success ){
				$wgAuth->printDebug( "Successfully deleted role $roledn", NONSENSITIVE );
			} else {
				$wgAuth->printDebug( "Failed to delete role $roledn", NONSENSITIVE );
			}
		}

		# Projects can have a separate group entry.  If so, delete it now.
		if ( OpenStackNovaProject::useProjectGroup() ) {
			OpenStackNovaProjectGroup::deleteProjectGroup( $projectname );
		}

		# Projects have a sudo OU and sudoers entries below that OU, we must delete them first
		$sudoers = OpenStackNovaSudoer::getAllSudoersByProject( $project->getProjectName() );
		foreach ( $sudoers as $sudoer ) {
			$success = OpenStackNovaSudoer::deleteSudoer( $sudoer->getSudoerName(), $project->getProjectName() );
			if ( $success ){
				$wgAuth->printDebug( "Successfully deleted sudoer " . $sudoer->getSudoerName(), NONSENSITIVE );
			} else {
				$wgAuth->printDebug( "Failed to delete sudoer " . $sudoer->getSudoerName(), NONSENSITIVE );
			}
		}
		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $project->getSudoersDN() );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted sudoers OU " .  $project->getSudoersDN(), NONSENSITIVE );
		} else {
			$wgAuth->printDebug( "Failed to delete sudoers OU " .  $project->getSudoersDN(), NONSENSITIVE );
		}
		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $dn );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted project $projectname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete project $projectname", NONSENSITIVE );
			return false;
		}
	}

	function editArticle() {
		global $wgOpenStackManagerCreateProjectSALPages;

		if ( ! OpenStackNovaArticle::canCreatePages() ) {
			return;
		}

		$format = <<<RESOURCEINFO
{{Nova Resource
|Resource Type=project
|Project Name=%s
|Members=%s}}
__NOEDITSECTION__
RESOURCEINFO;
		$rawmembers = $this->getMembers();
		$members = array();
		foreach ( $rawmembers as $member ) {
			array_push( $members, 'User:' . $member );
		}
		$text = sprintf( $format,
			$this->getProjectName(),
			implode( ',', $members )
		);
		OpenStackNovaArticle::editArticle( $this->getProjectName(), $text );
		if ( $wgOpenStackManagerCreateProjectSALPages ) {
			$pagename = $this->getProjectName() . "/SAL";
			$id = Title::newFromText( $pagename, NS_NOVA_RESOURCE )->getArticleId();
			$article = Article::newFromId( $id );
			$content = '';
			if ( $article ) {
				$content = $article->getRawText();
			}
			$text = "{{SAL|Project Name=" . $this->getProjectName() . "}}";
			if ( !strstr( $content, $text ) ) {
				OpenStackNovaArticle::editArticle( $pagename, $text );
			}
		}
	}

	function deleteArticle() {
		OpenStackNovaArticle::deleteArticle( $this->getProjectName() );
	}
}

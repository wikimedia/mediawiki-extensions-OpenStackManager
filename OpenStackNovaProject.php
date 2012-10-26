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
		$this->loaded = true;
	}

	/**
	 * @return  string
	 */
	function getProjectName() {
		return $this->projectname;
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

		$members = array();
		if ( isset( $this->projectInfo[0]['member'] ) ) {
			$memberdns = $this->projectInfo[0]['member'];
			array_shift( $memberdns );
			foreach ( $memberdns as $memberdn ) {
				$searchattr = $wgAuth->getConf( 'SearchAttribute' );
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

	function getProjectDN() {
		return $this->projectDN;
	}

	function getProjectGroupDN() {
		global $wgOpenStackManagerLDAPProjectGroupBaseDN;

		return 'cn=project-' . $this->getProjectName() . ',' . $wgOpenStackManagerLDAPProjectGroupBaseDN;
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
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->projectDN, $values );
			if ( $success ) {
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
				$wgAuth->printDebug( "Failed to remove $user->userDN from $this->projectDN", NONSENSITIVE );
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
			$this->fetchProjectInfo(true);
			$wgAuth->printDebug( "Successfully added $user->userDN to $this->projectDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $user->userDN to $this->projectDN", NONSENSITIVE );
			return false;
		}
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

		$projectGroup = array();
		if ( OpenStackNovaProject::useProjectGroup() ) {
			// Split posix group away from project name, prefixed by project
			$projectGroupName = 'project-' . $projectname;
			$projectGroup['objectclass'][] = 'posixgroup';
			$projectGroup['objectclass'][] = 'groupofnames';
			$projectGroup['objectclass'][] = 'ds-virtual-static-group';
			$projectGroup['cn'] = $projectGroupName;
			$projectGroup['gidnumber'] = OpenStackNovaUser::getNextIdNumber( $wgAuth, 'gidnumber' );
			$projectGroup['ds-target-group-dn'] = $projectdn;
			$projectGroupdn = 'cn=' . $projectGroupName . ',' . $wgOpenStackManagerLDAPProjectGroupBaseDN;
		} else {
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
			if ( $projectGroup ) {
				LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $projectGroupdn, $projectGroup );
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
		# Projects can have a separate group entry
		if ( OpenStackNovaProject::useProjectGroup() ) {
			$groupdn = $project->getProjectGroupDN();
			$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $groupdn );
			if ( $success ){
				$wgAuth->printDebug( "Successfully deleted group $groupdn", NONSENSITIVE );
			} else {
				$wgAuth->printDebug( "Failed to delete group $groupdn", NONSENSITIVE );
			}
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

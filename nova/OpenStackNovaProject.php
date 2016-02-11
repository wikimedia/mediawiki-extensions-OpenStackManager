<?php

/**
 * Class to manage Projects, project roles, service groups.
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaProject {
	public $projectname;
	public $projectDN;
	public $projectInfo;
	public $roles;
	public $loaded;
	public $projectGroup;

	// list of roles
	static $visiblerolenames = array( 'projectadmin' );

	// short-lived cache of project objects
	static $projectCache = array();
	static $projectCacheMaxSize = 200;

	/**
	 * @param  $projectname
	 * @param bool $load
	 */
	function __construct( $projectid, $load=true ) {
		$this->projectid = $projectid;
		$this->projectname = "";
		if ( $load ) {
			OpenStackNovaLdapConnection::connect();
			$this->fetchProjectInfo();
		} else {
			$this->loaded = false;
		}
	}

	public function setName( $projectname ) {
		$this->projectname = $projectname;
	}

	public function getName() {
		if ( !$this->projectname ) {
			$this->loadProjectName();
		}
		return $this->projectname;
	}

	public function getId() {
		return $this->projectid;
	}

	function loadProjectName() {
		$controller = OpenstackNovaProject::getController();
		$this->projectname = $controller->getProjectName( $this->projectid );
	}

	/**
	 * Fetch the project from keystone initialize the object
	 * @return void
	 */
	function fetchProjectInfo( $refresh=true ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		if ( $this->loaded and !$refresh ) {
			return;
		}

		if ( !$this->projectname || $refresh ) {
			$this->loadProjectName();
		}

		$this->roles = array();
		foreach ( self::$visiblerolenames as $rolename ) {
			$this->roles[] = OpenStackNovaRole::getProjectRoleByName( $rolename, $this );
		}

		// fetch the associated posix project group (project-$projectname)
		$this->fetchProjectGroup();

		$this->fetchServiceGroups();

		// For legacy purposes, still read in the ldap data.  This can be removed once we
		//  are writing via keystone as well:
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN,
								'(&(cn=' . $this->projectname . ')(objectclass=groupofnames))' );
		$this->projectInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $this->projectInfo['count'] === 0 ) {
			return;
		}
		$this->projectDN = $this->projectInfo[0]['dn'];

		$this->loaded = true;
	}

	function fetchServiceGroups() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPServiceGroupBaseDN;

		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn,
				$wgOpenStackManagerLDAPServiceGroupBaseDN,
				'(objectclass=groupofnames)' );

		if ( $result ) {
			$this->serviceGroups = array();
			$groupList = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
			if ( isset( $groupList ) ) {
				array_shift( $groupList );
				foreach ( $groupList as $groupEntry ) {
					# Now we have every group.  Check if this one belongs to us.
					$matchstring = $this->projectname . ".";
					if ( strpos($groupEntry['cn'][0], $matchstring) === 0 ) {
						$this->serviceGroups[] = new OpenStackNovaServiceGroup( $groupEntry['cn'][0], $this );
					}
				}
			}
		} else {
			$this->serviceGroups = array();
		}

		$serviceUserBaseDN = "ou=people" . "," . $wgOpenStackManagerLDAPServiceGroupBaseDN;
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn,
				$serviceUserBaseDN,
				'(objectclass=person)' );

		if ( $result ) {
			$this->serviceUsers = array();
			$userList = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
			if ( isset( $userList ) ) {
				array_shift( $userList );
				foreach ( $userList as $userEntry ) {
					# Now we have every user.  Check if this one belongs to us.
					$matchstring = $this->projectname . ".";
					if ( strpos($userEntry['cn'][0], $matchstring) === 0 ) {
						$wgAuth->printDebug( "adding " . $userEntry['cn'][0], NONSENSITIVE );
						$this->serviceUsers[] = $userEntry['cn'][0];
					}
				}
			}
		} else {
			$this->serviceUsers = array();
		}
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
		if ( !$this->projectGroup->loaded ) {
			$wgAuth->printDebug( $this->projectGroup->getProjectGroupName() . " does not exist.  Creating it.", NONSENSITIVE );

			$createSuccess = OpenStackNovaProjectGroup::createProjectGroup( $this->projectname );
			// Aaaaand if we successfully created the group, then finally sync the members from this project now.
			if ( $createSuccess ) {
				$this->projectGroup = new OpenStackNovaProjectGroup( $this->projectname );
				$this->syncProjectGroupMembers();
			}
		}
	}

	/**
	 * @return  string
	 */
	function getProjectName() {
		return $this->getName();
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
	 * Return all service groups for this project
	 * @return array
	 */
	function getServiceGroups() {
		return $this->serviceGroups;
	}

	/**
	 * Return all service users for this project
	 * @return array
	 */
	function getServiceUsers() {
		return $this->serviceUsers;
	}

	/**
	 * Fill $this->members.
	 *
	 * $this->members uses the uid as index and the name as value.
	 *
	 * @return array
	 */
	function loadMembers() {
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'projectuidsandmembers', $this->projectname );
		$this->members = $wgMemc->get( $key );

		if ( is_array( $this->members ) ) {
			return;
		}

		$controller = OpenstackNovaProject::getController();
		$this->members = $controller->getUsersInProject( $this->projectid );

		$wgMemc->set( $key, $this->members, '3600' );
	}

	/**
	 * Return UIDs for users who are a member of this project
	 *
	 * We need this for managing things related to sudoers; generating
	 * the list is expensive and caching it here is a big speedup.
	 *
	 * @return array
	 */
	function getMemberUids() {
		$this->loadMembers();
		return array_keys( $this->members );
	}

	/**
	 * Return all users who are a member of this project
	 *
	 * @return array
	 */
	function getMembers() {
		$this->loadMembers();
		return array_values( $this->members );
	}

	/**
	 * Return Ids of each user who is a member of this project
	 *
	 * @return array
	 */
	function getMemberIds() {
		$this->loadMembers();
		return array_keys( $this->members );
	}

	function memberForUid( $uid ) {
		$this->loadMembers();
		return $this->members[$uid];
	}

	/**
	 * Get service user homedir setting for project.
	 *
	 * This is stored as an 'info' setting in ldap:
	 *
	 * info: homedirpattern=<pattern>
	 *
	 * @return string
	 */
	function getServiceGroupHomedirPattern() {
		global $wgOpenStackManagerServiceGroupHomedirPattern;
		$pattern = $wgOpenStackManagerServiceGroupHomedirPattern;

		if ( isset( $this->projectInfo[0]['info'] ) ) {
			$infos = $this->projectInfo[0]['info'];

			// first member is a count.
			array_shift( $infos );
			foreach ( $infos as $info ) {
				$substrings=explode( '=', $info );
				if ( ( count( $substrings ) == 2 ) and ( $substrings[0] == 'servicegrouphomedirpattern' ) ) {
					$pattern = $substrings[1];
					break;
				}
			}
		}
		return $pattern;
	}

	/**
	 * Returns an array of all member DNs that belong to this project.
	 *
	 * @return array
	 */
	function getMemberDNs() {
		global $wgLDAPUserBaseDNs;
		$membernames = $this->getMembers();
		$memberDNs = array();
		$dnstring = implode( ",", $wgLDAPUserBaseDNs );
		foreach ( $membernames as $member ) {
			$memberDNs[] = "uid=$member,$dnstring";
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
	 * Inform role objects that membership has changed and they
	 *  need to refresh their caches.
	 *
	 * @param  $user OpenStackNovaUser
	 */
	function deleteRoleCaches( $user ) {
		foreach ( $this->getRoles() as $role ) {
			$role->deleteMemcKeys( $user );
		}
	}

	/**
	 * Remove a member from the project based on username
	 *
	 * @param  $username string
	 * @return bool
	 */
	function deleteMember( $username ) {
		global $wgAuth;
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'projectuidsandmembers', $this->projectname );
		$wgMemc->delete( $key );

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
				$this->deleteRoleCaches( $user );
				$this->editArticle();
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
	 * Add a service group to this project
	 *
	 * @param $groupname string
	 * @return bool
	 */
	function addServiceGroup( $groupName, $initialUser ) {
		global $wgAuth;

		$group = OpenStackNovaServiceGroup::createServiceGroup( $groupName, $this, $initialUser );
		if ( ! $group ) {
			$wgAuth->printDebug( "Failed to create service group $groupName", NONSENSITIVE );
			return false;
		}

		$this->fetchServiceGroups();
		return true;
	}

	/**
	 * Remove a service group from the project
	 *
	 * @param  $groupName string
	 * @return bool
	 */
	function deleteServiceGroup( $groupName ) {
		global $wgAuth;

		$success = OpenStackNovaServiceGroup::deleteServiceGroup( $groupName, $this );

		$this->fetchServiceGroups();
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
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'projectuidsandmembers', $this->projectname );
		$wgMemc->delete( $key );

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
			$this->deleteRoleCaches( $user );
			$this->editArticle();
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

		return $retval;
	}

	/**
	 * Return a project by its project name. Returns null if the project does not exist.
	 *  This function is terrible and should be used sparingly
	 *
	 * @static
	 * @param  $projectname
	 * @return null|OpenStackNovaProject
	 */
	static function getProjectByName( $projectname ) {
		$projects = self::getAllProjects();
		foreach ( $projects as $project ) {
			if ( $project->getProjectName() == $projectname ) {
				return $project;
			}
		}
		return null;
	}

	/**
	 * Return a project by its project id. Returns null if the project does not exist.
	 *
	 * @static
	 * @param  $projectname
	 * @return null|OpenStackNovaProject
	 */
	static function getProjectById( $projectid ) {
		if ( isset( self::$projectCache[ $projectid ] ) ) {
			return self::$projectCache[ $projectid ];
		}
		$project = new OpenStackNovaProject( $projectid );
		if ( $project->projectInfo ) {
			if ( count( self::$projectCache ) >= self::$projectCacheMaxSize ) {
				array_shift( self::$projectCache );
			}
			self::$projectCache[ $projectid ] = $project;
			return $project;
		} else {
			return null;
		}
	}

	static function getController() {
		# Because of weird issues in the Keystone auth model, we can't
		#  really modify project info as the current user.  For now
		#  we're doing this with a global all-powerful account,
		#  and relying on the GUI code to ensure that we're allowed :(
		#
		# In particular, keystone doesn't have any user roll which
		#  allows editing membership of some projects but not others.
		global $wgOpenStackManagerLDAPUsername;
		$userLDAP = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
		return OpenStackNovaController::newFromUser( $userLDAP );
	}

	static function getProjectsByName( $projectnames ) {
		$projects = array();
		foreach ( $projectnames as $projectname ) {
			$project = self::getProjectByName( $projectname );
			if ( $project ) {
				$projects[] = $project;
			}
		}
		return $projects;
	}

	static function getProjectsById( $projectids ) {
		$projects = array();
		foreach ( $projectids as $projectid ) {
			$project = self::getProjectById( $projectid );
			if ( $project ) {
				$projects[] = $project;
			}
		}
		return $projects;
	}

	/**
	 * Get all project names
	 *
	 * @return string[]
	 */
	static function getAllProjectNames() {
		$projects = self::getAllProjects();
		$names = array();
		foreach ( $projects as $project ) {
			$names[] = $project->getName();
		}

		return $names;
	}

	/**
	 * Get the list of projects from Keystone.
	 *
	 * @static
	 * @return array of projectid => projectname
	 */
	static function getProjectList() {
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'projectlist' );
		$projectList = $wgMemc->get( $key );
		if ( is_array( $projectList ) ) {
			return $projectList;
		}

		$controller = OpenstackNovaProject::getController();
		$projectList = $controller->getProjects();
		$wgMemc->set( $key, $projectList, '3600' );

		return $projectList;
	}

	/**
	 * Return all existing projects. Returns an empty array if no projects exist. This function
	 * lazy loads the projects. Objects will be returned unloaded. If you wish to receive more
	 * than just the project's name, you'll need to call the project's fetchProjectInfo() function.
	 *
	 * @static
	 * @return OpenStackNovaProject[]
	 */
	static function getAllProjects() {
		global $wgAuth;

		$projects = array();
		foreach( OpenStackNovaProject::getProjectList() as $id => $name ) {
			$project = new OpenStackNovaProject( $id, false );
			$project->setName( $name );
			$projects[] = $project;
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
	 * @return OpenStackNovaProject
	 */
	static function createProject( $projectname ) {
		global $wgAuth, $wgMemc;
		global $wgOpenStackManagerLDAPUser;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		OpenStackNovaLdapConnection::connect();

		$project = array();
		$project['objectclass'][] = 'extensibleobject';
		$project['objectclass'][] = 'groupofnames';
		$project['cn'] = $projectname;
		$project['member'] = $wgOpenStackManagerLDAPUser;
		$projectdn = 'cn=' . $projectname . ',' . $wgOpenStackManagerLDAPProjectBaseDN;

		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $projectdn, $project );
		$project = new OpenStackNovaProject( $projectname );
		if ( $success ) {
			foreach ( self::$visiblerolenames as $rolename ) {
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
			OpenStackNovaProjectGroup::createProjectGroup( $projectname );
			# TODO: If project group creation fails we need to be able to fail gracefully

			// Create two default, permissive sudo policies.  First,
                        //  allow sudo (as root) for all members...
			$projectGroup = "%" . $project->getProjectGroup()->getProjectGroupName();
			if ( OpenStackNovaSudoer::createSudoer( 'default-sudo', $projectname, array( $projectGroup ),
						array(),  array( 'ALL' ),
						array( '!authenticate' ) ) ) {
				$wgAuth->printDebug( "Successfully created default sudo policy for $projectname", NONSENSITIVE );
			}
			// Now, allow all project members to sudo to all other users.
			$projectGroup = "%" . $project->getProjectGroup()->getProjectGroupName();
			if ( OpenStackNovaSudoer::createSudoer( 'default-sudo-as', $projectname, array( $projectGroup ),
						array( "$projectGroup" ),  array( 'ALL' ),
						array( '!authenticate' ) ) ) {
				$wgAuth->printDebug( "Successfully created default sudo-as policy for $projectname", NONSENSITIVE );
			}

			$wgMemc->delete( wfMemcKey( 'openstackmanager', 'projectlist' ) );
		} else {
			$wgAuth->printDebug( "Failed to add project $projectname", NONSENSITIVE );
			return null;
		}

		OpenStackNovaProject::createServiceGroupOUs( $projectname );

		return $project;
	}

	/**
	 * Add the top-level entry for Service Groups to this project.
	 * This is in a separate function so we can call it for old entries
	 * for reverse-compatibility
	 *
	 * @param  $projectname String
	 * @return bool
	 */
	static function createServiceGroupOUs( $projectname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		// Create ou for service groups
		$groups = array();
		$groups['objectclass'][] = 'organizationalunit';
		$groups['ou'] = 'groups';
		$groupsdn = 'ou=' . $groups['ou'] . ',' . 'cn=' . $projectname . ',' . $wgOpenStackManagerLDAPProjectBaseDN;

		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $groupsdn, $groups );
		if ( !$success ) {
			$wgAuth->printDebug( "Failed to create service group ou for  project $projectname", NONSENSITIVE );
			return false;
		}

		// Create ou for service users
		$users = array();
		$users['objectclass'][] = 'organizationalunit';
		$users['ou'] = 'people';
		$usersdn = 'ou=' . $users['ou'] . ',' . 'cn=' . $projectname . ',' . $wgOpenStackManagerLDAPProjectBaseDN;

		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $usersdn, $users );
		if ( !$success ) {
			$wgAuth->printDebug( "Failed to create service user ou for project $projectname", NONSENSITIVE );
			return false;
		}

		return true;
	}


	/**
	 * Deletes a project based on project id. This function will also delete all roles
	 * associated with the project.
	 *
	 * @param  $projectid String
	 * @return bool
	 */
	static function deleteProject( $projectid ) {
		global $wgAuth, $wgMemc;

		OpenStackNovaLdapConnection::connect();

		$project = new OpenStackNovaProject( $projectid );
		$projectname = $project->getName();
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

		OpenStackNovaProjectGroup::deleteProjectGroup( $projectname );

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
		# And, we need to clean up service groups.
		$servicegroups = $project->getServiceGroups();
		foreach ( $servicegroups as $group ) {
			$groupName = $group->groupName;
			$success = OpenStackNovaServiceGroup::deleteServiceGroup( $groupName, $project );
			if ( $success ){
				$wgAuth->printDebug( "Successfully deleted service group " . $groupName, NONSENSITIVE );
			} else {
				$wgAuth->printDebug( "Failed to delete servie group " . $groupName, NONSENSITIVE );
			}
		}
		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $dn );
		$wgMemc->delete( wfMemcKey( 'openstackmanager', 'projectlist' ) );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted project $projectname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete project $projectname", NONSENSITIVE );
			return false;
		}
	}

	function editArticle() {
		global $wgOpenStackManagerCreateProjectSALPages, $wgOpenStackManagerProjectNamespace,
			$wgOpenStackManagerBastionProjectName;

		if ( ! OpenStackNovaArticle::canCreatePages() ) {
			return;
		}

		$format = <<<RESOURCEINFO
{{Nova Resource
|Resource Type=project
|Project Name=%s
|Admins=%s
|Members=%s}}
__NOEDITSECTION__
RESOURCEINFO;
		$rawmembers = $this->getMembers();
		$members = array();
		// FIXME! This was too slow on the bastion project, which users get added to automatically.
		// See https://phabricator.wikimedia.org/T114229 for details.
		if ( $this->getProjectName() !== $wgOpenStackManagerBastionProjectName ) {
			foreach ( $rawmembers as $member ) {
				$members[] = 'User:' . $member;
			}
		}
		$admins = array();
		# All roles have elevated privileges, count them all as admins
		foreach ( $this->getRoles() as $role ) {
			$rawadmins = $role->getMembers();
			foreach ( $rawadmins as $admin ) {
				$admins[] = 'User:' . $admin;
			}
		}
		$text = sprintf( $format,
			$this->getProjectName(),
			implode( ',', $admins ),
			implode( ',', $members )
		);
		OpenStackNovaArticle::editArticle( $this->getProjectName(), $text, $wgOpenStackManagerProjectNamespace );
		if ( $wgOpenStackManagerCreateProjectSALPages ) {
			$pagename = $this->getProjectName() . "/SAL";
			$id = Title::newFromText( $pagename, $wgOpenStackManagerProjectNamespace )->getArticleId();
			$article = Article::newFromId( $id );
			$content = '';
			if ( $article ) {
				$content = $article->getContent( Revision::RAW );
			}
			$text = "{{SAL|Project Name=" . $this->getProjectName() . "}}";
			if ( !strstr( $content, $text ) ) {
				OpenStackNovaArticle::editArticle( $pagename, $text, $wgOpenStackManagerProjectNamespace );
			}
		}
	}

	function deleteArticle() {
		global $wgOpenStackManagerProjectNamespace;
		OpenStackNovaArticle::deleteArticle( $this->getProjectName(), $wgOpenStackManagerProjectNamespace );
	}
}

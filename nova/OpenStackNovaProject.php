<?php

/**
 * Class to manage Projects, project roles, service groups.
 *
 *  For historical reasons this class is kind of a mess, mixing
 *   ldap with keystone-managed resources.
 *
 *  Projects:  Live in keystone, have ids and names
 *  Users: Stored in ldap, managed elsewhere
 *  Project members:  Stored via keystone roles that manage user/project/role records.
 *                    We have a role called 'user' that grants no OpenStack rights but is
 *                    used to keep track of which users should have login access to project
 *                    instances.
 *  Sudoers:   Live in ldap, in a domain named after the project name (not the id)
 *  Service groups:  Live entirely in ldap in domains named with the project name
 *
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaProject {
	public $projectname;
	public $projectDN;
	public $roles;
	public $userrole;
	public $loaded;
	protected $serviceGroups;
	protected $serviceUsers;

	// list of roles that are visible in the web UI
	private static $visiblerolenames = [ 'projectadmin' ];

	// this is a stealth role that implies project membership
	// but no ability to manipulate the project or instances
	private static $userrolename = 'user';

	// short-lived cache of project objects
	private static $projectCache = [];
	private static $projectCacheMaxSize = 200;
	private static $projectGroupPrefix = 'project-';

	/**
	 * @param  $projectname
	 * @param bool $load
	 */
	function __construct( $projectid, $load = true ) {
		$this->projectid = $projectid;
		$this->projectname = "";
		if ( $load ) {
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
		global $wgOpenStackManagerLDAPProjectBaseDN;
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'projectname', $this->projectid );
		$this->projectname = $wgMemc->get( $key );
		if ( !$this->projectname ) {
			$controller = OpenstackNovaProject::getController();
			$this->projectname = $controller->getProjectName( $this->projectid );

			# Projectname doesn't ever change once a project is created, so
			# we can cache this a good long time.
			$wgMemc->set( $key, $this->projectname );
		}

		# We still keep things like sudoers in ldap, so we need a unique dn for this
		#  project to keep things under.
		$this->projectDN = 'cn=' . $this->projectname . ',' . $wgOpenStackManagerLDAPProjectBaseDN;
	}

	/**
	 * Fetch the project from keystone initialize the object
	 */
	function fetchProjectInfo( $refresh = true ) {
		if ( $this->loaded && !$refresh ) {
			return;
		}

		if ( !$this->projectname || $refresh ) {
			$this->loadProjectName();
		}

		$this->roles = [];
		foreach ( self::$visiblerolenames as $rolename ) {
			$this->roles[] = OpenStackNovaRole::getProjectRoleByName( $rolename, $this );
		}
		$this->userrole = OpenStackNovaRole::getProjectRoleByName( self::$userrolename, $this );

		$this->fetchServiceGroups();

		$this->loaded = true;
	}

	function fetchServiceGroups() {
		global $wgOpenStackManagerLDAPServiceGroupBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$result = LdapAuthenticationPlugin::ldap_search(
			$ldap->ldapconn,
			$wgOpenStackManagerLDAPServiceGroupBaseDN,
			'(objectclass=groupofnames)'
		);

		$this->serviceGroups = [];
		if ( $result ) {
			$groupList = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
			if ( isset( $groupList ) ) {
				array_shift( $groupList );
				foreach ( $groupList as $groupEntry ) {
					# Now we have every group.  Check if this one belongs to us.
					$matchstring = $this->projectname . ".";
					if ( strpos( $groupEntry['cn'][0], $matchstring ) === 0 ) {
						$this->serviceGroups[] = new OpenStackNovaServiceGroup(
							$groupEntry['cn'][0], $this
						);
					}
				}
			}
		}

		$serviceUserBaseDN = "ou=people" . "," . $wgOpenStackManagerLDAPServiceGroupBaseDN;
		$result = LdapAuthenticationPlugin::ldap_search(
			$ldap->ldapconn,
			$serviceUserBaseDN,
			'(objectclass=person)'
		);

		$this->serviceUsers = [];
		if ( $result ) {
			$userList = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
			if ( isset( $userList ) ) {
				array_shift( $userList );
				foreach ( $userList as $userEntry ) {
					# Now we have every user.  Check if this one belongs to us.
					$matchstring = $this->projectname . ".";
					if ( strpos( $userEntry['cn'][0], $matchstring ) === 0 ) {
						$ldap->printDebug( "adding " . $userEntry['cn'][0], NONSENSITIVE );
						$this->serviceUsers[] = $userEntry['cn'][0];
					}
				}
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
	 * Return all roles for this project
	 * @return array
	 */
	function getRoles() {
		$this->fetchProjectInfo();
		return $this->roles;
	}

	/**
	 * Return all service groups for this project
	 * @return array
	 */
	function getServiceGroups() {
		$this->fetchProjectInfo();
		return $this->serviceGroups;
	}

	/**
	 * Return all service users for this project
	 * @return array
	 */
	function getServiceUsers() {
		$this->fetchProjectInfo();
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

	function uidForMember( $username ) {
		$this->loadMembers();
		foreach ( $this->members as $id => $name ) {
			if ( $username == $name ) {
				return $id;
			}
		}
		return "";
	}

	/**
	 * Returns an array of all member DNs that belong to this project.
	 *
	 * @return array
	 */
	function getMemberDNs() {
		global $wgLDAPUserBaseDNs;
		$memberids = $this->getMemberIDs();
		$memberDNs = [];
		$dnstring = implode( ",", $wgLDAPUserBaseDNs );
		foreach ( $memberids as $member ) {
			$memberDNs[] = "uid=$member,$dnstring";
		}
		return $memberDNs;
	}

	function getProjectDN() {
		if ( !$this->projectDN ) {
			$this->loadProjectName();
		}
		return $this->projectDN;
	}

	function getSudoersDN() {
		return 'ou=sudoers,' . $this->getProjectDN();
	}

	/**
	 * Inform role objects that membership has changed and they
	 *  need to refresh their caches.
	 *
	 * @param  $user OpenStackNovaUser
	 */
	function deleteRoleCaches( $username ) {
		$user = new OpenStackNovaUser( $username );
		if ( $this->roles ) {
			foreach ( $this->roles as $role ) {
				$role->deleteMemcKeys( $user );
			}
		}
		$this->userrole->deleteMemcKeys( $user );
	}

	/**
	 * Remove a member from the project based on username
	 *
	 * @param  $username string
	 * @return bool
	 */
	function deleteMember( $username ) {
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$key = wfMemcKey( 'openstackmanager', 'projectuidsandmembers', $this->projectname );
		$wgMemc->delete( $key );

		if ( $this->userrole->deleteMember( $username ) ) {
			foreach ( $this->roles as $role ) {
				$role->deleteMember( $username );
				# @todo Find a way to fail gracefully if role member
				# deletion fails
			}
			$sudoers = OpenStackNovaSudoer::getAllSudoersByProject( $this->getProjectName() );
			foreach ( $sudoers as $sudoer ) {
				$success = $sudoer->deleteUser( $username );
				if ( $success ) {
					$ldap->printDebug(
						"Successfully removed $username from " . $sudoer->getSudoerName(),
						NONSENSITIVE
					);
				} else {
					$ldap->printDebug(
						"Failed to remove $username from " . $sudoer->getSudoerName(), NONSENSITIVE
					);
				}
			}
			$user = new OpenStackNovaUser( $username );
			$ldap->printDebug(
				"Successfully removed $user->userDN from $this->projectname", NONSENSITIVE
			);
			$this->deleteRoleCaches( $username );
			return true;
		} else {
			$ldap->printDebug( "Failed to remove $username from $this->projectname: " .
				ldap_error( $ldap->ldapconn ), NONSENSITIVE );
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
		$group = OpenStackNovaServiceGroup::createServiceGroup( $groupName, $this, $initialUser );
		if ( !$group ) {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$ldap->printDebug( "Failed to create service group $groupName", NONSENSITIVE );
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
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$key = wfMemcKey( 'openstackmanager', 'projectuidsandmembers', $this->projectname );
		$wgMemc->delete( $key );

		if ( !$this->userrole ) {
			$this->userrole = OpenStackNovaRole::getProjectRoleByName( self::$userrolename, $this );
		}

		if ( $this->userrole->addMember( $username ) ) {
			$this->deleteRoleCaches( $username );
			$ldap->printDebug( "Successfully added $username to $this->projectname", NONSENSITIVE );
			return true;
		} else {
			$ldap->printDebug( "Failed to add $username to $this->projectname", NONSENSITIVE );
			return false;
		}
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
		if ( $project ) {
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
		# In particular, keystone doesn't have any user roll which
		#  allows editing membership of some projects but not others.
		global $wgOpenStackManagerLDAPUsername;
		$userLDAP = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
		return OpenStackNovaController::newFromUser( $userLDAP );
	}

	static function getProjectsByName( $projectnames ) {
		$projects = [];
		foreach ( $projectnames as $projectname ) {
			$project = self::getProjectByName( $projectname );
			if ( $project ) {
				$projects[] = $project;
			}
		}
		return $projects;
	}

	static function getProjectsById( $projectids ) {
		$projects = [];
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
		$names = [];
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
		$projects = [];
		foreach ( OpenStackNovaProject::getProjectList() as $id => $name ) {
			$project = new OpenStackNovaProject( $id, false );
			$project->setName( $name );
			$projects[] = $project;
		}

		sort( $projects );
		return $projects;
	}

	/**
	 * Returns a standardized project group name.  This needs to
	 * correspond with the project group name as set in the keystone hook.
	 *
	 * @return string
	 */
	function getProjectGroupName() {
		return self::$projectGroupPrefix . $this->projectname;
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
		global $wgMemc;
		global $wgOpenStackManagerLDAPUser;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$controller = OpenstackNovaProject::getController();
		$newProjectId = $controller->createProject( $projectname );
		$wgMemc->delete( wfMemcKey( 'openstackmanager', 'projectlist' ) );

		if ( $newProjectId ) {
			# We need to create the Ldap project as well, so it's there to contain sudoers &c.
			OpenStackNovaLdapConnection::connect();
			$ldapproject = [];
			$ldapproject['objectclass'][] = 'extensibleobject';
			$ldapproject['objectclass'][] = 'groupofnames';
			$ldapproject['cn'] = $projectname;
			$ldapproject['member'] = $wgOpenStackManagerLDAPUser;
			$projectdn = 'cn=' . $projectname . ',' . $wgOpenStackManagerLDAPProjectBaseDN;

			$success = LdapAuthenticationPlugin::ldap_add(
				$ldap->ldapconn, $projectdn, $ldapproject
			);
			if ( !$success ) {
				$ldap->printDebug(
					"Creation of ldap project container failed for $projectname", NONSENSITIVE
				);
			}

			$ldap->printDebug( "Added ldap project container $projectname", NONSENSITIVE );
			$project = new OpenstackNovaProject( $newProjectId, false );
			$projectdn = $project->getProjectDN();

			$sudoerOU = [];
			$sudoerOU['objectclass'][] = 'organizationalunit';
			$sudoerOU['ou'] = 'sudooers';
			$sudoerOUdn = 'ou=sudoers,' . $projectdn;
			LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $sudoerOUdn, $sudoerOU );
			# TODO: If sudoerOU creation fails we need to be able to fail gracefully

			// Create two default, permissive sudo policies.  First,
			// allow sudo (as root) for all members...
			$projectGroup = "%" . $project->getProjectGroupName();
			if ( OpenStackNovaSudoer::createSudoer( 'default-sudo', $projectname, [ $projectGroup ],
						[],  [ 'ALL' ],
						[ '!authenticate' ] ) ) {
				$ldap->printDebug(
					"Successfully created default sudo policy for $projectname", NONSENSITIVE
				);
			}
			// Now, allow all project members to sudo to all other users.
			$projectGroup = "%" . $project->getProjectGroupName();
			if (
				OpenStackNovaSudoer::createSudoer(
					'default-sudo-as', $projectname, [ $projectGroup ],
					[ "$projectGroup" ], [ 'ALL' ], [ '!authenticate' ]
				)
			) {
				$ldap->printDebug(
					"Successfully created default sudo-as policy for $projectname", NONSENSITIVE
				);
			}
			OpenStackNovaProject::createServiceGroupOUs( $projectname );
		} else {
			$ldap->printDebug( "Failed to add project $projectname", NONSENSITIVE );
			return null;
		}

		$project->fetchProjectInfo();
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
		global $wgOpenStackManagerLDAPProjectBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();

		// Create ou for service groups
		$groups = [];
		$groups['objectclass'][] = 'organizationalunit';
		$groups['ou'] = 'groups';
		$groupsdn = 'ou=' . $groups['ou'] . ',' . 'cn=' . $projectname . ',' .
			$wgOpenStackManagerLDAPProjectBaseDN;

		$success = LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $groupsdn, $groups );
		if ( !$success ) {
			$ldap->printDebug(
				"Failed to create service group ou for  project $projectname", NONSENSITIVE
			);
			return false;
		}

		// Create ou for service users
		$users = [];
		$users['objectclass'][] = 'organizationalunit';
		$users['ou'] = 'people';
		$usersdn = 'ou=' . $users['ou'] . ',' . 'cn=' . $projectname . ',' .
			$wgOpenStackManagerLDAPProjectBaseDN;

		$success = LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $usersdn, $users );
		if ( !$success ) {
			$ldap->printDebug(
				"Failed to create service user ou for project $projectname", NONSENSITIVE
			);
			return false;
		}

		return true;
	}

	/**
	 * Remove the top-level entry for Service Groups to this project.
	 *
	 * @param  $projectname String
	 * @return bool
	 */
	function deleteServiceGroupOUs() {
		global $wgOpenStackManagerLDAPProjectBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();

		$groups = [];
		$groups['objectclass'][] = 'organizationalunit';
		$groups['ou'] = 'groups';
		$groupsdn = 'ou=' . $groups['ou'] . ',' . 'cn=' . $this->projectname . ',' .
			$wgOpenStackManagerLDAPProjectBaseDN;

		$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $groupsdn );
		if ( !$success ) {
			$ldap->printDebug(
				"Failed to delete service group ou for  project $this->projectname", NONSENSITIVE
			);
			return false;
		}

		$users = [];
		$users['objectclass'][] = 'organizationalunit';
		$users['ou'] = 'people';
		$usersdn = 'ou=' . $users['ou'] . ',' . 'cn=' . $this->projectname . ',' .
			$wgOpenStackManagerLDAPProjectBaseDN;

		$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $usersdn );
		if ( !$success ) {
			$ldap->printDebug(
				"Failed to delete service user ou for project $this->projectname", NONSENSITIVE
			);
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
		global $wgMemc;

		$project = new OpenStackNovaProject( $projectid );
		if ( !$project ) {
			return false;
		}
		$projectname = $project->getName();

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		# Projects have a sudo OU and sudoers entries below that OU, we must delete them first
		$sudoers = OpenStackNovaSudoer::getAllSudoersByProject( $project->getProjectName() );
		foreach ( $sudoers as $sudoer ) {
			$success = OpenStackNovaSudoer::deleteSudoer(
				$sudoer->getSudoerName(), $project->getProjectName()
			);
			if ( $success ) {
				$ldap->printDebug(
					"Successfully deleted sudoer " . $sudoer->getSudoerName(), NONSENSITIVE
				);
			} else {
				$ldap->printDebug(
					"Failed to delete sudoer " . $sudoer->getSudoerName(), NONSENSITIVE
				);
			}
		}
		$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $project->getSudoersDN() );
		if ( $success ) {
			$ldap->printDebug(
				"Successfully deleted sudoers OU " . $project->getSudoersDN(), NONSENSITIVE
			);
		} else {
			$ldap->printDebug(
				"Failed to delete sudoers OU " . $project->getSudoersDN(), NONSENSITIVE
			);
		}
		# And, we need to clean up service groups.
		$servicegroups = $project->getServiceGroups();
		foreach ( $servicegroups as $group ) {
			$groupName = $group->groupName;
			$success = OpenStackNovaServiceGroup::deleteServiceGroup( $groupName, $project );
			if ( $success ) {
				$ldap->printDebug(
					"Successfully deleted service group " . $groupName, NONSENSITIVE
				);
			} else {
				$ldap->printDebug( "Failed to delete service group " . $groupName, NONSENSITIVE );
			}
		}
		$project->deleteServiceGroupOUs();

		$dn = $project->projectDN;
		$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $dn );
		if ( !$success ) {
			$ldap->printDebug( "Failed to delete project LDAP container $dn", NONSENSITIVE );
		}

		$controller = OpenstackNovaProject::getController();
		$success = $controller->deleteProject( $projectid );
		$wgMemc->delete( wfMemcKey( 'openstackmanager', 'projectlist' ) );

		if ( $success ) {
			$ldap->printDebug( "Successfully deleted project", NONSENSITIVE );
			return true;
		} else {
			$ldap->printDebug( "Failed to delete project", NONSENSITIVE );
			return false;
		}
	}

	public function editArticle() {
		global $wgOpenStackManagerCreateProjectSALPages, $wgOpenStackManagerProjectNamespace,
			$wgOpenStackManagerBastionProjectName;

		if ( !OpenStackNovaArticle::canCreatePages() ) {
			return;
		}

		$format = <<<RESOURCEINFO
{{Nova Resource
|Resource Type=project
|Project Name=%s}}
__NOEDITSECTION__
RESOURCEINFO;
		$text = sprintf( $format,
			$this->getProjectName()
		);
		OpenStackNovaArticle::editArticle(
			$this->getProjectName(), $text, $wgOpenStackManagerProjectNamespace
		);
		if ( $wgOpenStackManagerCreateProjectSALPages ) {
			$pagename = $this->getProjectName() . "/SAL";
			$title = Title::newFromText( $pagename, $wgOpenStackManagerProjectNamespace );
			$page = WikiPage::factory( $title );
			$content = '';
			if ( $page ) {
				$content = ContentHandler::getContentText( $page->getContent( Revision::RAW ) );
			}
			$text = "{{SAL|Project Name=" . $this->getProjectName() . "}}";
			if ( !strstr( $content, $text ) ) {
				OpenStackNovaArticle::editArticle(
					$pagename, $text, $wgOpenStackManagerProjectNamespace
				);
			}
		}
	}

	function deleteArticle() {
		global $wgOpenStackManagerProjectNamespace;
		OpenStackNovaArticle::deleteArticle(
			$this->getProjectName(), $wgOpenStackManagerProjectNamespace
		);
	}

	/**
	 * Get service user homedir setting for project.
	 *
	 * This is stored as an 'info' setting in ldap:
	 *
	 *  Note:  This setting is obsolete and can no longer be changed.  It's preserved as legacy
	 *          for a small number of projects who rely on it being set.
	 *
	 * info: homedirpattern=<pattern>
	 *
	 * @return string
	 */
	function getServiceGroupHomedirPattern() {
		global $wgOpenStackManagerServiceGroupHomedirPattern;
		global $wgOpenStackManagerLDAPProjectBaseDN;
		$pattern = $wgOpenStackManagerServiceGroupHomedirPattern;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$result = LdapAuthenticationPlugin::ldap_search(
			$ldap->ldapconn,
			$wgOpenStackManagerLDAPProjectBaseDN,
			'(&(cn=' . $this->getProjectName() . ')(objectclass=groupofnames))'
		);
		$projectInfo = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );

		if ( isset( $projectInfo[0]['info'] ) ) {
			$infos = $projectInfo[0]['info'];

			// first member is a count.
			array_shift( $infos );
			foreach ( $infos as $info ) {
				$substrings = explode( '=', $info );
				if ( count( $substrings ) == 2 && $substrings[0] == 'servicegrouphomedirpattern' ) {
					$pattern = $substrings[1];
					break;
				}
			}
		}
		return $pattern;
	}
}

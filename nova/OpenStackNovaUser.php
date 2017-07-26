<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaUser {

	public $username;
	public $userDN;
	public $userInfo;

	/**
	 * @param string $username
	 */
	function __construct( $username = '' ) {
		$this->username = $username;
		OpenStackNovaLdapConnection::connect();
		$this->fetchUserInfo();
	}

	/**
	 * @return void
	 */
	function fetchUserInfo() {
		global $wgUser;

		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( $this->username ) {
			$this->userDN = $ldap->getUserDN( strtolower( $this->username ) );
			$ldap->printDebug( "Fetching userdn using username: $this->userDN ", NONSENSITIVE );
			if ( !$this->userDN ) {
				$this->userDN = $ldap->getUserDN( strtolower( $this->username ), false, "uid" );
				$ldap->printDebug(
					"Fetching userdn using shell name: $this->userDN ", NONSENSITIVE
				);

				# We want the actual username, not the id that was passed in.
				$this->userInfo = $ldap->userInfo;
				$this->username = $this->userInfo[0]['cn'][0];
			}
		} else {
			$this->userDN = $ldap->getUserDN( strtolower( $wgUser->getName() ) );
			$this->username = $wgUser->getName();
			$ldap->printDebug(
				"Fetching userdn using wiki name: " . $wgUser->getName(), NONSENSITIVE
			);
		}
		$this->userInfo = $ldap->userInfo;
	}

	/**
	 * @return string
	 */
	function getUid() {
		return $this->userInfo[0]['uid'][0];
	}

	/**
	 * @return string
	 */
	function getUsername() {
		return $this->username;
	}

	/**
	 * @param string $project
	 * @return array
	 */
	function getCredentials( $project = '' ) {
		$userNova = OpenStackNovaController::newFromUser( $this );
		if ( $project ) {
			$token = $userNova->getProjectToken( $project );
		} else {
			$token = $userNova->getUnscopedToken();
		}

		return [ 'token' => $token ];
	}

	/**
	 * @param User $user
	 * @return string
	 */
	static function loadToken( $user ) {
		if ( !$user ) {
			return null;
		}
		$user_id = $user->getId();
		if ( $user_id != 0 ) {
			$dbr = wfGetDB( DB_SLAVE );
			$row = $dbr->selectRow(
				'openstack_tokens',
				[ 'token' ],
				[ 'user_id' => $user_id ],
				__METHOD__ );

			if ( $row ) {
				return $row->token;
			}
		}

		return null;
	}

	/**
	 * @param User $user
	 * @param string $token
	 * @return bool
	 */
	static function saveToken( $user, $token ) {
		$user_id = $user->getId();
		if ( $user_id != 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$oldtoken = self::loadToken( $user );
			if ( $oldtoken ) {
				return $dbw->update(
					'openstack_tokens',
					[ 'token' => $token ],
					[ 'user_id' => $user_id ],
					__METHOD__ );
			} else {
				return $dbw->insert(
					'openstack_tokens',
					[ 'token' => $token,
						'user_id' => $user_id ],
					__METHOD__ );
			}
		} else {
			return false;
		}
	}

	/**
	 * @return array
	 */
	function getKeypairs() {
		$this->fetchUserInfo();
		if ( isset( $this->userInfo[0]['sshpublickey'] ) ) {
			$keys = $this->userInfo[0]['sshpublickey'];
			$keypairs = [];
			array_shift( $keys );
			foreach ( $keys as $key ) {
				$hash = md5( $key );
				$keypairs[$hash] = $key;
			}
			return $keypairs;
		} else {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$ldap->printDebug( "No keypairs found", NONSENSITIVE );
			return [];
		}
	}

	/**
	 * @return bool
	 */
	function exists() {
		$credentials = $this->getCredentials();
		return (bool)$credentials['token'];
	}

	/**
	 * @return array
	 */
	function getProjects() {
		$controller = OpenStackNovaProject::getController();
		$projects = array_keys( $controller->getRoleAssignmentsForUser( $this->getUid() ) );
		return $projects;
	}

	/**
	 * Returns a list of role this user is a member of. Includes
	 * all projects.
	 * @return array of rolenames
	 */
	function getRoles() {
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'roles', $this->username );
		$roles = $wgMemc->get( $key );
		if ( is_array( $roles ) ) {
			return $roles;
		}

		$controller = OpenStackNovaProject::getController();
		$assignments = $controller->getRoleAssignmentsForUser( $this->getUid() );

		$everyrole = [];
		foreach ( $assignments as $projectid => $rolelist ) {
			$everyrole = array_merge( $everyrole, $rolelist );
		}
		$roleids = array_unique( $everyrole );

		$roles = [];
		foreach ( $roleids as $roleid ) {
			$roles[] = OpenStackNovaRole::getRoleNameForId( $roleid );
		}

		$wgMemc->set( $key, $roles, '3600' );
		return $roles;
	}

	/**
	 * @param  $project
	 * @return bool
	 */
	function inProject( $project ) {
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', "project-$project", $this->userDN );
		$cacheLength = 3600;
		$inProject = $wgMemc->get( $key );
		if ( is_int( $inProject ) ) {
			return (bool)$inProject;
		}

		$ret = in_array( $project, $this->getProjects() );

		$wgMemc->set( $key, (int)$ret, $cacheLength );
		return $ret;
	}

	/**
	 * @param $role
	 * @param string $projectname
	 * @return bool
	 */
	function inRole( $role, $projectname ) {
		global $wgMemc;

		if ( !$projectname ) {
			return false;
		}
		$key = wfMemcKey( 'openstackmanager', "projectrole-$projectname-$role", $this->userDN );
		$inRole = $wgMemc->get( $key );
		if ( is_int( $inRole ) ) {
			return (bool)$inRole;
		}

		$project = OpenStackNovaProject::getProjectByName( $projectname );
		if ( !$project ) {
			return false;
		}
		$role = OpenStackNovaRole::getProjectRoleByName( $role, $project );
		if ( !$role ) {
			return false;
		}

		$ret = false;
		if ( in_array( $this->getUsername(), $role->getMembers() ) ) {
			$ret = true;
		}
		// Invalidating this properly is hard, so cache just long enough for a single action
		$wgMemc->set( $key, (int)$ret, 30 );

		return $ret;
	}

	/**
	 * @param  $key
	 * @return bool
	 */
	function importKeypair( $key ) {
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$keypairs = [];
		if ( isset( $this->userInfo[0]['sshpublickey'] ) ) {
			$keypairs = $this->userInfo[0]['sshpublickey'];
			array_shift( $keypairs );
		}
		$keypairs[] = $key;
		$values = [];
		$values['sshpublickey'] = $keypairs;
		$success = LdapAuthenticationPlugin::ldap_modify( $ldap->ldapconn, $this->userDN, $values );
		if ( $success ) {
			$ldap->printDebug( "Successfully imported the user's sshpublickey", NONSENSITIVE );
			$key = wfMemcKey( 'ldapauthentication', "userinfo", $this->userDN );
			$ldap->printDebug( "Deleting memcache key: $key.", NONSENSITIVE );
			$wgMemc->delete( $key );
			$this->fetchUserInfo();
			return true;
		} else {
			$ldap->printDebug( "Failed to import the user's sshpublickey", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @param  $key
	 * @return bool
	 */
	function deleteKeypair( $key ) {
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( isset( $this->userInfo[0]['sshpublickey'] ) ) {
			$keypairs = $this->userInfo[0]['sshpublickey'];
			array_shift( $keypairs );
			$index = array_search( $key, $keypairs );
			if ( $index === false ) {
				$ldap->printDebug( "Unable to find the sshpublickey to be deleted", NONSENSITIVE );
				return false;
			}
			unset( $keypairs[$index] );
			$values = [];
			$values['sshpublickey'] = [];
			foreach ( $keypairs as $keypair ) {
				$values['sshpublickey'][] = $keypair;
			}
			$success = LdapAuthenticationPlugin::ldap_modify(
				$ldap->ldapconn, $this->userDN, $values
			);
			if ( $success ) {
				$ldap->printDebug( "Successfully deleted the user's sshpublickey", NONSENSITIVE );
				$key = wfMemcKey( 'ldapauthentication', "userinfo", $this->userDN );
				$ldap->printDebug( "Deleting memcache key: $key.", NONSENSITIVE );
				$wgMemc->delete( $key );
				$this->fetchUserInfo();
				return true;
			} else {
				$ldap->printDebug( "Failed to delete the user's sshpublickey", NONSENSITIVE );
				return false;
			}
		} else {
			$ldap->printDebug( "User does not have a sshpublickey attribute", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Does not ensure uniqueness during concurrent use!
	 * Also does not work when resource limits are set for
	 * LDAP queries by the client or the server.
	 *
	 * TODO: write a better and more efficient version of this.
	 *
	 * TODO: Make use of $wgOpenStackManagerIdRanges for all cases.
	 * TODO: Make $wgOpenStackManagerIdRanges use a set of ranges.
	 *
	 * @static
	 * @param  $auth
	 * @param  $attr
	 * @return mixed|string
	 */
	static function getNextIdNumber( $auth, $attr ) {
		global $wgOpenStackManagerIdRanges;

		$highest = '';
		if ( $attr === 'gidnumber' ) {
			$filter = "(objectclass=posixgroup)";
			$base = GROUPDN;
			$highest = $wgOpenStackManagerIdRanges['service']['gid']['min'];
		} else {
			$filter = "(objectclass=posixaccount)";
			$base = USERDN;
			$highest = '500';
		}
		$basedn = $auth->getBaseDN( $base );

		$result = LdapAuthenticationPlugin::ldap_search( $auth->ldapconn, $basedn, $filter );
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $auth->ldapconn, $result );
			if ( $entries ) {
				if ( $entries['count'] != "0" ) {
					array_shift( $entries );
					$uids = [];
					foreach ( $entries as $entry ) {
						$uids[] = $entry[$attr][0];
					}
					sort( $uids, SORT_NUMERIC );
					$highest = array_pop( $uids ) + 1;
				}
			} else {
				$auth->printDebug(
					"Failed to find any entries when searching for next $attr", NONSENSITIVE
				);
			}
		} else {
			$auth->printDebug( "Failed to get a result searching for next $attr", NONSENSITIVE );
		}

		if ( $highest > $wgOpenStackManagerIdRanges['service']['gid']['max'] ) {
			$auth->printDebug( "Ran out of service group gids!", NONSENSITIVE );
		}

		$auth->printDebug( "id returned: $highest", NONSENSITIVE );
		return $highest;
	}

	/**
	 * Hook to add objectclasses and attributes for users being created.
	 *
	 * @static
	 * @param  $auth
	 * @param  $username
	 * @param  $values
	 * @param  $writeloc
	 * @param  $userdn
	 * @param  $result
	 * @return bool
	 */
	static function LDAPSetCreationValues(
		$auth, $username, &$values, $writeloc, &$userdn, &$result
	) {
		global $wgOpenStackManagerLDAPDefaultGid;
		global $wgOpenStackManagerLDAPDefaultShell;
		global $wgRequest;

		$values['objectclass'][] = 'person';
		$values['objectclass'][] = 'ldappublickey';
		$values['objectclass'][] = 'posixaccount';
		$values['objectclass'][] = 'shadowaccount';
		$uidnumber = self::getNextIdNumber( $auth, 'uidnumber' );
		if ( !$uidnumber ) {
			$auth->printDebug( "Unable to allocate a UID", NONSENSITIVE );
			$result = false;
			return false;
		}
		$values['cn'] = $username;
		if ( '' !== $auth->realname ) {
			$values['displayname'] = $auth->realname;
		}
		if ( class_exists( \MediaWiki\Auth\AuthManager::class ) &&
			empty( $wgDisableAuthManager )
		) {
			$shellaccountname = \MediaWiki\Auth\AuthManager::singleton()
				->getAuthenticationSessionData( 'osm-shellaccountname', '' );
		} else {
			$shellaccountname = $wgRequest->getText( 'shellaccountname' );
		}
		if ( !preg_match( "/^[a-z][a-z0-9\-_]*$/", $shellaccountname ) ) {
			$auth->printDebug( "Invalid shell name $shellaccountname", NONSENSITIVE );
			$result = false;
			return false;
		}
		$check = ucfirst( $shellaccountname );
		if ( !User::isCreatableName( $check ) ) {
			$auth->printDebug( "$shellaccountname is not a creatable name.", NONSENSITIVE );
			$result = false;
			return false;
		}
		$values['uid'] = $shellaccountname;
		$base = $auth->getBaseDN( USERDN );
		# Though the LDAP plugin checks to see if the user account exists,
		# it does not check to see if the uid attribute is already used.
		$result = LdapAuthenticationPlugin::ldap_search(
			$auth->ldapconn, $base, "(uid=$shellaccountname)"
		);
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $auth->ldapconn, $result );
			if ( (int)$entries['count'] > 0 ) {
				$auth->printDebug( "User $shellaccountname already exists.", NONSENSITIVE );
				# uid attribute is already in use, fail.
				$result = false;
				return false;
			}
		}
		$values['uidnumber'] = $uidnumber;
		$values['gidnumber'] = $wgOpenStackManagerLDAPDefaultGid;
		$values['homedirectory'] = '/home/' . $shellaccountname;
		$values['loginshell'] = $wgOpenStackManagerLDAPDefaultShell;

		if ( $writeloc === '' ) {
			$auth->printDebug(
				"Trying to set the userdn, but write location isn't set.", NONSENSITIVE
			);
			return false;
		} else {
			$userdn = 'uid=' . $shellaccountname . ',' . $writeloc;
			$auth->printDebug( "Using uid as the naming attribute, dn is: $userdn", NONSENSITIVE );
		}
		$auth->printDebug( "User account's objectclasses: ", NONSENSITIVE, $values['objectclass'] );

		return true;
	}

	/**
	 * Hook to retry setting the creation values. Specifically, this will try to set a new
	 * uid in case there's a race condition.
	 *
	 * @static
	 * @param  $auth
	 * @param  $username
	 * @param  $values
	 * @param  $writeloc
	 * @param  $userdn
	 * @param  $result
	 * @return bool
	 */
	static function LDAPRetrySetCreationValues(
		$auth, $username, &$values, $writeloc, &$userdn, &$result
	) {
		$uidnumber = self::getNextIdNumber( $auth, 'uidnumber' );
		if ( !$uidnumber ) {
			$result = false;
			return false;
		}
		$values['uidnumber'] = $uidnumber;

		$result = true;
		return true;
	}

	/**
	 * @static
	 * @param $template
	 * @return bool
	 */
	static function LDAPModifyUITemplate( &$template ) {
		$input = [
			'msg' => 'openstackmanager-shellaccountname',
			'type' => 'text',
			'name' => 'shellaccountname',
			'value' => '',
			'helptext' => 'openstackmanager-shellaccountnamehelp'
		];
		$template->set( 'extraInput', [ $input ] );

		return true;
	}

	/**
	 * @param \MediaWiki\Auth\AuthenticationRequest[] $requests
	 * @param array $fieldInfo
	 * @param array $formDescriptor
	 * @param string $action
	 */
	static function AuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		if ( isset( $formDescriptor['shellaccountname'] ) ) {
			$formDescriptor['shellaccountname'] += [
				'help-message' => 'openstackmanager-shellaccountnamehelp',
				'weight' => 90,
			];
		};
	}

	static function AbortNewAccount( $user, &$message ) {
		global $wgRequest;
		global $wgUser;
		global $wgDisableAuthManager;

		if ( class_exists( \MediaWiki\Auth\AuthManager::class ) &&
			empty( $wgDisableAuthManager )
		) {
			// handled in OpenStackNovaSecondaryAuthenticationProvider
			return true;
		}

		$ldap = LdapAuthenticationPlugin::getInstance();
		$shellaccountname = $wgRequest->getText( 'shellaccountname' );
		if ( !preg_match( "/^[a-z][a-z0-9\-_]*$/", $shellaccountname ) ) {
			$ldap->printDebug( "Invalid shell name $shellaccountname", NONSENSITIVE );
			$message = wfMessage( 'openstackmanager-shellaccountvalidationfail' )->parse();
			return false;
		}

		$base = USERDN;
		$result = LdapAuthenticationPlugin::ldap_search(
			$ldap->ldapconn, $base, "(uid=$shellaccountname)"
		);
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
			if ( (int)$entries['count'] > 0 ) {
				$ldap->printDebug( "User $shellaccountname already exists.", NONSENSITIVE );
				$message = wfMessage( 'openstackmanager-shellaccountexists' )->parse();
				return false;
			}
		}

		if ( class_exists( 'TitleBlacklist' ) ) {
			return TitleBlacklistHooks::acceptNewUserName(
				$shellaccountname, $wgUser, $message, $override = false, $log = true
			);
		} else {
			return true;
		}
	}

	/**
	 * @static
	 * @param $user
	 * @return bool
	 */
	static function LDAPUpdateUser( &$wikiUser ) {
		if ( $wikiUser->getToken( false ) && isset( $_SESSION['wsOpenStackToken'] ) ) {
			# If the user has a long-lived token, save the token,
			# so that it can be refetched.
			self::saveToken( $wikiUser, $_SESSION['wsOpenStackToken'] );
		}
		return true;
	}

	/**
	 * @param $username string
	 * @param $password string
	 * @param $result bool
	 * @return bool
	 */
	static function ChainAuth( $username, $password, &$result ) {
		$user = new OpenStackNovaUser( $username );
		$userNova = OpenStackNovaController::newFromUser( $user );
		$token = $userNova->authenticate( $username, $password );
		if ( $token ) {
			$result = true;
			# Add token to session, so that it can be referenced later
			$_SESSION['wsOpenStackToken'] = $token;
		} else {
			$result = false;
		}

		return $result;
	}

	static function DynamicSidebarGetGroups( &$groups ) {
		global $wgUser, $wgMemc;
		if ( $wgUser->isLoggedIn() ) {
			$key = wfMemcKey( 'openstackmanager', 'roles', $wgUser->getName() );
			$roles = $wgMemc->get( $key );
			if ( !is_array( $roles ) ) {
				$user = new OpenStackNovaUser();
				$roles = $user->getRoles();
			}
			$groups = array_unique( array_merge( $groups, $roles ) );
		}

		return true;
	}

	public static function addUserToBastionProject( $user, &$group ) {
		global $wgOpenStackManagerBastionProjectId;

		if ( User::groupHasPermission( $group, 'loginviashell' ) ) {
			// Add the user to the bastion project if not already a
			// member.
			$username = $user->getName();
			$project = new OpenStackNovaProject( $wgOpenStackManagerBastionProjectId );
			if ( !in_array( $username, $project->getMembers() ) ) {
				$project->addMember( $username );
			}
		}
		return true;
	}

	public static function removeUserFromBastionProject( $user, &$group ) {
		global $wgOpenStackManagerRemoveUserFromBastionProjectOnShellDisable;
		global $wgOpenStackManagerRemoveUserFromAllProjectsOnShellDisable;
		global $wgOpenStackManagerBastionProjectId;

		// Check whether after removing the group the user would still
		// have the loginviashell permission.
		foreach ( $user->getEffectiveGroups() as $g ) {
			// Ignore the group that will be removed.
			if ( $g === $group ) {
				continue;
			}
			// If the user still has the loginviashell permission, we
			// can immediately return.
			if ( User::groupHasPermission( $g, 'loginviashell' ) ) {
				return true;
			}
		}

		// At this point we know that the user will not have the
		// loginviashell permission after the group is removed so we
		// can remove him from the bastion projects if the
		// configuration requires that.
		$username = $user->getName();

		if ( $wgOpenStackManagerRemoveUserFromAllProjectsOnShellDisable ) {
			// Get a users projects
			$userLDAP = new OpenStackNovaUser( $username );
			foreach ( $userLDAP->getProjects() as $projectId ) {
				// Remove the user from the project
				$project = new OpenStackNovaProject( $projectId );
				$project->deleteMember( $username );
			}
		} elseif ( $wgOpenStackManagerRemoveUserFromBastionProjectOnShellDisable ) {
			// Remove the user from the bastion project
			$project = new OpenStackNovaProject( $wgOpenStackManagerBastionProjectId );
			if ( in_array( $username, $project->getMembers() ) ) {
				$project->deleteMember( $username );
			}
		}
		return true;
	}

	/**
	 * @param $user User
	 * @param $preferences array
	 * @return bool True
	 */
	public static function novaUserPreferences( User $user, array &$preferences ) {
		$link = Linker::link( SpecialPage::getTitleFor( 'NovaKey' ),
			wfMessage( 'novakey' )->escaped(),
			[],
			[ 'returnto' => SpecialPage::getTitleFor( 'Preferences' )->getPrefixedText() ]
		);

		$novaUser = new OpenStackNovaUser( $user->getName() );

		$preferences['shellusername'] = [
			'type' => 'info',
			'label-message' => 'openstackmanager-shellaccountname-pref',
			'default' => $novaUser->getUid(),
			'section' => 'personal/info',
		];

		$preferences['openstack-sshkeylist'] = [
			'type' => 'info',
			'raw' => true,
			'default' => self::getKeyList( $novaUser ),
			'label-message' => 'openstackmanager-prefs-novapublickey',
			'section' => 'openstack/openstack-keys',
		];
		return true;
	}

	/**
	 * @param $user OpenStackNovaUser
	 * @return string
	 */
	static function getKeyList( $user ) {
		global $wgOpenStackManagerNovaKeypairStorage;
		$keyInfo = [];
		if ( $wgOpenStackManagerNovaKeypairStorage === 'nova' ) {
			$projects = $user->getProjects();
			$keyInfo['keyname'] = [
				'type' => 'text',
				'label-message' => 'openstackmanager-novakeyname',
				'default' => '',
				'name' => 'keyname',
			];
			$project_keys = [];
			foreach ( $projects as $project ) {
				$project_keys[$project] = $project;
			}
			$keyInfo['project'] = [
				'type' => 'select',
				'options' => $project_keys,
				'label-message' => 'openstackmanager-project',
				'name' => 'project',
			];
		}
		$keyInfo['key'] = [
			'type' => 'textarea',
			'default' => '',
			'label-message' => 'openstackmanager-novapublickey',
			'name' => 'key',
		];

		$out = '';
		if ( $wgOpenStackManagerNovaKeypairStorage === 'nova' ) {
			# TODO: add project filter
			foreach ( $projects as $project ) {
				$userCredentials = $user->getCredentials();
				$userNova = new OpenStackNovaController( $userCredentials, $project );
				$keypairs = $userNova->getKeypairs();
				if ( !$keypairs ) {
					continue;
				}
				$out .= Html::element( 'h2', [], $project );
				$headers = [ 'openstackmanager-name', 'openstackmanager-fingerprint' ];
				$keyRows = [];
				foreach ( $keypairs as $keypair ) {
					$keyRow = [];
					SpecialNova::pushResourceColumn( $keyRow, $keypair->getKeyName() );
					SpecialNova::pushResourceColumn( $keyRow, $keypair->getKeyFingerprint() );
					$keyRows[] = $keyRow;
				}
				$out .= SpecialNova::createResourceTable( $headers, $keyRows );
			}
		} elseif ( $wgOpenStackManagerNovaKeypairStorage === 'ldap' ) {
			$headers = [ 'openstackmanager-keys', 'openstackmanager-actions' ];
			$keypairs = $user->getKeypairs();
			$keyRows = [];
			foreach ( $keypairs as $hash => $key ) {
				$keyRow = [];
				SpecialNova::pushResourceColumn( $keyRow, $key, [ 'class' => 'Nova_col' ] );
				$actions = [];
				$actions[] = SpecialNova::createNovaKeyActionLink(
					'openstackmanager-delete',
					[
						'action' => 'delete',
						'hash' => $hash,
						'returnto' => SpecialPage::getTitleFor(
							'Preferences', false, 'mw-prefsection-openstack'
						)->getFullText()
					]
				);
				SpecialNova::pushRawResourceColumn(
					$keyRow, SpecialNova::createResourceList( $actions )
				);
				$keyRows[] = $keyRow;
			}
			$out .= SpecialNova::createResourceTable( $headers, $keyRows );
		}
		$out .= Linker::link(
			SpecialPage::getTitleFor( 'NovaKey' ),
			wfMessage( 'openstackmanager-addkey' )->escaped(),
			[],
			[
				'returnto' => SpecialPage::getTitleFor(
					'Preferences', false, 'mw-prefsection-openstack'
				)->getFullText()
			]
		);
		return $out;
	}
}

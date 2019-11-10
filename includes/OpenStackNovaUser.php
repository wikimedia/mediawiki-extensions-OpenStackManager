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
	public function __construct( $username = '' ) {
		$this->username = $username;
		self::connectToLdap();
		$this->fetchUserInfo();
	}

	/**
	 * Connect to LDAP as the open stack manager account using LdapAuthenticationPlugin
	 */
	private static function connectToLdap() {
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		// Only reconnect/rebind if we aren't alredy bound
		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( $ldap->boundAs !== $wgOpenStackManagerLDAPUser ) {
			$ldap->connect( $wgOpenStackManagerLDAPDomain );
			$ldap->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
		}
	}

	/**
	 * @return void
	 */
	public function fetchUserInfo() {
		global $wgUser;

		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( $this->username ) {
			$this->userDN = $ldap->getUserDN( $this->username );
			$ldap->printDebug( "Fetching userdn using username: $this->userDN ", NONSENSITIVE );
			if ( !$this->userDN ) {
				$this->userDN = $ldap->getUserDN( $this->username, false, "uid" );
				$ldap->printDebug(
					"Fetching userdn using shell name: $this->userDN ", NONSENSITIVE
				);

				# We want the actual username, not the id that was passed in.
				$this->userInfo = $ldap->userInfo;
				$this->username = $this->userInfo[0]['cn'][0];
			}
		} else {
			$this->userDN = $ldap->getUserDN( $wgUser->getName() );
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
	public function getUid() {
		return $this->userInfo[0]['uid'][0];
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * @return array
	 */
	public function getKeypairs() {
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
	 * @param string $key
	 * @return bool
	 */
	public function importKeypair( $key ) {
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
	 * @param string $key
	 * @return bool
	 */
	public function deleteKeypair( $key ) {
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
	 * @param LdapAuthenticationPlugin $auth
	 * @param string $attr
	 * @return mixed|string
	 */
	public static function getNextIdNumber( $auth, $attr ) {
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
	 * @param LdapAuthenticationPlugin $auth
	 * @param string $username
	 * @param array &$values
	 * @param string $writeloc
	 * @param string &$userdn
	 * @param bool &$result
	 * @return bool
	 */
	public static function LDAPSetCreationValues(
		$auth, $username, &$values, $writeloc, &$userdn, &$result
	) {
		global $wgOpenStackManagerLDAPDefaultGid;
		global $wgOpenStackManagerLDAPDefaultShell;

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

		$shellaccountname = \MediaWiki\Auth\AuthManager::singleton()
			->getAuthenticationSessionData( 'osm-shellaccountname', '' );

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
	 * @param LdapAuthenticationPlugin $auth
	 * @param string $username
	 * @param array &$values
	 * @param string $writeloc
	 * @param string &$userdn
	 * @param string &$result
	 * @return bool
	 */
	public static function LDAPRetrySetCreationValues(
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
	 * @param BaseTemplate &$template
	 * @return bool
	 */
	public static function LDAPModifyUITemplate( &$template ) {
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
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public static function AuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		if ( isset( $formDescriptor['shellaccountname'] ) ) {
			$formDescriptor['shellaccountname'] += [
				'help-message' => 'openstackmanager-shellaccountnamehelp',
				'weight' => 90,
			];
		};
	}

	/**
	 * @param User $user
	 * @param array &$preferences
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
	 * @param OpenStackNovaUser $user
	 * @return string
	 */
	public static function getKeyList( $user ) {
		global $wgOpenStackManagerNovaKeypairStorage;
		$keyInfo = [];
		$keyInfo['key'] = [
			'type' => 'textarea',
			'default' => '',
			'label-message' => 'openstackmanager-novapublickey',
			'name' => 'key',
		];

		$out = '';
		if ( $wgOpenStackManagerNovaKeypairStorage === 'ldap' ) {
			$headers = [ 'openstackmanager-keys', 'openstackmanager-actions' ];
			$keypairs = $user->getKeypairs();
			$keyRows = [];
			foreach ( $keypairs as $hash => $key ) {
				$keyRow = [];
				SpecialNovaKey::pushResourceColumn( $keyRow, $key, [ 'class' => 'Nova_col' ] );
				$actions = [];
				$actions[] = SpecialNovaKey::createNovaKeyActionLink(
					'openstackmanager-delete',
					[
						'action' => 'delete',
						'hash' => $hash,
						'returnto' => SpecialPage::getTitleFor(
							'Preferences', false, 'mw-prefsection-openstack'
						)->getFullText()
					]
				);
				SpecialNovaKey::pushRawResourceColumn(
					$keyRow, SpecialNovaKey::createResourceList( $actions )
				);
				$keyRows[] = $keyRow;
			}
			$out .= SpecialNovaKey::createResourceTable( $headers, $keyRows );
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

	/**
	 * getUserPermissionsErrors hook
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param array &$result
	 * @return bool
	 */
	public static function getUserPermissionsErrors( Title $title, User $user, $action, &$result ) {
		if (
			$title->inNamespace( 666 /*NS_HIERA*/ ) &&
			( $action === 'create' || $action === 'edit' )
		) {
			$result = [ 'openstackmanager-hiera-disabled' ];
			return false;
		}
		return true;
	}
}

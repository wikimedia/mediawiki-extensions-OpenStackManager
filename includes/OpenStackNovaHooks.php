<?php

namespace MediaWiki\Extension\OpenStackManager;

/**
 * @file
 * @ingroup Extensions
 */

use LdapAuthenticationPlugin;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SpecialPage;
use User;

class OpenStackNovaHooks {

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
	private static function getNextIdNumber( $auth, $attr ) {
		global $wgOpenStackManagerIdRanges;

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
		if ( $auth->realname !== '' ) {
			$values['displayname'] = $auth->realname;
		}

		$shellaccountname = MediaWikiServices::getInstance()->getAuthManager()
			->getAuthenticationSessionData( 'osm-shellaccountname', '' );

		if ( !preg_match( "/^[a-z][a-z0-9\-_]*$/", $shellaccountname ) ) {
			$auth->printDebug( "Invalid shell name $shellaccountname", NONSENSITIVE );
			$result = false;
			return false;
		}
		$check = ucfirst( $shellaccountname );
		$userNameUtils = MediaWikiServices::getInstance()->getUserNameUtils();
		if ( !$userNameUtils->isCreatable( $check ) ) {
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
	 * @param \MediaWiki\Auth\AuthenticationRequest[] $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public static function AuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
		if ( isset( $formDescriptor['shellaccountname'] ) ) {
			$formDescriptor['shellaccountname'] += [
				'placeholder-message' => 'openstackmanager-shellaccountname-placeholder',
				'help-message' => 'openstackmanager-shellaccountnamehelp',
				'weight' => 90,
			];
		}
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 * @return bool True
	 */
	public static function novaUserPreferences( User $user, array &$preferences ) {
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
	 * Add icon for Special:Preferences mobile layout
	 *
	 * @param array &$iconNames Array of icon names for their respective sections.
	 */
	public static function onPreferencesGetIcon( &$iconNames ) {
		$iconNames[ 'openstack' ] = 'key';
	}

	/**
	 * @param OpenStackNovaUser $user
	 * @return string
	 */
	private static function getKeyList( $user ) {
		$out = '';
		$headers = [ 'openstackmanager-keys', 'openstackmanager-actions' ];
		$keypairs = $user->getKeypairs();
		$keyRows = [];

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		foreach ( $keypairs as $hash => $key ) {
			$keyRow = [];
			SpecialNovaKey::pushResourceColumn( $keyRow, $key, [ 'class' => 'Nova_col' ] );
			$actions = [];
			$actions[] = $linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'NovaKey' ),
				wfMessage( 'openstackmanager-delete' )->text(),
				[],
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

		$out .= $linkRenderer->makeLink(
			SpecialPage::getTitleFor( 'NovaKey' ),
			wfMessage( 'openstackmanager-addkey' )->text(),
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

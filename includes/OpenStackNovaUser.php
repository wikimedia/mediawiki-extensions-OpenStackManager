<?php

namespace MediaWiki\Extension\OpenStackManager;

/**
 * @file
 * @ingroup Extensions
 */

use LdapAuthenticationPlugin;
use MediaWiki\MediaWikiServices;
use RequestContext;

class OpenStackNovaUser {

	/** @var string */
	private $username;
	/** @var string */
	private $userDN;
	/** @var array */
	private $userInfo;

	/**
	 * @param string $username
	 */
	public function __construct( $username = '' ) {
		$this->username = $username;
		$this->connectToLdap();
		$this->fetchUserInfo();
	}

	/**
	 * Connect to LDAP as the openstack manager account using LdapAuthenticationPlugin
	 */
	private function connectToLdap() {
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		// Only reconnect/rebind if we aren't already bound
		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( $ldap->boundAs !== $wgOpenStackManagerLDAPUser ) {
			$ldap->connect( $wgOpenStackManagerLDAPDomain );
			$ldap->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
		}
	}

	private function fetchUserInfo() {
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
				$this->username = $ldap->userInfo[0]['cn'][0] ?? '';
			}
		} else {
			$username = RequestContext::getMain()->getUser()->getName();
			$this->userDN = $ldap->getUserDN( $username );
			$this->username = $username;
			$ldap->printDebug(
				"Fetching userdn using wiki name: " . $username, NONSENSITIVE
			);
		}
		$this->userInfo = $ldap->userInfo;
	}

	/**
	 * @return string
	 */
	public function getUid() {
		return $this->userInfo[0]['uid'][0] ?? '';
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
			$ldap->printDebug( "Deleting memcache key: $key.", NONSENSITIVE );
			// @TODO: don't depend on cache key naming internals of another extension
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$cache->delete( $cache->makeKey( 'ldapauthentication-userinfo', $this->userDN ) );
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
				$ldap->printDebug( "Deleting memcache key: $key.", NONSENSITIVE );
				// @TODO: don't depend on cache key naming internals of another extension
				$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
				$cache->delete( $cache->makeKey( 'ldapauthentication-userinfo', $this->userDN ) );
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

}

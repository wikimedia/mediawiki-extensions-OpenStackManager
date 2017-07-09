<?php

use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\AuthManager;

class OpenStackNovaSecondaryAuthenticationProvider
	extends AbstractPreAuthenticationProvider
{
	public function __construct( $params = [] ) {
	}

	public function getAuthenticationRequests( $action, array $options ) {
		if ( $action === AuthManager::ACTION_CREATE ) {
			return [ new OpenStackNovaShellAccountNameRequest() ];
		}
		return [];
	}

	public function testForAccountCreation( $user, $creator, array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass(
			$reqs, OpenStackNovaShellAccountNameRequest::class );
		$shellaccountname = $req ? $req->shellaccountname : '';

		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( !preg_match( "/^[a-z][a-z0-9\-_]*$/", $shellaccountname ) ) {
			$ldap->printDebug( "Invalid shell name $shellaccountname", NONSENSITIVE );
			$message = wfMessage( 'openstackmanager-shellaccountvalidationfail' );
			return StatusValue::newFatal( $message );
		}

		$ldap->connect();
		$base = $ldap->getBaseDN( USERDN );
		$result = LdapAuthenticationPlugin::ldap_search(
			$ldap->ldapconn, $base, "(uid=$shellaccountname)"
		);
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
			if ( (int)$entries['count'] > 0 ) {
				$ldap->printDebug( "User $shellaccountname already exists.", NONSENSITIVE );
				$message = wfMessage( 'openstackmanager-shellaccountexists' );
				return StatusValue::newFatal( $message );
			}
		}

		// OpenStackNovaUser::LDAPSetCreationValues will use this
		$this->manager->setAuthenticationSessionData( 'osm-shellaccountname', $shellaccountname );

		$sv = StatusValue::newGood();
		if ( class_exists( 'TitleBlacklist' ) ) {
			$sv->merge( TitleBlacklistHooks::testUserName(
				$shellaccountname,
				$creator,
				$override = false,
				$log = true
			) );
		}

		return $sv;
	}
}

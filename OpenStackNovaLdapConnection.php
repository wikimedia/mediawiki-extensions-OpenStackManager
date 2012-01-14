<?php

/**
 * class for nova ldap
 *
 * @file
 * @ingroup Extensions
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is a part of the OpenStackManager extension and cannot be used standalone.\n" );
	die( 1 );
}

class OpenStackNovaLdapConnection {

	/**
	 * Connect to LDAP as the open stack manager account using wgAuth
	 */
	static function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		// Only reconnect/rebind if we aren't alredy bound
		if ( $wgAuth->boundAs != $wgOpenStackManagerLDAPUser ) {
			$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
			$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
		}
	}

}

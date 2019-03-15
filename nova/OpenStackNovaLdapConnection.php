<?php

/**
 * class for nova ldap
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaLdapConnection {
	/**
	 * Connect to LDAP as the open stack manager account using LdapAuthenticationPlugin
	 */
	public static function connect() {
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		// Only reconnect/rebind if we aren't alredy bound
		$ldap = LdapAuthenticationPlugin::getInstance();
		if ( $ldap->boundAs !== $wgOpenStackManagerLDAPUser ) {
			$ldap->connect( $wgOpenStackManagerLDAPDomain );
			$ldap->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
		}
	}
}

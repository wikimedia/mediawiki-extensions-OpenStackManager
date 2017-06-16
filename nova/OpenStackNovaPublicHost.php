<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaPublicHost extends OpenStackNovaHost {

	/**
	 * @var string
	 */
	public $ip;

	/**
	 * @param  $ip
	 */
	function __construct( $ip ) {
		$this->domainCache = null;
		$this->ip = $ip;
		OpenStackNovaLdapConnection::connect();
		$this->fetchHostInfo();
	}

	/**
	 * Fetch the host from LDAP and initialize the object
	 *
	 * @return void
	 */
	function fetchHostInfo() {
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$this->ip = $ldap->getLdapEscapedString( $this->ip );
		$result = LdapAuthenticationPlugin::ldap_search( $ldap->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(dc=' . $this->ip . ')' );
		$this->hostInfo = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
		if ( $this->hostInfo["count"] == "0" ) {
			$this->hostInfo = null;
		} else {
			$this->hostDN = $this->hostInfo[0]['dn'];
		}
	}

	/**
	 * Return the domain associated with this host
	 *
	 * @return OpenStackNovaDomain
	 */
	function getDomain() {
		if ( !$this->domainCache ) {
			$this->domainCache = OpenStackNovaDomain::getDomainByHostIP( $this->ip );
			if ( !$this->domainCache ) {
				$ldap = LdapAuthenticationPlugin::getInstance();
				$ldap->printDebug( "Looked up domain for ip $this->ip but domainCache is still empty.", NONSENSITIVE );
			}
		}
		return $this->domainCache;
	}

}

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
		global $wgAuth;

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
		global $wgAuth;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$this->ip = $wgAuth->getLdapEscapedString( $this->ip );
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(dc=' . $this->ip . ')' );
		$this->hostInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
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
		global $wgAuth;

		if ( ! $this->domainCache ) {
			$this->domainCache = OpenStackNovaDomain::getDomainByHostIP( $this->ip );
			if (! $this->domainCache ) {
		    		$wgAuth->printDebug( "Looked up domain for ip $this->ip but domainCache is still empty.", NONSENSITIVE );
			}
		}
		return $this->domainCache;
	}

}

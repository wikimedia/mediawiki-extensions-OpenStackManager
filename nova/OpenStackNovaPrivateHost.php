<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaPrivateHost extends OpenStackNovaHost {

	/**
	 * @var string
	 */
	public $instancename;
	public $instanceproject;

	/**
	 * @var string
	 */
	public $region;

	/**
	 * @param  $instancename
	 * @param  $instanceproject
	 * @param  $region
	 */
	function __construct( $instancename, $instanceproject, $region ) {
		global $wgAuth;

		$this->instancename = $wgAuth->getLdapEscapedString( $instancename );
		$this->instanceproject = $wgAuth->getLdapEscapedString( $instanceproject );
		$this->region = $region;
		$this->domainCache = null;
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

		if ($this->getDomain()) {
			$fqdn = $this->instancename . '.' . $this->instanceproject . '.' . $this->getDomain()->getFullyQualifiedDomainName();
		} else {
			# No domain means no instance!
			$this->hostInfo = null;
			return;
		}
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(dc=' . $fqdn . ')' );
		$this->hostInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $this->hostInfo["count"] == "0" ) {
			$this->hostInfo = null;
		} else {
			$this->hostDN = $this->hostInfo[0]['dn'];
		}
	}

	/**
	 * Return a private host's host's fully qualified display name
	 *
	 * (Note that calling this for a public host doesn't make sense since public
	 *  host entries have multiple FQDNs.)
	 *
	 * @return string
	 */
	function getFullyQualifiedDisplayName() {
		global $wgAuth;

		if ($this->getDomain()) {
			$fqdn = $this->instancename . '.' . $this->instanceproject . '.' . $this->getDomain()->getFullyQualifiedDomainName();
			return $fqdn;
		} else {
			$wgAuth->printDebug( "Error: Unable to determine instancename of " . $this->instancename, NONSENSITIVE );
			return "";
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
			$this->domainCache = OpenStackNovaDomain::getDomainByRegion( $this->region );
			if (! $this->domainCache ) {
		    		$wgAuth->printDebug( "Looked up domain for region $this->region but domainCache is still empty.", NONSENSITIVE );
			}
		}
		return $this->domainCache;
	}

	/**
	 * Return human-readable hostname
	 *
	 * @return string
	 */
	function getDisplayName() {
		$pieces = explode( '.', $this->getFullyQualifiedDisplayName() );
		return $pieces[0];
	}

	/**
	 *
	 * Return <hostname>.<project>.<domain> for a private host
	 *
	 * (Note that calling this for a public host doesn't make sense since public
	 *  host entries have multiple FQDNs.)
	 *
	 * @return string
	 */
	function getFullyQualifiedHostName() {
		return $this->getFullyQualifiedDisplayName();
	}

	/**
	 * Return the puppet classes and variables assigned to this host
	 *
	 * @return array
	 */
	function getPuppetConfiguration() {
		$puppetinfo = array( 'puppetclass' => array(), 'puppetvar' => array() );
		if ( isset( $this->hostInfo[0]['puppetclass'] ) ) {
			array_shift( $this->hostInfo[0]['puppetclass'] );
			foreach ( $this->hostInfo[0]['puppetclass'] as $class ) {
				$puppetinfo['puppetclass'][] = $class;
			}
		}
		if ( isset( $this->hostInfo[0]['puppetvar'] ) ) {
			array_shift( $this->hostInfo[0]['puppetvar'] );
			foreach ( $this->hostInfo[0]['puppetvar'] as $variable ) {
				$vararr = explode( '=', $variable );
				$varname = trim( $vararr[0] );
				$var = trim( $vararr[1] );
				$puppetinfo['puppetvar'][$varname] = $var;
			}
		}
		return $puppetinfo;
	}

	/**
	 * Update puppet classes and variables for this host.
	 *
	 * @param  $puppetinfo
	 * @return bool
	 */
	function modifyPuppetConfiguration( $puppetinfo ) {
		global $wgAuth;
		global $wgOpenStackManagerPuppetOptions;

		$hostEntry = array( 'puppetclass' => array(), 'puppetvar' => array() );
		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			if ( isset( $puppetinfo['classes'] ) ) {
				foreach ( $puppetinfo['classes'] as $class ) {
					$hostEntry['puppetclass'][] = $class;
				}
			}
			if ( isset( $puppetinfo['variables'] ) ) {
				foreach ( $puppetinfo['variables'] as $variable => $value ) {
					$hostEntry['puppetvar'][] = $variable . '=' . $value;
				}
			}
			$oldpuppetinfo = $this->getPuppetConfiguration();
			if ( isset( $oldpuppetinfo['puppetvar'] ) ) {
				$wgAuth->printDebug( "Checking for preexisting variables", NONSENSITIVE );
				foreach ( $oldpuppetinfo['puppetvar'] as $variable => $value ) {
					$wgAuth->printDebug( "Found $variable", NONSENSITIVE );
					if ( $variable === "instanceproject" || $variable === "instancename" ) {
						$hostEntry['puppetvar'][] = $variable . '=' . $value;
					}
				}
			}

			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->hostDN, $hostEntry );
			if ( $success ) {
				$this->fetchHostInfo();
				$wgAuth->printDebug( "Successfully modified puppet configuration for host", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to modify puppet configuration for host", NONSENSITIVE );
				return false;
			}
		}
		return false;
	}

}

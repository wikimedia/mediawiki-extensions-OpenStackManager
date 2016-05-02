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
		$ldap = LdapAuthenticationPlugin::getInstance();
		$this->instancename = $ldap->getLdapEscapedString( $instancename );
		$this->instanceproject = $ldap->getLdapEscapedString( $instanceproject );
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
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		if ($this->getDomain()) {
			$fqdn = $this->instancename . '.' . $this->instanceproject . '.' . $this->getDomain()->getFullyQualifiedDomainName();
		} else {
			# No domain means no instance!
			$this->hostInfo = null;
			return;
		}
		$result = LdapAuthenticationPlugin::ldap_search( $ldap->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(dc=' . $fqdn . ')' );
		$this->hostInfo = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
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
		if ($this->getDomain()) {
			$fqdn = $this->instancename . '.' . $this->instanceproject . '.' . $this->getDomain()->getFullyQualifiedDomainName();
			return $fqdn;
		} else {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$ldap->printDebug( "Error: Unable to determine instancename of " . $this->instancename, NONSENSITIVE );
			return "";
		}
	}

	/**
	 * Return the domain associated with this host
	 *
	 * @return OpenStackNovaDomain
	 */
	function getDomain() {
		if ( ! $this->domainCache ) {
			$this->domainCache = OpenStackNovaDomain::getDomainByRegion( $this->region );
			if (! $this->domainCache ) {
				$ldap = LdapAuthenticationPlugin::getInstance();
				$ldap->printDebug( "Looked up domain for region $this->region but domainCache is still empty.", NONSENSITIVE );
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
		global $wgOpenStackManagerPuppetOptions;

		$hostEntry = array( 'puppetclass' => array(), 'puppetvar' => array() );
		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			$ldap = LdapAuthenticationPlugin::getInstance();
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
				$ldap->printDebug( "Checking for preexisting variables", NONSENSITIVE );
				foreach ( $oldpuppetinfo['puppetvar'] as $variable => $value ) {
					$ldap->printDebug( "Found $variable", NONSENSITIVE );
					if ( $variable === "instanceproject" || $variable === "instancename" ) {
						$hostEntry['puppetvar'][] = $variable . '=' . $value;
					}
				}
			}

			$success = LdapAuthenticationPlugin::ldap_modify( $ldap->ldapconn, $this->hostDN, $hostEntry );
			if ( $success ) {
				$this->fetchHostInfo();
				$ldap->printDebug( "Successfully modified puppet configuration for host", NONSENSITIVE );
				return true;
			} else {
				$ldap->printDebug( "Failed to modify puppet configuration for host", NONSENSITIVE );
				return false;
			}
		}
		return false;
	}

}

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
	var $instanceid;

	/**
	 * @var string
	 */
	var $region;

	/**
	 * @param  $instanceid
	 * @param  $ip
	 *  (specify $instanceid for private, $ip for public)
	 */
	function __construct( $instanceid, $region ) {
		global $wgAuth;

		$this->instanceid = $instanceid;
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

		$this->instanceid = $wgAuth->getLdapEscapedString( $this->instanceid );
		if ($this->getDomain()) {
			$fqdn = $this->instanceid . '.' . $this->getDomain()->getFullyQualifiedDomainName();
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

		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$domains = $this->hostInfo[0]['associateddomain'];
			array_shift( $domains );
			foreach ( $domains as $domain ) {
				$pieces = explode( '.', $domain );
				$name = $pieces[0];
				if ( $name != $this->instanceid ) {
					# A leap of faith:  There should
					# be two associated domains, one based on the id and
					# one the display name.  So, if this one isn't the id,
					# it must be the display.
					return $domain;
				}
			}
		}

		$wgAuth->printDebug( "Error: Unable to determine instancename of " . $this->instanceid, NONSENSITIVE );
		return "";
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
	 * Return i-xxxxx.<domain> for a private host
	 *
	 * (Note that calling this for a public host doesn't make sense since public
	 *  host entries have multiple FQDNs.)
	 *
	 * @return string
	 */
	function getFullyQualifiedHostName() {
		global $wgAuth;

		return $this->instanceid . '.' . $this->getDomain()->getFullyQualifiedDomainName();
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

		$hostEntry = array();
		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			foreach ( $wgOpenStackManagerPuppetOptions['defaultclasses'] as $class ) {
				$hostEntry['puppetclass'][] = $class;
			}
			foreach ( $wgOpenStackManagerPuppetOptions['defaultvariables'] as $variable => $value ) {
				$hostEntry['puppetvar'][] = $variable . '=' . $value;
			}
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
			if ( $hostEntry ) {
				$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->hostDN, $hostEntry );
				if ( $success ) {
					$this->fetchHostInfo();
					$wgAuth->printDebug( "Successfully modified puppet configuration for host", NONSENSITIVE );
					return true;
				} else {
					$wgAuth->printDebug( "Failed to modify puppet configuration for host", NONSENSITIVE );
					return false;
				}
			} else {
				$wgAuth->printDebug( "No hostEntry when trying to modify puppet configuration", NONSENSITIVE );
				return false;
			}
		}
		return false;
	}

}

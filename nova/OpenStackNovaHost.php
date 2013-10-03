<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaHost {
	/**
	 * @var bool
	 */
	var $private;

	/**
	 * @var string
	 */
	var $instanceid;

	/**
	 * @var string
	 */
	var $hostDN;

	/**
	 * @var string
	 */
	var $ip;

	/**
	 * @var mixed
	 */
	var $hostInfo;

	/**
	 * @var OpenStackNovaDomain
	 */
	var $domainCache;

	/**
	 * @param  $instanceid
	 * @param  $ip
	 *  (specify $instanceid for private, $ip for public)
	 */
	function __construct( $private, $instanceid, $ip ) {
		global $wgAuth;

		$this->private = $private;
		$this->instanceid = $instanceid;
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

		$this->instanceid = $wgAuth->getLdapEscapedString( $this->instanceid );
		if ( $this->private ) {
			if ($this->getDomain()) {
				$fqdn = $this->instanceid . '.' . $this->getDomain()->getFullyQualifiedDomainName();
			} else {
				# No domain means no instance!
				$this->hostInfo = null;
				return;
			}
			$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(dc=' . $fqdn . ')' );
		} else {
			$this->ip = $wgAuth->getLdapEscapedString( $this->ip );
			$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(dc=' . $this->ip . ')' );
		}
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

		if ( ! $this->private ) {
			$wgAuth->printDebug( "getFullyQualifiedDisplayName called on public host, so this will probably break.", NONSENSITIVE );
		}

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
			if ( $this->private ) {
				$this->domainCache = OpenStackNovaDomain::getDomainByInstanceId( $this->instanceid );
				if (! $this->domainCache ) {
		    		$wgAuth->printDebug( "Looked up domain for id $this->instanceid but domainCache is still empty.", NONSENSITIVE );
				}
			} else {
				$this->domainCache = OpenStackNovaDomain::getDomainByHostIP( $this->ip );
				if (! $this->domainCache ) {
		    		$wgAuth->printDebug( "Looked up domain for ip $this->ip but domainCache is still empty.", NONSENSITIVE );
				}
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

		if ( ! $this->private ) {
			$wgAuth->printDebug( "getFullyQualifiedDisplayName called on a public host; this will probably break.", NONSENSITIVE );
		}

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
	 * Return all arecords associated with this host. Return an empty
	 * array if the arecord attribute is not set.
	 *
	 * @return array
	 */
	function getARecords() {
		$arecords = array();
		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$arecords = $this->hostInfo[0]['arecord'];
			array_shift( $arecords );
		}

		return $arecords;
	}

	/**
	 * Return all associateddomain records associated with this host.
	 * Return an empty array if the arecord attribute is not set.
	 *
	 * @return array
	 */
	function getAssociatedDomains() {
		$associateddomain = array();
		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$associateddomain = $this->hostInfo[0]['associateddomain'];
			array_shift( $associateddomain );
		}

		return $associateddomain;
	}

	/**
	 * Return all cname records associated with this host.
	 * Return an empty array if the arecord attribute is not set.
	 *
	 * @return array
	 */
	function getCNAMERecords() {
		$cnamerecords = array();
		if ( isset( $this->hostInfo[0]['cnamerecord'] ) ) {
			$cnamerecords = $this->hostInfo[0]['cnamearecord'];
			array_shift( $cnamerecords );
		}

		return $cnamerecords;
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

	/**
	 * Remove an associated domain record from this entry.
	 *
	 * @param  $fqdn
	 * @return bool
	 */
	function deleteAssociatedDomain( $fqdn ) {
		global $wgAuth;

		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$associateddomains = $this->hostInfo[0]['associateddomain'];
			array_shift( $associateddomains );
			$index = array_search( $fqdn, $associateddomains );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find $fqdn in associateddomain list", NONSENSITIVE );
				return false;
			}
			unset( $associateddomains[$index] );
			$values = array();
			$values['associateddomain'] = array();
			foreach ( $associateddomains as $associateddomain ) {
				$values['associateddomain'][] = $associateddomain;
			}
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
			if ( $success ) {
				$wgAuth->printDebug( "Successfully removed $fqdn from $this->hostDN", NONSENSITIVE );
				$this->getDomain()->updateSOA();
				$this->fetchHostInfo();
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $fqdn from $this->hostDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Remove an arecord from the host.
	 *
	 * @param  $ip
	 * @return bool
	 */
	function deleteARecord( $ip ) {
		global $wgAuth;

		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$arecords = $this->hostInfo[0]['arecord'];
			array_shift( $arecords );
			$index = array_search( $ip, $arecords );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find ip address in arecords list", NONSENSITIVE );
				return false;
			}
			unset( $arecords[$index] );
			$values = array();
			$values['arecord'] = array();
			foreach ( $arecords as $arecord ) {
				$values['arecord'][] = $arecord;
			}
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
			if ( $success ) {
				$wgAuth->printDebug( "Successfully removed $ip from $this->hostDN", NONSENSITIVE );
				$this->getDomain()->updateSOA();
				$this->fetchHostInfo();
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $ip from $this->hostDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Add an associated domain record to this host.
	 *
	 * @param  $fqdn
	 * @return bool
	 */
	function addAssociatedDomain( $fqdn ) {
		global $wgAuth;

		$associatedomains = array();
		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$associatedomains = $this->hostInfo[0]['associateddomain'];
			array_shift( $associatedomains );
		}
		$associatedomains[] = $fqdn;
		$values = array();
		$values['associateddomain'] = $associatedomains;
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added $fqdn to $this->hostDN", NONSENSITIVE );
			$this->getDomain()->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $fqdn to $this->hostDN", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Add an arecord entry to this host.
	 *
	 * @param  $ip
	 * @return bool
	 */
	function addARecord( $ip ) {
		global $wgAuth;

		$arecords = array();
		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$arecords = $this->hostInfo[0]['arecord'];
			array_shift( $arecords );
		}
		$arecords[] = $ip;
		$values = array();
		$values['arecord'] = $arecords;
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added $ip to $this->hostDN", NONSENSITIVE );
			$this->getDomain()->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $ip to $this->hostDN", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Replace all arecords on this host with $ip.
	 *
	 * @param  $ip
	 * @return bool
	 */
	function setARecord( $ip ) {
		global $wgAuth;

		$values = array( 'arecord' => array( $ip ) );
		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully set $ip on $this->hostDN", NONSENSITIVE );
			$this->getDomain()->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$wgAuth->printDebug( "Failed to set $ip on $this->hostDN", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Get a host by the host's short name, and a OpenStackNovaDomain object. Returns
	 * null if the entry does not exist.
	 *
	 * @static
	 * @param  $instanceid
	 * @param  $domain
	 * @return OpenStackNovaHost
	 */
	static function getPrivateHost( $instanceid ) {
		$host = new OpenStackNovaHost( true, $instanceid, null );
		if ( $host->hostInfo ) {
			return $host;
		} else {
			return null;
		}
	}

	/**
	 * Get a public host by the host's ip. Returns
	 * null if the entry does not exist.
	 *
	 * @static
	 * @param  $ip
	 * @return OpenStackNovaHost
	 */
	static function getHostByPublicIP( $ip ) {
		global $wgAuth;

		$host = new OpenStackNovaHost( false, null, $ip );
		if ( $host->hostInfo ) {
			return $host;
		} else {
			return null;
		}
	}

	/**
	 * Get a host by an instance ID. Returns null if the entry does not exist.
	 *
	 * @static
	 * @param  $instanceid
	 * @return OpenStackNovaHost
	 */
	static function getHostByInstanceId( $instanceid ) {
		return self::getPrivateHost( $instanceid );
	}

	/**
	 * Get private host entries that has the specified IP address assigned. Returns
	 * null if none is found.
	 *
	 * @static
	 * @param  $ip
	 * @return array
	 */
	static function getHostByPrivateIP( $ip ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(arecord=' . $ip . ')' );
		$hostInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $hostInfo["count"] == "0" ) {
			return null;
		} else {
			$host = $hotsInfo[0];
			$instanceid = $host['dc'][0];
			$hostObject = OpenStackNovaHost::getHostByInstanceId( $instanceid );
			return $hostObject;
		}
	}

	/**
	 * Get all host entries in the specified domain. Returns an empty array
	 * if no entries are found.
	 *
	 * @static
	 * @param  $domain OpenStackNovaDomain
	 * @return array
	 */
	static function getAllHosts( $domain ) {
		global $wgAuth;

		OpenStackNovaLdapConnection::connect();

		$hosts = array();
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $domain->domainDN, '(dc=*)' );
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$hosts[] = new OpenStackNovaHost( true, $entry['dc'][0], null );
				}
			}
		}

		return $hosts;
	}

	/**
	 * Delete this host
	 *
	 * @param  $instanceid
	 * @return bool
	 */
	function deleteHost() {
		global $wgAuth;

		# Grab the domain now, before we delete the entry and it's no longer there to grab.
		$domain = $this->getDomain();

		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $this->hostDN );
		if ( $success ) {
			$domain->updateSOA();
			$wgAuth->printDebug( "Successfully deleted host " . $this->instanceid, NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete host " . $this->instanceid, NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Add a new host entry from an OpenStackNovaInstance object, an OpenStackNovaDomain object,
	 * and optional puppet information. Returns null if a host already exists, or if
	 * if the host additional fails. This function should be used for adding host entries
	 * for instances (private DNS).
	 *
	 * @static
	 * @param  $instance OpenStackNovaInstance
	 * @param  $domain OpenStackNovaDomain
	 * @param  $puppetinfo
	 * @return OpenStackNovaHost
	 */
	static function addHost( $instance, $domain, $puppetinfo = array() ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPInstanceBaseDN, $wgOpenStackManagerPuppetOptions;

		OpenStackNovaLdapConnection::connect();

		$hostname = $instance->getInstanceName();
		$instanceid = $instance->getInstanceId();
		$project = $instance->getProject();
		$tmpip = $instance->getInstancePrivateIPs();
		if ( $tmpip && isset( $tmpip[0] ) ) {
			$ip = $tmpip[0];
		} else {
			$ip = null;
		}
		$domainname = $domain->getFullyQualifiedDomainName();
		$fqdn = $instanceid . '.' . $domainname;
		$host = OpenStackNovaHost::getHostByInstanceId( $instanceid );
		if ( $host ) {
			$wgAuth->printDebug( "Failed to add host $hostname as the DNS entry already exists", NONSENSITIVE );
			return null;
		}
		$hostEntry = array();
		$hostEntry['objectclass'][] = 'dcobject';
		$hostEntry['objectclass'][] = 'dnsdomain';
		$hostEntry['objectclass'][] = 'domainrelatedobject';
		$hostEntry['dc'] = $fqdn;
		# $hostEntry['l'] = $instance->getInstanceAvailabilityZone();
		if ( $ip ) {
			$hostEntry['arecord'] = $ip;
		}
		$hostEntry['associateddomain'][] = $instanceid . '.' . $domainname;
		$hostEntry['associateddomain'][] = $hostname . '.' . $domainname;
		$hostEntry['l'] = $domain->getLocation();
		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			$hostEntry['objectclass'][] = 'puppetclient';
			foreach ( $wgOpenStackManagerPuppetOptions['defaultclasses'] as $class ) {
				$hostEntry['puppetclass'][] = $class;
			}
			foreach ( $wgOpenStackManagerPuppetOptions['defaultvariables'] as $variable => $value ) {
				$hostEntry['puppetvar'][] = $variable . '=' . $value;
			}
			if ( $puppetinfo ) {
				if ( isset( $puppetinfo['classes'] ) ) {
					foreach ( $puppetinfo['classes'] as $class ) {
						$hostEntry['puppetclass'][] = $class;
					}
				}
				if ( isset( $puppetinfo['variables'] ) ) {
					foreach ( $puppetinfo['variables'] as $variable => $value ) {
						if ( $value ) {
							$hostEntry['puppetvar'][] = $variable . '=' . $value;
						}
					}
				}
			}
			$hostEntry['puppetvar'][] = 'instanceproject=' . $project;
			$hostEntry['puppetvar'][] = 'instancename=' . $hostname;
		}
		$dn = 'dc=' . $fqdn . ',' . $wgOpenStackManagerLDAPInstanceBaseDN;

		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $dn, $hostEntry );
		if ( $success ) {
			$domain->updateSOA();
			$wgAuth->printDebug( "Successfully added host $hostname", NONSENSITIVE );
			return new OpenStackNovaHost( true, $hostname, null );
		} else {
			$wgAuth->printDebug( "Failed to add host $hostname with dn of $dn", NONSENSITIVE );
			return null;
		}
	}

	/**
	 * Adds a host entry based on the hostname, IP addrss, and a domain. Returns null
	 * if the entry already exists, or if the additional fails. This function should be used
	 * for adding public DNS entries.
	 *
	 * @static
	 * @param  $hostname
	 * @param  $ip
	 * @param  $domain OpenStackNovaDomain
	 * @return bool|null|OpenStackNovaHost
	 */
	static function addPublicHost( $hostname, $ip, $domain ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		OpenStackNovaLdapConnection::connect();

		$domainname = $domain->getFullyQualifiedDomainName();
		$host = OpenStackNovaHost::getHostByPublicIP( $ip );
		if ( $host ) {
			$wgAuth->printDebug( "Failed to add public host $hostname as the DNS entry already exists", NONSENSITIVE );
			return null;
		}
		$hostEntry = array();
		$hostEntry['objectclass'][] = 'dcobject';
		$hostEntry['objectclass'][] = 'dnsdomain';
		$hostEntry['objectclass'][] = 'domainrelatedobject';
		$hostEntry['dc'] = $ip;
		$hostEntry['arecord'] = $ip;
		$hostEntry['associateddomain'][] = $hostname . '.' . $domainname;
		$dn = 'dc=' . $ip . ',' . $wgOpenStackManagerLDAPInstanceBaseDN;

		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $dn, $hostEntry );
		if ( $success ) {
			$domain->updateSOA();
			$wgAuth->printDebug( "Successfully added public host $hostname", NONSENSITIVE );
			return new OpenStackNovaHost( false, null, $ip );
		} else {
			$wgAuth->printDebug( "Failed to add public host $hostname with dn = $dn", NONSENSITIVE );
			return null;
		}
	}

	/**
	 * @param $hostname
	 * @return bool
	 */
	static function validateHostname( $hostname ) {
		# Does not handle trailing dots, purposely
		return (bool)preg_match( "/^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*$/", $hostname );
	}

}

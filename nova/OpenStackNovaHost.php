<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaHost {

	/**
	 * @var string
	 */
	public $hostDN;

	/**
	 * @var mixed
	 */
	public $hostInfo;

	/**
	 * @var OpenStackNovaDomain
	 */
	public $domainCache;

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
	 * Get a public host by the host's ip. Returns
	 * null if the entry does not exist.
	 *
	 * @static
	 * @param  $ip
	 * @return OpenStackNovaHost
	 */
	static function getHostByPublicIP( $ip ) {
		global $wgAuth;

		$host = new OpenStackNovaPublicHost( $ip );
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
	 * @param  $region
	 * @return OpenStackNovaHost
	 */
	static function getHostByInstanceId( $instanceid, $region ) {
		$host = new OpenStackNovaPrivateHost( $instanceid, $region );
		if ( $host->hostInfo ) {
			return $host;
		} else {
			return null;
		}
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
			$wgAuth->printDebug( "Successfully deleted host $this->hostDN", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete host $this->hostDN", NONSENSITIVE );
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
	static function addHostFromInstance( $instance, $domain, $puppetinfo = array() ) {
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
		$region = $domain->getLocation();
		$fqdn = $instanceid . '.' . $domainname;
		$host = OpenStackNovaHost::getHostByInstanceId( $instanceid, $region );
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
		$projectName = $project->getProjectName();
		$hostEntry['associateddomain'][] = $instanceid . '.' . $projectName . '.' . $domainname;
		$hostEntry['associateddomain'][] = $hostname . '.' . $projectName . '.' . $domainname;
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
			$wgAuth->printDebug( "Successfully added host $fqdn", NONSENSITIVE );
			return OpenStackNovaHost::getHostByInstanceId( $instanceid, $region );
		} else {
			$wgAuth->printDebug( "Failed to add host $fqdn with dn of $dn", NONSENSITIVE );
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

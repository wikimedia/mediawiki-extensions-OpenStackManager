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
		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$associateddomains = $this->hostInfo[0]['associateddomain'];
			array_shift( $associateddomains );
			$index = array_search( $fqdn, $associateddomains );
			if ( $index === false ) {
				$ldap->printDebug( "Failed to find $fqdn in associateddomain list", NONSENSITIVE );
				return false;
			}
			unset( $associateddomains[$index] );
			$values = array();
			$values['associateddomain'] = array();
			foreach ( $associateddomains as $associateddomain ) {
				$values['associateddomain'][] = $associateddomain;
			}
			$success = LdapAuthenticationPlugin::ldap_modify( $ldap->ldapconn, $this->hostDN, $values );
			if ( $success ) {
				$ldap->printDebug( "Successfully removed $fqdn from $this->hostDN", NONSENSITIVE );
				$this->getDomain()->updateSOA();
				$this->fetchHostInfo();
				return true;
			} else {
				$ldap->printDebug( "Failed to remove $fqdn from $this->hostDN", NONSENSITIVE );
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
		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$arecords = $this->hostInfo[0]['arecord'];
			array_shift( $arecords );
			$index = array_search( $ip, $arecords );
			if ( $index === false ) {
				$ldap->printDebug( "Failed to find ip address in arecords list", NONSENSITIVE );
				return false;
			}
			unset( $arecords[$index] );
			$values = array();
			$values['arecord'] = array();
			foreach ( $arecords as $arecord ) {
				$values['arecord'][] = $arecord;
			}
			$success = LdapAuthenticationPlugin::ldap_modify( $ldap->ldapconn, $this->hostDN, $values );
			if ( $success ) {
				$ldap->printDebug( "Successfully removed $ip from $this->hostDN", NONSENSITIVE );
				$this->getDomain()->updateSOA();
				$this->fetchHostInfo();
				return true;
			} else {
				$ldap->printDebug( "Failed to remove $ip from $this->hostDN", NONSENSITIVE );
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
		$ldap = LdapAuthenticationPlugin::getInstance();
		$associatedomains = array();
		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$associatedomains = $this->hostInfo[0]['associateddomain'];
			array_shift( $associatedomains );
		}
		$associatedomains[] = $fqdn;
		$values = array();
		$values['associateddomain'] = $associatedomains;
		$success = LdapAuthenticationPlugin::ldap_modify( $ldap->ldapconn, $this->hostDN, $values );
		if ( $success ) {
			$ldap->printDebug( "Successfully added $fqdn to $this->hostDN", NONSENSITIVE );
			$this->getDomain()->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$ldap->printDebug( "Failed to add $fqdn to $this->hostDN", NONSENSITIVE );
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
		$ldap = LdapAuthenticationPlugin::getInstance();
		$arecords = array();
		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$arecords = $this->hostInfo[0]['arecord'];
			array_shift( $arecords );
		}
		$arecords[] = $ip;
		$values = array();
		$values['arecord'] = $arecords;
		$success = LdapAuthenticationPlugin::ldap_modify( $ldap->ldapconn, $this->hostDN, $values );
		if ( $success ) {
			$ldap->printDebug( "Successfully added $ip to $this->hostDN", NONSENSITIVE );
			$this->getDomain()->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$ldap->printDebug( "Failed to add $ip to $this->hostDN", NONSENSITIVE );
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
		$ldap = LdapAuthenticationPlugin::getInstance();
		$values = array( 'arecord' => array( $ip ) );
		$success = LdapAuthenticationPlugin::ldap_modify( $ldap->ldapconn, $this->hostDN, $values );
		if ( $success ) {
			$ldap->printDebug( "Successfully set $ip on $this->hostDN", NONSENSITIVE );
			$this->getDomain()->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$ldap->printDebug( "Failed to set $ip on $this->hostDN", NONSENSITIVE );
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
	 * @param  $instancename
	 * @param  $instanceproject
	 * @param  $region
	 * @return OpenStackNovaHost
	 */
	static function getHostByNameAndProject( $instancename, $instanceproject, $region ) {
		$host = new OpenStackNovaPrivateHost( $instancename, $instanceproject, $region );
		if ( $host->hostInfo ) {
			return $host;
		} else {
			return null;
		}
	}

	/**
	 * Delete this host
	 *
	 * @return bool
	 */
	function deleteHost() {
		$ldap = LdapAuthenticationPlugin::getInstance();

		# Grab the domain now, before we delete the entry and it's no longer there to grab.
		$domain = $this->getDomain();

		$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $this->hostDN );
		if ( $success ) {
			$domain->updateSOA();
			$ldap->printDebug( "Successfully deleted host $this->hostDN", NONSENSITIVE );
			return true;
		} else {
			$ldap->printDebug( "Failed to delete host $this->hostDN", NONSENSITIVE );
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
		global $wgOpenStackManagerLDAPInstanceBaseDN, $wgOpenStackManagerPuppetOptions;

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$hostname = $instance->getInstanceName();
		$instanceid = $instance->getInstanceId();
		$instancename = $instance->getInstanceName();
		$instanceproject = $instance->getProject();
		$project = $instance->getProject();
		$tmpip = $instance->getInstancePrivateIPs();
		if ( $tmpip && isset( $tmpip[0] ) ) {
			$ip = $tmpip[0];
		} else {
			$ip = null;
		}
		$domainname = $domain->getFullyQualifiedDomainName();
		$region = $domain->getLocation();
		$fqdn = $instancename . '.' . $instanceproject . '.' . $domainname;
		$host = OpenStackNovaHost::getHostByNameAndProject( $instancename, $instanceproject, $region );
		if ( $host ) {
			$ldap->printDebug( "Failed to add host $hostname as the DNS entry already exists", NONSENSITIVE );
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
		$hostEntry['associateddomain'][] = $instanceid . '.' . $project . '.' . $domainname;
		$hostEntry['associateddomain'][] = $hostname . '.' . $project . '.' . $domainname;
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

		$success = LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $dn, $hostEntry );
		if ( $success ) {
			$domain->updateSOA();
			$ldap->printDebug( "Successfully added host $fqdn", NONSENSITIVE );
			return OpenStackNovaHost::getHostByInstanceNameAndProject( $instancename, $instanceproject, $region );
		} else {
			$ldap->printDebug( "Failed to add host $fqdn with dn of $dn", NONSENSITIVE );
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
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$domainname = $domain->getFullyQualifiedDomainName();
		$host = OpenStackNovaHost::getHostByPublicIP( $ip );
		if ( $host ) {
			$ldap->printDebug( "Failed to add public host $hostname as the DNS entry already exists", NONSENSITIVE );
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

		$success = LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $dn, $hostEntry );
		if ( $success ) {
			$domain->updateSOA();
			$ldap->printDebug( "Successfully added public host $hostname", NONSENSITIVE );
			return new OpenStackNovaHost( false, null, $ip );
		} else {
			$ldap->printDebug( "Failed to add public host $hostname with dn = $dn", NONSENSITIVE );
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

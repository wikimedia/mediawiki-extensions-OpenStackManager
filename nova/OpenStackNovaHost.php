<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

abstract class OpenStackNovaHost {

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
	public function getARecords() {
		$arecords = [];
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
	public function getAssociatedDomains() {
		$associateddomain = [];
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
	public function getCNAMERecords() {
		$cnamerecords = [];
		if ( isset( $this->hostInfo[0]['cnamerecord'] ) ) {
			$cnamerecords = $this->hostInfo[0]['cnamearecord'];
			array_shift( $cnamerecords );
		}

		return $cnamerecords;
	}

	/**
	 * Remove an associated domain record from this entry.
	 *
	 * @param string $fqdn
	 * @return bool
	 */
	public function deleteAssociatedDomain( $fqdn ) {
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
			$values = [];
			$values['associateddomain'] = [];
			foreach ( $associateddomains as $associateddomain ) {
				$values['associateddomain'][] = $associateddomain;
			}
			$success = LdapAuthenticationPlugin::ldap_modify(
				$ldap->ldapconn, $this->hostDN, $values
			);
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
	 * @param string $ip
	 * @return bool
	 */
	public function deleteARecord( $ip ) {
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
			$values = [];
			$values['arecord'] = [];
			foreach ( $arecords as $arecord ) {
				$values['arecord'][] = $arecord;
			}
			$success = LdapAuthenticationPlugin::ldap_modify(
				$ldap->ldapconn, $this->hostDN, $values
			);
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
	 * @param string $fqdn
	 * @return bool
	 */
	public function addAssociatedDomain( $fqdn ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$associatedomains = [];
		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$associatedomains = $this->hostInfo[0]['associateddomain'];
			array_shift( $associatedomains );
		}
		$associatedomains[] = $fqdn;
		$values = [];
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
	 * @param string $ip
	 * @return bool
	 */
	public function addARecord( $ip ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$arecords = [];
		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$arecords = $this->hostInfo[0]['arecord'];
			array_shift( $arecords );
		}
		$arecords[] = $ip;
		$values = [];
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
	 * @param string $ip
	 * @return bool
	 */
	public function setARecord( $ip ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$values = [ 'arecord' => [ $ip ] ];
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
	 * @param string $ip
	 * @return OpenStackNovaHost
	 */
	public static function getHostByPublicIP( $ip ) {
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
	 * @param string $instancename
	 * @param string $instanceproject
	 * @param string $region
	 * @return OpenStackNovaHost
	 */
	public static function getHostByNameAndProject( $instancename, $instanceproject, $region ) {
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
	public function deleteHost() {
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
	 * Return the domain associated with this host
	 *
	 * @return OpenStackNovaDomain
	 */
	abstract public function getDomain();

	/**
	 * Fetch the host from LDAP and initialize the object
	 *
	 * @return void
	 */
	abstract public function fetchHostInfo();

	/**
	 * Adds a host entry based on the hostname, IP addrss, and a domain. Returns null
	 * if the entry already exists, or if the additional fails. This function should be used
	 * for adding public DNS entries.
	 *
	 * @param string $hostname
	 * @param string $ip
	 * @param OpenStackNovaDomain $domain
	 * @return bool|null|OpenStackNovaHost
	 */
	public static function addPublicHost( $hostname, $ip, $domain ) {
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$domainname = $domain->getFullyQualifiedDomainName();
		$host = self::getHostByPublicIP( $ip );
		if ( $host ) {
			$ldap->printDebug(
				"Failed to add public host $hostname as the DNS entry already exists", NONSENSITIVE
			);
			return null;
		}
		$hostEntry = [];
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
			return new OpenStackNovaPrivateHost( false, null, $ip );
		} else {
			$ldap->printDebug( "Failed to add public host $hostname with dn = $dn", NONSENSITIVE );
			return null;
		}
	}

	/**
	 * @param string $hostname
	 * @return bool
	 */
	public static function validateHostname( $hostname ) {
		# Does not handle trailing dots, purposely
		return (bool)preg_match( "/^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}" .
			"[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*$/", $hostname
		);
	}

}

<?php

class OpenStackNovaHost {

	var $hostname;
	var $hostDN;
	var $hostInfo;
	var $domain;

	function __construct( $hostname, $domain ) {
		$this->hostname = $hostname;
		$this->domain = $domain;
		$this->connect();
		$this->fetchHostInfo();
	}

	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}

	function fetchHostInfo() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$result = @ldap_search( $wgAuth->ldapconn, $this->domain->domainDN, '(dc=' . $this->hostname . '))' );
		$this->hostInfo = @ldap_get_entries( $wgAuth->ldapconn, $result );
		$this->hostDN = $this->hostInfo[0]['dn'];
	}

	function getHostName() {
		return $this->hostname;
	}

	function getARecords() {
		$arecords = array();
		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$arecords = $this->hostInfo[0]['arecord'];
			$arecords = array_shift( $arecords );
		}

		return $arecords;
	}

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
			$values['arecord'] = array();
			foreach ( $arecords as $arecord ) {
				$values['arecord'][] = $arecord;
			}
			$success = @ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
			if ( $success ) {
				$wgAuth->printDebug( "Successfully removed $ip from $this->hostDN", NONSENSITIVE );
				$this->domain->updateSOA();
				return true;
			} else {
				$wgAuth->printDebug( "Failed to remove $ip from $this->hostDN", NONSENSITIVE );
				return false;
			}
		} else {
			return false;
		}
	}

	function addARecord( $ip ) {
		global $wgAuth;

		$arecords = array();
		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$arecords = $this->hostInfo[0]['arecord'];
			array_shift( $arecords );
		}
		$arecords[] = $ip;
		$values['arecord'] = $arecords;
		$success = @ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added $ip to $this->hostDN", NONSENSITIVE );
			$this->domain->updateSOA();
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $ip to $this->hostDN", NONSENSITIVE );
			return false;
		}
	}

	static function getAllHosts( $domain ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$hosts = array();
		$result = @ldap_search( $wgAuth->ldapconn, $domain->domainDN, '(dc=*)' );
		if ( $result ) {
			$entries = @ldap_get_entries( $wgAuth->ldapconn, $result );
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$hosts[] = new OpenStackNovaHost( $entry['dc'][0], $domain );
				}
			}
		}

		return $hosts;
	}

	static function deleteHost( $hostname, $domain ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$host = new OpenStackNovaHost( $hostname, $domain );
		if ( ! $host ) {
			return false;
		}
		$dn = $host->hostDN;

		$success = @ldap_delete( $wgAuth->ldapconn, $dn );
		if ( $success ) {
			$domain->updateSOA();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @static
	 * @param  $hostname
	 * @param  $ip
	 * @param  $domain OpenStackNovaDomain
	 * @return bool
	 */
	static function addHost( $hostname, $ip, $domain ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDNSDomainBaseDN;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$domainname = $domain->getFullyQualifiedDomainName();

		$host = new OpenStackNovaHost( $hostname, $domain );
		if ( $host ) {
			return false;
		}
		$host = OpenStackNovaHost::getLDAPArray( $hostname, $ip, $domainname );
		$dn = 'dc=' . $hostname . ',dc=' . $domain->getDomainName() . ',' . $wgOpenStackManagerLDAPDNSDomainBaseDN;

		$success = @ldap_add( $wgAuth->ldapconn, $dn, $hostname );
		if ( $success ) {
			$domain->updateSOA();
			$wgAuth->printDebug( "Successfully added domain $domainname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add domain $domainname", NONSENSITIVE );
			return false;
		}
	}

	static function getLDAPArray( $hostname, $ip, $domain ) {
		$host['objectclass'][] = 'dcobject';
		$host['objectclass'][] = 'dnsdomain';
		$host['objectclass'][] = 'domainrelatedobject';
		$host['dc'] = $hostname;
		$host['arecord'] = $ip;
		$host['associateddomain'] = $hostname . '.' . $domain->getFullyQualifiedDomainName();

		return $host;
	}

}

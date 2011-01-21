<?php

class OpenStackNovaHost {

	var $searchvalue;
	var $hostDN;
	var $hostInfo;
	var $domain;

	function __construct( $hostname, $domain ) {
		$this->searchvalue = $hostname;
		$this->domain = $domain;
		$this->connect();
		$this->fetchHostInfo();
	}

	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}

	function fetchHostInfo() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$hostname = $this->searchvalue . '.' . $this->domain->getFullyQualifiedDomainName();
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $this->domain->domainDN, '(|(associateddomain=' . $hostname . ')(cnamerecord=' . $hostname . '))' );
		$this->hostInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		if ( $this->hostInfo["count"] == "0" ) {
			$this->hostInfo = null;
		} else {
			$this->hostDN = $this->hostInfo[0]['dn'];
		}
	}

	function getHostName() {
		return $this->hostInfo[0]['dc'][0];
	}

	function getDomain() {
		return $this->domain;
	}

	function getFullyQualifiedHostName() {
		return $this->getHostName() . '.' . $this->domain->getFullyQualifiedDomainName();
	}

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
				$puppetinfo['puppetvar']["$varname"] = $var;
			}
		}
		return $puppetinfo;
	}

	function getARecords() {
		$arecords = array();
		if ( isset( $this->hostInfo[0]['arecord'] ) ) {
			$arecords = $this->hostInfo[0]['arecord'];
			array_shift( $arecords );
		}

		return $arecords;
	}

	function getAssociatedDomains() {
		$associateddomain = array();
		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$associateddomain = $this->hostInfo[0]['associateddomain'];
			array_shift( $associateddomain );
		}

		return $associateddomain;
	}

	function getCNAMERecords() {
		$cnamerecords = array();
		if ( isset( $this->hostInfo[0]['cnamerecord'] ) ) {
			$cnamerecords = $this->hostInfo[0]['cnamearecord'];
			array_shift( $cnamerecords );
		}

		return $cnamerecords;
	}

	function modifyPuppetConfiguration( $puppetinfo ) {
		global $wgAuth;
		global $wgOpenStackManagerPuppetOptions;

		$hostEntry = array();
		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			foreach ( $wgOpenStackManagerPuppetOptions['defaultclasses'] as $class ) {
				$hostEntry['puppetclass'][] = $class;
			}
			foreach ( $wgOpenStackManagerPuppetOptions['defaultvariables'] as $variable => $value ) {
				$hostEntry['puppetvar'][] = $variable . ' = ' . $value;
			}
			foreach ( $puppetinfo['classes'] as $class ) {
				$hostEntry['puppetclass'][] = $class;
			}
			foreach ( $puppetinfo['variables'] as $variable => $value ) {
				$hostEntry['puppetvar'][] = $variable . ' = ' . $value;
			}
			if ( $hostEntry ) {
				wfSuppressWarnings();
				$success = ldap_modify( $wgAuth->ldapconn, $this->hostDN, $hostEntry );
				wfRestoreWarnings();
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
	}

	function deleteAssociatedDomain( $fqdn ) {
		global $wgAuth;

		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$associateddomains = $this->hostInfo[0]['associateddomain'];
			array_shift( $associateddomains );
			$index = array_search( $fqdn, $associateddomains );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find ip address in arecords list", NONSENSITIVE );
				return false;
			}
			unset( $associateddomains[$index] );
			$values['associateddomain'] = array();
			foreach ( $associateddomains as $associateddomain ) {
				$values['associateddomain'][] = $associateddomain;
			}
			wfSuppressWarnings();
			$success = ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
			wfRestoreWarnings();
			if ( $success ) {
				$wgAuth->printDebug( "Successfully removed $fqdn from $this->hostDN", NONSENSITIVE );
				$this->domain->updateSOA();
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
			wfSuppressWarnings();
			$success = ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
			wfRestoreWarnings();
			if ( $success ) {
				$wgAuth->printDebug( "Successfully removed $ip from $this->hostDN", NONSENSITIVE );
				$this->domain->updateSOA();
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

	function addAssociatedDomain( $fqdn ) {
		global $wgAuth;

		$associatedomains = array();
		if ( isset( $this->hostInfo[0]['associateddomain'] ) ) {
			$associatedomains = $this->hostInfo[0]['associateddomain'];
			array_shift( $associatedomains );
		}
		$associatedomains[] = $fqdn;
		$values['associateddomain'] = $associatedomains;
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added $fqdn to $this->hostDN", NONSENSITIVE );
			$this->domain->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $fqdn to $this->hostDN", NONSENSITIVE );
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
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added $ip to $this->hostDN", NONSENSITIVE );
			$this->domain->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add $ip to $this->hostDN", NONSENSITIVE );
			return false;
		}
	}

	function setARecord( $ip ) {
		global $wgAuth;

		$values = array( 'arecord' => array( $ip ) );
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->hostDN, $values );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully set $ip on $this->hostDN", NONSENSITIVE );
			$this->domain->updateSOA();
			$this->fetchHostInfo();
			return true;
		} else {
			$wgAuth->printDebug( "Failed to set $ip on $this->hostDN", NONSENSITIVE );
			return false;
		}
	}

	static function getHostByName( $hostname, $domain ) {
		$host = new OpenStackNovaHost( $hostname, $domain );
		if ( $host->hostInfo ) {
			return $host;
		} else {
			return null;
		}
	}

	static function getHostByInstanceId( $instanceid ) {
		$domain = OpenStackNovaDomain::getDomainByInstanceId( $instanceid );
		return self::getHostByName( $instanceid, $domain );
	}

	static function getHostByIP( $ip, $domain ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$domain = OpenStackNovaDomain::getDomainByHostIP( $ip );
		if ( ! $domain ) {
			return array();
		}
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(arecord=' . $ip . ')' );
		$hostInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		if ( $hostInfo["count"] == "0" ) {
			return array();
		} else {
			array_shift( $hostsInfo );
			$hostname = $hostInfo[0]['dc'][0];
			$host = OpenStackNovaHost::getHostByName( $hostname, $domain );
			return $host;
		}
	}

	static function getHostsByIP( $ip ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(arecord=' . $ip . ')' );
		$hostsInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		if ( $hostsInfo["count"] == "0" ) {
			return array();
		} else {
			$hosts = array();
			array_shift( $hostsInfo );
			foreach ( $hostsInfo as $host ) {
				$hostname = $host['dc'][0];
				$domainname = explode( '.', $host['associateddomain'][0] );
				$domainname = $domainname[1];
				$domain = OpenStackNovaDomain::getDomainByName( $domainname );
				$hosts[] = OpenStackNovaHost::getHostByName( $hostname, $domain );
			}
			return $hosts;
		}
	}

	static function getAllHosts( $domain ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$hosts = array();
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $domain->domainDN, '(dc=*)' );
		wfRestoreWarnings();
		if ( $result ) {
			wfSuppressWarnings();
			$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
			wfRestoreWarnings();
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
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$host = OpenStackNovaHost::getHostByName( $hostname, $domain );
		if ( ! $host ) {
			$wgAuth->printDebug( "Failed to delete host $hostname as the DNS entry does not exist", NONSENSITIVE );
			return false;
		}
		$dn = $host->hostDN;

		wfSuppressWarnings();
		$success = ldap_delete( $wgAuth->ldapconn, $dn );
		wfRestoreWarnings();
		if ( $success ) {
			$domain->updateSOA();
			$wgAuth->printDebug( "Successfully deleted host $hostname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete host $hostname", NONSENSITIVE );
			return false;
		}
	}

	static function deleteHostByInstanceId( $instanceid ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$host = OpenStackNovaHost::getHostByInstanceId( $instanceid );
		if ( ! $host ) {
			$wgAuth->printDebug( "Failed to delete host $instanceid as the DNS entry does not exist", NONSENSITIVE );
			return false;
		}
		$dn = $host->hostDN;
		$domain = $host->getDomain();

		wfSuppressWarnings();
		$success = ldap_delete( $wgAuth->ldapconn, $dn );
		wfRestoreWarnings();
		if ( $success ) {
			$domain->updateSOA();
			$wgAuth->printDebug( "Successfully deleted host $instanceid", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete host $instanceid", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * @static
	 * @param  $hostname
	 * @param  $ip
	 * @param  $domain OpenStackNovaDomain
	 * @param  $puppetinfo
	 * @return bool
	 */
	static function addHost( $instance, $domain, $puppetinfo = array() ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN, $wgOpenStackManagerPuppetOptions;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$hostname = $instance->getInstanceName();
		$instanceid = $instance->getInstanceId();
		$ip = $instance->getInstancePrivateIP();
		$domainname = $domain->getFullyQualifiedDomainName();
		$host = OpenStackNovaHost::getHostByName( $hostname, $domain );
		if ( $host ) {
			$wgAuth->printDebug( "Failed to add host $hostname as the DNS entry already exists", NONSENSITIVE );
			return false;
		}
		$hostEntry['objectclass'][] = 'dcobject';
		$hostEntry['objectclass'][] = 'dnsdomain';
		$hostEntry['objectclass'][] = 'domainrelatedobject';
		$hostEntry['dc'] = $hostname;
		# $hostEntry['l'] = $instance->getInstanceAvailabilityZone();
		$hostEntry['arecord'] = $ip;
		$hostEntry['associateddomain'][] = $hostname . '.' . $domainname;
		$hostEntry['cnamerecord'][] = $instanceid . '.' . $domainname;
		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			$hostEntry['objectclass'][] = 'puppetclient';
			foreach ( $wgOpenStackManagerPuppetOptions['defaultclasses'] as $class ) {
				$hostEntry['puppetclass'][] = $class;
			}
			foreach ( $wgOpenStackManagerPuppetOptions['defaultvariables'] as $variable => $value ) {
				$hostEntry['puppetvar'][] = $variable . ' = ' . $value;
			}
			if ( $puppetinfo ) {
				foreach ( $puppetinfo['classes'] as $class ) {
					$hostEntry['puppetclass'][] = $class;
				}
				foreach ( $puppetinfo['variables'] as $variable => $value ) {
					if ( $value ) {
						$hostEntry['puppetvar'][] = $variable . ' = ' . $value;
					}
				}
			}
		}
		$dn = 'dc=' . $hostname . ',dc=' . $domain->getDomainName() . ',' . $wgOpenStackManagerLDAPInstanceBaseDN;

		wfSuppressWarnings();
		$success = ldap_add( $wgAuth->ldapconn, $dn, $hostEntry );
		wfRestoreWarnings();
		if ( $success ) {
			$domain->updateSOA();
			$wgAuth->printDebug( "Successfully added host $hostname", NONSENSITIVE );
			return new OpenStackNovaHost( $hostname, $domain );
		} else {
			$wgAuth->printDebug( "Failed to add host $hostname", NONSENSITIVE );
			return null;
		}
	}

	static function addPublicHost( $hostname, $ip, $domain ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$domainname = $domain->getFullyQualifiedDomainName();
		$host = OpenStackNovaHost::getHostByName( $hostname, $domain );
		if ( $host ) {
			$wgAuth->printDebug( "Failed to add public host $hostname as the DNS entry already exists", NONSENSITIVE );
			return false;
		}
		$hostEntry['objectclass'][] = 'dcobject';
		$hostEntry['objectclass'][] = 'dnsdomain';
		$hostEntry['objectclass'][] = 'domainrelatedobject';
		$hostEntry['dc'] = $hostname;
		$hostEntry['arecord'] = $ip;
		$hostEntry['associateddomain'][] = $hostname . '.' . $domainname;
		$dn = 'dc=' . $hostname . ',dc=' . $domain->getDomainName() . ',' . $wgOpenStackManagerLDAPInstanceBaseDN;

		wfSuppressWarnings();
		$success = ldap_add( $wgAuth->ldapconn, $dn, $hostEntry );
		wfRestoreWarnings();
		if ( $success ) {
			$domain->updateSOA();
			$wgAuth->printDebug( "Successfully added public host $hostname", NONSENSITIVE );
			return new OpenStackNovaHost( $hostname, $domain );
		} else {
			$wgAuth->printDebug( "Failed to add public host $hostname", NONSENSITIVE );
			return null;
		}
	}

}

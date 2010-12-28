<?php

class OpenStackNovaDomain {

	var $domainname;
	var $domainDN;
	var $domainInfo;
	var $fqdn;

	function __construct( $domainname ) {
		$this->domainname = $domainname;
		$this->connect();
		$this->fetchDomainInfo();
	}

	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}

	function fetchDomainInfo() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPInstanceBaseDN;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		# TODO: memcache this
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN,
								'(dc=' . $this->domainname . ')' );
		$this->domainInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		$this->fqdn = $this->domainInfo[0]['associateddomain'][0];
		$this->domainDN = $this->domainInfo[0]['dn'];
	}

	function getDomainName() {
		return $this->domainname;
	}

	function getFullyQualifiedDomainName() {
		return $this->fqdn;
	}

	function updateSOA() {
		global $wgAuth;

		$domain['soarecord'] = OpenStackNovaDomain::generateSOA();
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->domainDN, $domain );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully modified soarecord for " . $this->domainDN, NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to modify soarecord for " . $this->domainDN, NONSENSITIVE );
			return false;
		}
	}

	static function getAllDomains() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$domains = array();
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, '(soarecord=*)' );
		wfRestoreWarnings();
		if ( $result ) {
			wfSuppressWarnings();
			$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
			wfRestoreWarnings();
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$domain = new OpenStackNovaDomain( $entry['dc'][0] );
					array_push( $domains, $domain );
				}
			}
		}

		return $domains;
	}

	static function getDomainByName( $domainname ) {
		$domain = new OpenStackNovaDomain( $domainname );
		if ( $domain->domainInfo ) {
			return $domain;
		} else {
			return null;
		}
	}

	static function getDomainByHostIP( $ip ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN,
								'(arecord=' . $ip . ')' );
		$hostInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		$fqdn = $hostInfo[0]['associateddomain'][0];
		$domainname = explode( '.', $fqdn );
		$domainname = $domainname[1];
		$domain = new OpenStackNovaDomain( $domainname );
		if ( $domain->domainInfo ) {
			return $domain;
		} else {
			return null;
		}
	}

	static function getDomainByInstanceId( $instanceid ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN,
								'(cnamerecord=' . $instanceid . '.*)' );
		$hostInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		$fqdn = $hostInfo[0]['associateddomain'][0];
		$domainname = explode( '.', $fqdn );
		$domainname = $domainname[1];
		$domain = new OpenStackNovaDomain( $domainname );
		if ( $domain->domainInfo ) {
			return $domain;
		} else {
			return null;
		}
	}

	# TODO: Allow generic domains; get rid of config set base name
	static function createDomain( $domainname, $fqdn ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;
		global $wgOpenStackManagerDNSServers;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$soa = OpenStackNovaDomain::generateSOA();
		$domain['objectclass'][] = 'dcobject';
		$domain['objectclass'][] = 'dnsdomain';
		$domain['objectclass'][] = 'domainrelatedobject';
		$domain['dc'] = $domainname;
		$domain['soarecord'] = $wgOpenStackManagerDNSServers['primary'] . ' ' . $soa;
		$domain['associateddomain'] = $fqdn;
		$dn = 'dc=' . $domainname . ',' . $wgOpenStackManagerLDAPInstanceBaseDN;

		wfSuppressWarnings();
		$success = ldap_add( $wgAuth->ldapconn, $dn, $domain );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added domain $domainname", NONSENSITIVE );
			return new OpenStackNovaDomain( $domainname );
		} else {
			$wgAuth->printDebug( "Failed to add domain $domainname", NONSENSITIVE );
			return null;
		}
	}

	static function deleteDomain( $domainname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$domain = new OpenStackNovaDomain( $domainname );
		if ( ! $domain ) {
			$wgAuth->printDebug( "Domain $domainname does not exist", NONSENSITIVE );
			return false;
		}
		$dn = $domain->domainDN;

		# Domains can have records as sub entries. If sub-entries exist, fail.
		$result = ldap_list( $wgAuth->ldapconn, $dn, 'objectclass=*' );
		$hosts = ldap_get_entries( $wgAuth->ldapconn, $result );
		if ( $hosts['count'] != "0" ) {
			$wgAuth->printDebug( "Failed to delete domain $domainname, since it had sub entries", NONSENSITIVE );
			return false;
		}
		wfSuppressWarnings();
		$success = ldap_delete( $wgAuth->ldapconn, $dn );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted domain $domainname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete domain $domainname, since it had sub entries", NONSENSITIVE );
			return false;
		}
	}

	static function generateSOA() {
		global $wgOpenStackManagerDNSSOA;

		$serial = date( 'YmdHis' );
		$soa = $wgOpenStackManagerDNSSOA['hostmaster'] . ' ' . $serial . ' ' .
			   $wgOpenStackManagerDNSSOA['refresh'] . ' ' . $wgOpenStackManagerDNSSOA['retry'] . ' ' .
			   $wgOpenStackManagerDNSSOA['expiry'] . ' ' . $wgOpenStackManagerDNSSOA['minimum'];

		return $soa;
	}

}

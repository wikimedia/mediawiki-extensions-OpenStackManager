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
		global $wgOpenStackManagerLDAPDNSDomainBaseDN;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$result = @ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPDNSDomainBaseDN, '(dc=' . $this->domainname . ')' );
		$this->domainInfo = @ldap_get_entries( $wgAuth->ldapconn, $result );
		$this->fqdn = $this->domainInfo[0]['associateddomain'][0];
		$this->domainDN = $this->domainInfo[0]['dn'];
	}

	function getDomainName() {
		return $this->domainname;
	}

	function getFullyQualifiedDomainName() {
		return $this->fqdn;
	}

	function getHosts() {
		global $wgAuth;

		# Domains can have records as sub entries. If sub-entries exist, fail.
		$result = ldap_list( $wgAuth->ldapconn, $this->domainDN, 'objectclass=*' );
		$hostsLDAP = ldap_get_entries( $wgAuth->ldapconn, $result );
		array_shift( $hostsLDAP );
		foreach ( $hostsLDAP as $hostLDAP ) {
			$hosts[] = new OpenStackNovaHost( $hostLDAP[0]['dc'] );
		}

		return $hosts;
	}

	function updateSOA() {
		global $wgAuth;

		$domain['soarecord'] = $OpenStackNovaDomain::generateSOA();
		$success = @ldap_modify( $wgAuth->ldapconn, $this->domainDN, $domain );
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
		global $wgOpenStackManagerLDAPDNSDomainBaseDN;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$domains = array();
		$result = @ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPDNSDomainBaseDN, '(soarecord=*)' );
		if ( $result ) {
			$entries = @ldap_get_entries( $wgAuth->ldapconn, $result );
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

	# TODO: Allow generic domains; get rid of config set base name
	static function createDomain( $domainname, $fqdn ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDNSDomainBaseDN, $wgOpenStackManagerLDAPDNSDomainBaseName;
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
		$dn = 'dc=' . $domainname . ',' . $wgOpenStackManagerLDAPDNSDomainBaseDN;

		$success = @ldap_add( $wgAuth->ldapconn, $dn, $domain );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added domain $domainname", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to add domain $domainname", NONSENSITIVE );
			return false;
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
		$success = @ldap_delete( $wgAuth->ldapconn, $dn );
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
		$soa = $wgOpenStackManagerDNSSOA['hostmaster'] . ' ' . $serial . ' ' . $wgOpenStackManagerDNSSOA['refresh'] . ' ' . $wgOpenStackManagerDNSSOA['retry'] . ' 
' . $wgOpenStackManagerDNSSOA['expiry'] . ' ' . $wgOpenStackManagerDNSSOA['minimum'];

		return $soa;
	}

}

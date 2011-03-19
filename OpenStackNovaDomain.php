<?php

class OpenStackNovaDomain {

	var $domainname;
	var $domainDN;
	var $domainInfo;
	var $fqdn;

	/**
	 * @param  $domainname
	 */
	function __construct( $domainname ) {
		$this->domainname = $domainname;
		$this->connect();
		$this->fetchDomainInfo();
	}

	/**
	 * Connect to LDAP as the open stack manager account using wgAuth
	 *
	 * @return void
	 */
	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}

	/**
	 * Fetch the domain from LDAP and initialize the object
	 *
	 * @return void
	 */
	function fetchDomainInfo() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		# TODO: memcache this
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN,
								'(dc=' . $this->domainname . ')' );
		$this->domainInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		if ( $this->domainInfo ) {
			$this->fqdn = $this->domainInfo[0]['associateddomain'][0];
			$this->domainDN = $this->domainInfo[0]['dn'];
		}
	}

	/**
	 * Return the short domainname
	 *
	 * @return string
	 */
	function getDomainName() {
		return $this->domainname;
	}

	/**
	 * Return the fully qualified domain name
	 *
	 * @return string
	 */
	function getFullyQualifiedDomainName() {
		return $this->fqdn;
	}

	/**
	 * Return the location associated with this domain; if this domain
	 * is a public domain, return an empty string
	 *
	 * @return string
	 */
	function getLocation() {
		if ( isset( $this->domainInfo[0]['l'] ) ) {
			return $this->domainInfo[0]['l'][0];
		} else {
			return '';
		}
	}

	/**
	 * Update the Start of Authority record. This should be called on
	 * the change of any object in the domain
	 *
	 * @return bool
	 */
	function updateSOA() {
		global $wgAuth;

		$domain = array();
		$domain['soarecord'] = OpenStackNovaDomain::generateSOA();
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->domainDN, $domain );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully modified soarecord for " . $this->domainDN, NONSENSITIVE );
			$this->fetchDomainInfo();
			return true;
		} else {
			$wgAuth->printDebug( "Failed to modify soarecord for " . $this->domainDN, NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Get all domains of a specific type. Valid types are local, public, and all.
	 *
	 * @static
	 * @param string $type
	 * @return array of OpenNovaDomain
	 */
	static function getAllDomains( $type='all' ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$domains = array();
		if ( $type == 'local' ) {
			$query = '(&(soarecord=*)(l=*))';
		} else if ( $type == 'public' ) {
			$query = '(&(soarecord=*)(!(l=*)))';
		} else {
			$query = '(soarecord=*)';
		}
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, $query );
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

	/**
	 * Get a domain object by short name. If the domain is not found
	 * return null.
	 *
	 * @static
	 * @param  $domainname
	 * @return null|OpenStackNovaDomain
	 */
	static function getDomainByName( $domainname ) {
		$domain = new OpenStackNovaDomain( $domainname );
		if ( $domain->domainInfo ) {
			return $domain;
		} else {
			return null;
		}
	}

	/**
	 * Find a domain by a host entry's IP address. If the host entry doesn't
	 * exist, return null.
	 *
	 * @static
	 * @param  $ip
	 * @return null|OpenStackNovaDomain
	 */
	static function getDomainByHostIP( $ip ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN,
								'(arecord=' . $ip . ')' );
		$hostInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		if ( $hostInfo['count'] == "0" ) {
			return null;
		}
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

	/**
	 * Get a domain by an instance's ID. Return null if the instance ID entry
	 * does not exist.
	 *
	 * @static
	 * @param  $instanceid
	 * @return null|OpenStackNovaDomain
	 */
	static function getDomainByInstanceId( $instanceid ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN,
								'(associateddomain=' . $instanceid . '.*)' );
		$hostInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		if ( $hostInfo['count'] == "0" ) {
			return null;
		}
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
	/**
	 * Create a new domain based on shortname, fully qualified domain name
	 * and location. If location is an empty string, the domain created will
	 * be a public domain, otherwise it will be a private domain for instance
	 * creation. Returns null on domain creation failure.
	 *
	 * @static
	 * @param  $domainname
	 * @param  $fqdn
	 * @param  $location
	 * @return null|OpenStackNovaDomain
	 */
	static function createDomain( $domainname, $fqdn, $location ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPInstanceBaseDN;
		global $wgOpenStackManagerDNSOptions;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$soa = OpenStackNovaDomain::generateSOA();
		$domain = array();
		$domain['objectclass'][] = 'dcobject';
		$domain['objectclass'][] = 'dnsdomain';
		$domain['objectclass'][] = 'domainrelatedobject';
		$domain['dc'] = $domainname;
		$domain['soarecord'] = $wgOpenStackManagerDNSOptions['servers']['primary'] . ' ' . $soa;
		$domain['associateddomain'] = $fqdn;
		if ( $location ) {
			$domain['l'] = $location;
		}
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

	/**
	 * Deletes a domain based on the domain's short name. Will fail to
	 * delete the domain if any host entries still exist in the domain.
	 *
	 * @static
	 * @param  $domainname
	 * @return bool
	 */
	static function deleteDomain( $domainname ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
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

	/**
	 * Generates a valid SOA string based on configuration and current time.
	 *
	 * @static
	 * @return string
	 */
	static function generateSOA() {
		global $wgOpenStackManagerDNSOptions;

		$serial = date( 'YmdHis' );
		$soa = $wgOpenStackManagerDNSOptions['soa']['hostmaster'] . ' ' . $serial . ' ' .
			   $wgOpenStackManagerDNSOptions['soa']['refresh'] . ' ' .
			   $wgOpenStackManagerDNSOptions['soa']['retry'] . ' ' .
			   $wgOpenStackManagerDNSOptions['soa']['expiry'] . ' ' .
			   $wgOpenStackManagerDNSOptions['soa']['minimum'];

		return $soa;
	}

}

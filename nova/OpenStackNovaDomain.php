<?php

/**
 * Class for Nova Domain
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaDomain {

	public $domainname;
	public $domainDN;
	public $domainInfo;
	public $fqdn; // fq domain name

	/**
	 * @param  $domainname
	 */
	function __construct( $domainname ) {
		$this->domainname = $domainname;
		OpenStackNovaLdapConnection::connect();
		$this->fetchDomainInfo();
	}

	/**
	 * Fetch the domain from LDAP and initialize the object
	 *
	 * @return void
	 */
	function fetchDomainInfo() {
		global $wgMemc;
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$key = wfMemcKey( 'openstackmanager', 'domaininfo', $this->domainname );

		$domainInfo = $wgMemc->get( $key );

		if ( is_array( $domainInfo ) ) {
			$this->domainInfo = $domainInfo;
		} else {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$result = LdapAuthenticationPlugin::ldap_search( $ldap->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN,
									'(dc=' . $this->domainname . ')' );
			$this->domainInfo = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
			$wgMemc->set( $key, $this->domainInfo, 3600 * 24 );
		}
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
		$ldap = LdapAuthenticationPlugin::getInstance();
		$domain = array();
		$domain['soarecord'] = OpenStackNovaDomain::generateSOA();
		$success = LdapAuthenticationPlugin::ldap_modify( $ldap->ldapconn, $this->domainDN, $domain );
		if ( $success ) {
			$ldap->printDebug( "Successfully modified soarecord for " . $this->domainDN, NONSENSITIVE );
			$this->fetchDomainInfo();
			return true;
		} else {
			$ldap->printDebug( "Failed to modify soarecord for " . $this->domainDN, NONSENSITIVE );
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
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$domains = array();
		if ( $type === 'local' ) {
			$query = '(&(soarecord=*)(l=*))';
		} elseif ( $type === 'public' ) {
			$query = '(&(soarecord=*)(!(l=*)))';
		} else {
			$query = '(soarecord=*)';
		}
		$result = LdapAuthenticationPlugin::ldap_search( $ldap->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN, $query );
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$domain = new OpenStackNovaDomain( $entry['dc'][0] );
					$domains[] = $domain;
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
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$result = LdapAuthenticationPlugin::ldap_search( $ldap->ldapconn, $wgOpenStackManagerLDAPInstanceBaseDN,
								'(arecord=' . $ip . ')' );
		$hostInfo = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
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
	 * Get a domain by a region. Return null if the region
	 * does not exist.
	 *
	 * @static
	 * @param  $instanceid
	 * @return null|OpenStackNovaDomain
	 */
	static function getDomainByRegion( $region ) {
		$domain = OpenStackNovaDomain::getDomainByName( $region );
		if ( $domain ) {
			if ( $domain->getLocation() ) {
				return $domain;
			}
		}
		return null;
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
		global $wgOpenStackManagerLDAPInstanceBaseDN;

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$soa = OpenStackNovaDomain::generateSOA();
		$domain = array();
		$domain['objectclass'][] = 'dcobject';
		$domain['objectclass'][] = 'dnsdomain';
		$domain['objectclass'][] = 'domainrelatedobject';
		$domain['dc'] = $domainname;
		$domain['soarecord'] = $soa;
		$domain['associateddomain'] = $fqdn;
		if ( $location ) {
			$domain['l'] = $location;
		}
		$dn = 'dc=' . $domainname . ',' . $wgOpenStackManagerLDAPInstanceBaseDN;

		$success = LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $dn, $domain );
		if ( $success ) {
			$ldap->printDebug( "Successfully added domain $domainname", NONSENSITIVE );
			return new OpenStackNovaDomain( $domainname );
		} else {
			$ldap->printDebug( "Failed to add domain $domainname", NONSENSITIVE );
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
		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$domain = new OpenStackNovaDomain( $domainname );
		if ( ! $domain ) {
			$ldap->printDebug( "Domain $domainname does not exist", NONSENSITIVE );
			return array( false, 'openstackmanager-failedeletedomainnotfound' );
		}
		$dn = $domain->domainDN;

		# Domains can have records as sub entries. If sub-entries exist, fail.
		$result = LdapAuthenticationPlugin::ldap_list( $ldap->ldapconn, $dn, 'objectclass=*' );
		$hosts = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
		if ( $hosts['count'] != "0" ) {
			$ldap->printDebug( "Failed to delete domain $domainname, since it had sub entries", NONSENSITIVE );
			return array( false, 'openstackmanager-failedeletedomainduplicates' );
		}

		$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $dn );
		if ( $success ) {
			$ldap->printDebug( "Successfully deleted domain $domainname", NONSENSITIVE );
			return array( true, '' );
		} else {
			$ldap->printDebug( "Failed to delete domain $domainname, since it had sub entries", NONSENSITIVE );
			return array( false, 'openstackmanager-failedeletedomain' );
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

		$serial = date( 'U' );
		$soa = $wgOpenStackManagerDNSOptions['servers']['primary'] . '. ' .
			   $wgOpenStackManagerDNSOptions['soa']['hostmaster'] . '. ' . $serial . ' ' .
			   $wgOpenStackManagerDNSOptions['soa']['refresh'] . ' ' .
			   $wgOpenStackManagerDNSOptions['soa']['retry'] . ' ' .
			   $wgOpenStackManagerDNSOptions['soa']['expiry'] . ' ' .
			   $wgOpenStackManagerDNSOptions['soa']['minimum'];

		return $soa;
	}

}

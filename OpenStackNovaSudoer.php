<?php

class OpenStackNovaSudoer {

	var $sudoername;
	var $sudoerDN;
	var $sudoerInfo;

	/**
	 * @param  $sudoername
	 */
	function __construct( $sudoername ) {
		$this->sudoername = $sudoername;
		$this->connect();
		$this->fetchSudoerInfo();
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
	 * Fetch the sudoer policy from LDAP and initialize the object
	 *
	 * @return void
	 */
	function fetchSudoerInfo() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPSudoerBaseDN;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		# TODO: memcache this
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPSudoerBaseDN,
								'(cn=' . $this->sudoername . ')' );
		$this->sudoerInfo = ldap_get_entries( $wgAuth->ldapconn, $result );
		wfRestoreWarnings();
		if ( $this->sudoerInfo ) {
			$this->sudoerDN = $this->sudoerInfo[0]['dn'];
		}
	}

	/**
	 * Return the sudo policy name
	 *
	 * @return string
	 */
	function getSudoerName() {
		return $this->sudoername;
	}

	/**
	 * Return the policy users
	 *
	 * @return array
	 */
	function getSudoerUsers() {
		if ( isset( $this->sudoerInfo[0]['sudouser'] ) ) {
			$users = $this->sudoerInfo[0]['sudouser'];
			array_shift( $users );
			return $users;
		} else {
			return array();
		}
	}

	/**
	 * Return the policy hosts
	 *
	 * @return array
	 */
	function getSudoerHosts() {
		if ( isset( $this->sudoerInfo[0]['sudohost'] ) ) {
			$hosts = $this->sudoerInfo[0]['sudohost'];
			array_shift( $hosts );
			return $hosts;
		} else {
			return array();
		}
	}

	/**
	 * Return the policy commands
	 *
	 * @return array
	 */
	function getSudoerCommands() {
		if ( isset( $this->sudoerInfo[0]['sudocommand'] ) ) {
			$commands = $this->sudoerInfo[0]['sudocommand'];
			array_shift( $commands );
			return $commands;
		} else {
			return '';
		}
	}

	/**
	 * Return the policy options
	 *
	 * @return array
	 */
	function getSudoerOptions() {
		if ( isset( $this->sudoerInfo[0]['sudooption'] ) ) {
			$options = $this->sudoerInfo[0]['sudooption'];
			array_shift( $options );
			return $options;
		} else {
			return array();
		}
	}

	/**
	 * Modify a new sudoer based on users, hosts, commands, and options.
	 *
	 * @param  $users
	 * @param  $hosts
	 * @param  $commands
	 * @param  $options
	 * @return boolean
	 */
	function modifySudoer( $users, $hosts, $commands, $options ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPSudoerBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$sudoer = array();
		foreach ( $users as $user ) {
			$sudoer['sudouser'][] = $user;
		}
		foreach ( $hosts as $host ) {
			$sudoer['sudohost'][] = $host;
		}
		foreach ( $commands as $command ) {
			$sudoer['sudocommand'][] = $command;
		}
		foreach ( $options as $option ) {
			$sudoer['sudooption'][] = $option;
		}

		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->sudoerDN, $sudoer );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully modified sudoer $sudoername", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to modified sudoer $sudoername", NONSENSITIVE );
			return false;
		}
	}

	/**
	 * Get all sudo policies
	 *
	 * @static
	 * @return array of OpenStackNovaSudoer
	 */
	static function getAllSudoers() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPSudoerBaseDN;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$sudoers = array();
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPSudoerBaseDN, '(&(cn=*)(objectclass=sudorole))' );
		wfRestoreWarnings();
		if ( $result ) {
			wfSuppressWarnings();
			$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
			wfRestoreWarnings();
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$sudoer = new OpenStackNovaSudoer( $entry['cn'][0] );
					array_push( $sudoers, $sudoer );
				}
			}
		}

		return $sudoers;
	}

	/**
	 * Get a sudoer policy by name.
	 *
	 * @static
	 * @param  $sudoername
	 * @return null|OpenStackNovaSudoer
	 */
	static function getSudoerByName( $sudoername ) {
		$sudoer = new OpenStackNovaSudoer( $sudoername );
		if ( $sudoer->sudoerInfo ) {
			return $sudoer;
		} else {
			return null;
		}
	}

	/**
	 * Create a new sudoer based on name, users, hosts, commands, and options.
	 * Returns null on sudoer creation failure.
	 *
	 * @static
	 * @param  $sudoername
	 * @param  $users
	 * @param  $hosts
	 * @param  $commands
	 * @param  $options
	 * @return null|OpenStackNovaSudoer
	 */
	static function createSudoer( $sudoername, $users, $hosts, $commands, $options ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPSudoerBaseDN;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$sudoer = array();
		$sudoer['objectclass'][] = 'sudorole';
		foreach ( $users as $user ) {
			$sudoer['sudouser'][] = $user;
		}
		foreach ( $hosts as $host ) {
			$sudoer['sudohost'][] = $host;
		}
		foreach ( $commands as $command ) {
			$sudoer['sudocommand'][] = $command;
		}
		foreach ( $options as $option ) {
			$sudoer['sudooption'][] = $option;
		}
		$sudoer['cn'] = $sudoername;
		$dn = 'cn=' . $sudoername . ',' . $wgOpenStackManagerLDAPSudoerBaseDN;

		wfSuppressWarnings();
		$success = ldap_add( $wgAuth->ldapconn, $dn, $sudoer );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added sudoer $sudoername", NONSENSITIVE );
			return new OpenStackNovaSudoer( $sudoername );
		} else {
			$wgAuth->printDebug( "Failed to add sudoer $sudoername", NONSENSITIVE );
			return null;
		}
	}

	/**
	 * Deletes a sudo policy based on the policy name.
	 *
	 * @static
	 * @param  $sudoername
	 * @return bool
	 */
	static function deleteSudoer( $sudoername ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerLDAPDomain;

		$wgAuth->connect( $wgOpenStackManagerLDAPDomain );
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );

		$sudoer = new OpenStackNovaSudoer( $sudoername );
		if ( ! $sudoer ) {
			$wgAuth->printDebug( "Sudoer $sudoername does not exist", NONSENSITIVE );
			return false;
		}
		$dn = $sudoer->sudoerDN;

		wfSuppressWarnings();
		$success = ldap_delete( $wgAuth->ldapconn, $dn );
		wfRestoreWarnings();
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted sudoer $sudoername", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete sudoer $sudoername", NONSENSITIVE );
			return false;
		}
	}

}

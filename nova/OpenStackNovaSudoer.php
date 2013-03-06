<?php

/**
 * class for nova sudoers
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaSudoer {

	var $sudoername;
	var $project;
	var $sudoerDN;
	var $sudoerInfo;

	/**
	 * @param $sudoername
	 * @param $project
	 */
	function __construct( $sudoername, $project ) {
		$this->sudoername = $sudoername;
		$this->project = $project;
		OpenStackNovaLdapConnection::connect();
		$this->fetchSudoerInfo();
	}

	/**
	 * Fetch the sudoer policy from LDAP and initialize the object
	 *
	 * @return void
	 */
	function fetchSudoerInfo() {
		global $wgAuth;
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'sudoerinfo', $this->project->getProjectName() . $this->sudoername );

		$sudoerInfo = $wgMemc->get( $key );

		if ( is_array( $sudoerInfo ) ) {
			$this->sudoerInfo = $sudoerInfo;
		} else {
			$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $this->project->getSudoersDN(),
									'(cn=' . $this->sudoername . ')' );
			$this->sudoerInfo = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
			$wgMemc->set( $key, $this->sudoerInfo, 3600 * 24 );
		}
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
			return array();
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
		global $wgMemc;

		$sudoer = array();
		$sudoer['sudouser'] = array();
		foreach ( $users as $user ) {
			$sudoer['sudouser'][] = $user;
		}
		$sudoer['sudohost'] = array();
		foreach ( $hosts as $host ) {
			$sudoer['sudohost'][] = $host;
		}
		$sudoer['sudocommand'] = array();
		foreach ( $commands as $command ) {
			$sudoer['sudocommand'][] = $command;
		}
		$sudoer['sudooption'] = array();
		foreach ( $options as $option ) {
			$sudoer['sudooption'][] = $option;
		}

		$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->sudoerDN, $sudoer );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully modified sudoer $this->sudoerDN", NONSENSITIVE );
			$key = wfMemcKey( 'openstackmanager', 'sudoerinfo', $this->project->getProjectName() . $this->sudoername );
			$wgMemc->delete( $key );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to modify sudoer $this->sudoerDN", NONSENSITIVE );
			return false;
		}
	}

	function deleteUser( $username ) {
		global $wgAuth;
		global $wgMemc;

		if ( isset( $this->sudoerInfo[0]['sudouser'] ) ) {
			$sudousers = $this->sudoerInfo[0]['sudouser'];
			array_shift( $sudousers );
			$index = array_search( $username, $sudousers );
			if ( $index === false ) {
				$wgAuth->printDebug( "Failed to find userDN in sudouser list", NONSENSITIVE );
				return false;
			}
			unset( $sudousers[$index] );
			$values = array();
			$values['sudouser'] = array();
			foreach ( $sudousers as $sudouser ) {
				$values['sudouser'][] = $sudouser;
			}
			$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $this->sudoerDN, $values );
			if ( $success ) {
				$key = wfMemcKey( 'openstackmanager', 'sudoerinfo', $this->project->getProjectName() . $this->sudoername );
				$wgMemc->delete( $key );
				return true;
			}
		}
		return false;
	}

	/**
	 * Get all sudo policies
	 *
	 * @param $projectName
	 * @return array of OpenStackNovaSudoer
	 */
	static function getAllSudoersByProject( $projectName ) {
		global $wgAuth;

		OpenStackNovaLdapConnection::connect();

		$sudoers = array();
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$result = LdapAuthenticationPlugin::ldap_search( $wgAuth->ldapconn, $project->getSudoersDN(), '(&(cn=*)(objectclass=sudorole))' );
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $wgAuth->ldapconn, $result );
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$sudoer = new OpenStackNovaSudoer( $entry['cn'][0], $project );
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
	 * @param $sudoerName
	 * @param $projectName
	 * @return null|OpenStackNovaSudoer
	 */
	static function getSudoerByName( $sudoerName, $projectName ) {
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$sudoer = new OpenStackNovaSudoer( $sudoerName, $project );
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
	 * @param $projectName
	 * @param  $users
	 * @param  $hosts
	 * @param  $commands
	 * @param  $options
	 * @return null|OpenStackNovaSudoer
	 */
	static function createSudoer( $sudoername, $projectName, $users, $hosts, $commands, $options ) {
		global $wgAuth;

		OpenStackNovaLdapConnection::connect();

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
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$dn = 'cn=' . $sudoername . ',' . $project->getSudoersDN();

		$success = LdapAuthenticationPlugin::ldap_add( $wgAuth->ldapconn, $dn, $sudoer );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully added sudoer $sudoername", NONSENSITIVE );
			return new OpenStackNovaSudoer( $sudoername, $project );
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
	 * @param $projectName
	 * @return bool
	 */
	static function deleteSudoer( $sudoername, $projectName ) {
		global $wgAuth;
		global $wgMemc;

		OpenStackNovaLdapConnection::connect();

		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$sudoer = new OpenStackNovaSudoer( $sudoername, $project );
		if ( ! $sudoer ) {
			$wgAuth->printDebug( "Sudoer $sudoername does not exist", NONSENSITIVE );
			return false;
		}
		$dn = $sudoer->sudoerDN;

		$success = LdapAuthenticationPlugin::ldap_delete( $wgAuth->ldapconn, $dn );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully deleted sudoer $sudoername", NONSENSITIVE );
			$key = wfMemcKey( 'openstackmanager', 'sudoerinfo', $projectName . $sudoername );
			$wgMemc->delete( $key );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to delete sudoer $sudoername", NONSENSITIVE );
			return false;
		}
	}

}

<?php

/**
 * class for nova sudoers
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaSudoer {

	public $sudoername;
	public $project;
	public $sudoerDN;
	public $sudoerInfo;

	/**
	 * @param string $sudoername
	 * @param string $project
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
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'sudoerinfo',
			$this->project->getProjectName() . $this->sudoername
		);

		$sudoerInfo = $wgMemc->get( $key );

		if ( is_array( $sudoerInfo ) ) {
			$this->sudoerInfo = $sudoerInfo;
		} else {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$result = LdapAuthenticationPlugin::ldap_search(
				$ldap->ldapconn,
				$this->project->getSudoersDN(),
				'(cn=' . $this->sudoername . ')'
			);
			$this->sudoerInfo = LdapAuthenticationPlugin::ldap_get_entries(
				$ldap->ldapconn, $result
			);
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
			return [];
		}
	}

	/**
	 * Return the 'run as' users for the policy
	 *
	 * @return array
	 */
	function getSudoerRunAsUsers() {
		if ( isset( $this->sudoerInfo[0]['sudorunasuser'] ) ) {
			$runasusers = $this->sudoerInfo[0]['sudorunasuser'];
			array_shift( $runasusers );
			return $runasusers;
		} else {
			return [];
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
			return [];
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
			return [];
		}
	}

	/**
	 * Modify a sudoer based on users, commands, and options.
	 *
	 * @param array $users
	 * @param array $runasuser
	 * @param array $commands
	 * @param array $options
	 * @return bool
	 */
	function modifySudoer( $users, $runasuser, $commands, $options ) {
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$sudoer = [];
		$sudoer['sudouser'] = [];
		foreach ( $users as $user ) {
			$sudoer['sudouser'][] = $user;
		}
		$sudoer['sudorunasuser'] = [];
		foreach ( $runasuser as $runas ) {
			$sudoer['sudorunasuser'][] = $runas;
		}
		$sudoer['sudocommand'] = [];
		foreach ( $commands as $command ) {
			$sudoer['sudocommand'][] = $command;
		}
		$sudoer['sudooption'] = [];
		foreach ( $options as $option ) {
			$sudoer['sudooption'][] = $option;
		}

		$success = LdapAuthenticationPlugin::ldap_modify(
			$ldap->ldapconn, $this->sudoerDN, $sudoer
		);
		if ( $success ) {
			$ldap->printDebug( "Successfully modified sudoer $this->sudoerDN", NONSENSITIVE );
			$key = wfMemcKey( 'openstackmanager', 'sudoerinfo',
				$this->project->getProjectName() . $this->sudoername
			);
			$wgMemc->delete( $key );
			return true;
		} else {
			$ldap->printDebug( "Failed to modify sudoer $this->sudoerDN", NONSENSITIVE );
			return false;
		}
	}

	function deleteUser( $username ) {
		global $wgMemc;

		if ( isset( $this->sudoerInfo[0]['sudouser'] ) ) {
			$ldap = LdapAuthenticationPlugin::getInstance();
			$sudousers = $this->sudoerInfo[0]['sudouser'];
			array_shift( $sudousers );
			$index = array_search( $username, $sudousers );
			if ( $index === false ) {
				$ldap->printDebug( "Failed to find userDN in sudouser list", NONSENSITIVE );
				return false;
			}
			unset( $sudousers[$index] );
			$values = [];
			$values['sudouser'] = [];
			foreach ( $sudousers as $sudouser ) {
				$values['sudouser'][] = $sudouser;
			}
			$success = LdapAuthenticationPlugin::ldap_modify(
				$ldap->ldapconn, $this->sudoerDN, $values
			);
			if ( $success ) {
				$key = wfMemcKey( 'openstackmanager', 'sudoerinfo',
					$this->project->getProjectName() . $this->sudoername
				);
				$wgMemc->delete( $key );
				return true;
			}
		}
		return false;
	}

	/**
	 * Get all sudo policies
	 *
	 * @param string $projectName
	 * @return array of OpenStackNovaSudoer
	 */
	static function getAllSudoersByProject( $projectName ) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$sudoers = [];
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$result = LdapAuthenticationPlugin::ldap_search(
			$ldap->ldapconn, $project->getSudoersDN(), '(&(cn=*)(objectclass=sudorole))'
		);
		if ( $result ) {
			$entries = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
				foreach ( $entries as $entry ) {
					$sudoer = new OpenStackNovaSudoer( $entry['cn'][0], $project );
					$sudoers[] = $sudoer;
				}
			}
		}

		return $sudoers;
	}

	/**
	 * Get a sudoer policy by name.
	 *
	 * @static
	 * @param string $sudoerName
	 * @param string $projectName
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
	 * Create a new sudoer based on name, users, commands, and options.
	 * Returns null on sudoer creation failure.
	 *
	 * @static
	 * @param string $sudoername
	 * @param string $projectName
	 * @param array $users
	 * @param array $runasuser
	 * @param array $commands
	 * @param array $options
	 * @return null|OpenStackNovaSudoer
	 */
	static function createSudoer(
		$sudoername, $projectName, $users, $runasuser, $commands, $options
	) {
		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$sudoer = [];
		$sudoer['objectclass'][] = 'sudorole';
		foreach ( $users as $user ) {
			$sudoer['sudouser'][] = $user;
		}
		foreach ( $runasuser as $runas ) {
			$sudoer['sudorunasuser'][] = $runas;
		}
		foreach ( $commands as $command ) {
			$sudoer['sudocommand'][] = $command;
		}
		foreach ( $options as $option ) {
			$sudoer['sudooption'][] = $option;
		}
		$sudoer['sudohost'][] = 'ALL';
		$sudoer['cn'] = $sudoername;
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$dn = 'cn=' . $sudoername . ',' . $project->getSudoersDN();

		$success = LdapAuthenticationPlugin::ldap_add( $ldap->ldapconn, $dn, $sudoer );
		if ( $success ) {
			$ldap->printDebug( "Successfully added sudoer $sudoername", NONSENSITIVE );
			return new OpenStackNovaSudoer( $sudoername, $project );
		} else {
			$ldap->printDebug( "Failed to add sudoer $sudoername", NONSENSITIVE );
			return null;
		}
	}

	/**
	 * Deletes a sudo policy based on the policy name.
	 *
	 * @static
	 * @param string $sudoername
	 * @param string $projectName
	 * @return bool
	 */
	static function deleteSudoer( $sudoername, $projectName ) {
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		OpenStackNovaLdapConnection::connect();

		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$sudoer = new OpenStackNovaSudoer( $sudoername, $project );
		if ( !$sudoer ) {
			$ldap->printDebug( "Sudoer $sudoername does not exist", NONSENSITIVE );
			return false;
		}
		$dn = $sudoer->sudoerDN;

		$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $dn );
		if ( $success ) {
			$ldap->printDebug( "Successfully deleted sudoer $sudoername", NONSENSITIVE );
			$key = wfMemcKey( 'openstackmanager', 'sudoerinfo', $projectName . $sudoername );
			$wgMemc->delete( $key );
			return true;
		} else {
			$ldap->printDebug( "Failed to delete sudoer $sudoername", NONSENSITIVE );
			return false;
		}
	}

}

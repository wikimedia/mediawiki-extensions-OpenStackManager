<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class OpenStackNovaPurgeOldServiceGroups extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Delete all service users and groups of the form " .
			"n=local-<servicegroup>,ou=groups,cn=<project>,ou=projects,dc=wikimedia,dc=org";
	}

	public function execute() {
		global $wgOpenStackManagerLDAPUsername;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$user     = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
		$projects = OpenStackNovaProject::getAllProjects();

		$failedSync = false;

		$attempt_count = 0;
		$synced_count  = 0;
		$failed_count  = 0;

		/**
		 * @var $project OpenStackNovaProject
		 */
		foreach ( $projects as $project ) {
			// actually load the project info from ldap
			// (getAllProjects() doesn't do this)
			$project->fetchProjectInfo();
			$projectName = $project->getProjectName();

			$oldServiceGroupOUDN = 'ou=groups,' . $project->getProjectDN();
			$oldServiceUserOUDN = 'ou=people,' . $project->getProjectDN();

			$result = LdapAuthenticationPlugin::ldap_search( $ldap->ldapconn,
				$oldServiceGroupOUDN,
				'(objectclass=groupofnames)' );

			if ( $result ) {
				$this->serviceGroups = [];
				$groupList = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
				if ( isset( $groupList ) ) {
					array_shift( $groupList );
					foreach ( $groupList as $groupEntry ) {
						$deleteme = "cn=" . $groupEntry['cn'][0] . "," . $oldServiceGroupOUDN;
						print "needs deleting: " . $deleteme . "...";
						$attempt_count++;
						$success = LdapAuthenticationPlugin::ldap_delete(
							$ldap->ldapconn, $deleteme
						);
						if ( $success ) {
							$synced_count++;
							print "done.\n";
						} else {
							$failed_count++;
							print "FAILED\n";
						}
					}
				}
			}

			$result = LdapAuthenticationPlugin::ldap_search( $ldap->ldapconn,
				$oldServiceUserOUDN,
				'(objectclass=person)' );

			if ( $result ) {
				$this->serviceGroups = [];
				$groupList = LdapAuthenticationPlugin::ldap_get_entries( $ldap->ldapconn, $result );
				if ( isset( $groupList ) ) {
					array_shift( $groupList );
					foreach ( $groupList as $groupEntry ) {
						$deleteme = "uid=" . $groupEntry['cn'][0] . "," . $oldServiceUserOUDN;
						print "user needs deleting: " . $deleteme . "...";
						$attempt_count++;
						$success = LdapAuthenticationPlugin::ldap_delete(
							$ldap->ldapconn, $deleteme
						);
						if ( $success ) {
							$synced_count++;
							print "done.\n";
						} else {
							$failed_count++;
							print "FAILED\n";
						}
					}
				}
			}

			$deleteme = $oldServiceGroupOUDN;
			print "ou needs deleting: " . $deleteme . "...";
			$attempt_count++;
			$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $deleteme );
			if ( $success ) {
				$synced_count++;
				print "done.\n";
			} else {
				$failed_count++;
				print "FAILED\n";
			}

			$deleteme = $oldServiceUserOUDN;
			print "ou needs deleting: " . $deleteme . "...";
			$attempt_count++;
			$success = LdapAuthenticationPlugin::ldap_delete( $ldap->ldapconn, $deleteme );
			if ( $success ) {
				$synced_count++;
				print "done.\n";
			} else {
				$failed_count++;
				print "FAILED\n";
			}
		}

		$this->output( "$attempt_count items needed cleanup. $synced_count removed, " .
			"$failed_count failed.\n" );
		$this->output( "Done.\n" );

		return ( $failed_count == 0 );
	}
}

$maintClass = "OpenStackNovaPurgeOldServiceGroups";
require_once RUN_MAINTENANCE_IF_MAIN;

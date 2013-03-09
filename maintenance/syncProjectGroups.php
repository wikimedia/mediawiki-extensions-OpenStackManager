<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class OpenStackNovaSyncProjectGroups extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Sync each cn=<project-name>,ou=projects members to cn=project-<project-name>,ou=groups";
	}

	public function execute() {
		global $wgOpenStackManagerLDAPUsername;

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

			$retval = $project->syncProjectGroupMembers();
			$attempt_count++;

			// -1: failure
			//  0: no change
			//  1: successful sync

			if ( $retval != 0 ) {
				$this->output( ( $retval ? "Succeeded" : "Failed")  . " syncing members for project $projectName and group " . $project->projectGroup->getProjectGroupName() );
				if ( $retval < 0 ) {
					$failedSync = true;
					$failed_count++;
				} else {
					$synced_count++;
				}
			}
			// echo "\nproject member DNs:\n";
			// print_r( $project->getMemberDNs() );

			// echo "\nproject group member DNs:\n";
			// print_r( $projectGroup->getMemberDNs() );
		}

		$this->output( "$attempt_count project groups were synced, $synced_count changed, $failed_count failed.\n" );
		$this->output( "Done.\n" );

		// return true if there were no failed syncs
		return !$failedSync;
	}

}

$maintClass = "OpenStackNovaSyncProjectGroups";
require_once( RUN_MAINTENANCE_IF_MAIN );

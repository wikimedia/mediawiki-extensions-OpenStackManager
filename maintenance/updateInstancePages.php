<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class OpenStackNovaUpdateInstancePages extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Update all instance pages in the wiki";
	}

	public function execute() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;

		$user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
		$userNova = OpenStackNovaController::newFromUser( $user );
		$projects = OpenStackNovaProject::getAllProjects();
		# HACK (please fix): Keystone doesn't deliver services and endpoints unless
		# a project token is returned, so we need to feed it a project. Ideally this
		# should be configurable, and not hardcoded like this.
		$userNova->setProject( 'bastion' );
		$userNova->authenticate( $wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword );
		$regions = $userNova->getRegions( 'compute' );
		foreach ( $regions as $region ) {
			$this->output( "Running region : " . $region . "\n" );
			foreach ( $projects as $project ) {
				$projectName = $project->getProjectName();
				$this->output( "Running project : " . $projectName . "\n" );
				$userNova->setProject( $projectName );
				$userNova->setRegion( $region );
				$instances = $userNova->getInstances();
				if ( ! $instances ) {
					$wgAuth->printDebug( "No instance, continuing", NONSENSITIVE );
					continue;
				}
				foreach ( $instances as $instance ) {
					$this->output( "Updating instance : " . $instance->getInstanceId() . "\n" );
					$instance->editArticle( $userNova );
				}
			}
		}

		$this->output( "Done.\n" );
	}

}

$maintClass = "OpenStackNovaUpdateInstancePages";
require_once( RUN_MAINTENANCE_IF_MAIN );

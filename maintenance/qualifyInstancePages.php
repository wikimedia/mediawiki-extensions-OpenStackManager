<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class QualifyInstancePages extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Move instance pages from id to fqdn.";
	}

	public function execute() {
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;

		$user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
		$userNova = OpenStackNovaController::newFromUser( $user );
		$projects = OpenStackNovaProject::getAllProjects();
		# HACK (please fix): Keystone doesn't deliver services and endpoints unless
		# a project token is returned, so we need to feed it a project. Ideally this
		# should be configurable, and not hardcoded like this.
		$userNova->setProject( 'bastion' );
		$userNova->authenticate(
			$wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword
		);
		$regions = $userNova->getRegions( 'compute' );
		foreach ( $regions as $region ) {
			$this->output( "Running region: " . $region . "\n" );
			foreach ( $projects as $project ) {
				$projectName = $project->getProjectName();
				$this->output( "Running project: " . $projectName . "\n" );
				$userNova->setProject( $projectName );
				$userNova->setRegion( $region );
				$instances = $userNova->getInstances();
				if ( !$instances ) {
					$ldap = LdapAuthenticationPlugin::getInstance();
					$ldap->printDebug( "No instance, continuing", NONSENSITIVE );
					continue;
				}
				foreach ( $instances as $instance ) {
					$host = $instance->getHost();
					if ( !$host ) {
						$this->output( "Skipping instance due to missing host entry: " .
							$instance->getInstanceId() . "\n" );
						continue;
					}
					$this->output( "Renaming instance: " . $instance->getInstanceId() . "\n" );
					$ot = Title::newFromText( $instance->getInstanceId(), NS_NOVA_RESOURCE );
					$nt = Title::newFromText(
						$host->getFullyQualifiedHostName(), NS_NOVA_RESOURCE
					);
					$ot->moveTo( $nt, false, 'Maintenance script move from id to fqdn.' );
				}
			}
		}

		$this->output( "Done.\n" );
	}

}

$maintClass = "QualifyInstancePages";
require_once RUN_MAINTENANCE_IF_MAIN;

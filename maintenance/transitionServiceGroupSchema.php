<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class OpenStackNovaTransitionServiceGroups extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Copy each cn=local-<servicegroup>,ou=groups,cn=<project>,ou=projects,dc=wikimedia,dc=org to cn=<project>.<servicegroup>,ou=groups,cn=<project>,ou=projects,dc=wikimedia,dc=org";
	}

	public function updateMemberName( $member, $project ) {
		global $wgOpenStackManagerServiceGroupPrefix;

		if ( strpos( $member, $wgOpenStackManagerServiceGroupPrefix, 0 ) === 0 ) {
			# This is a service-group member!
			$simpleMemberName = substr( $member, strlen( $wgOpenStackManagerServiceGroupPrefix ) );
			$newMemberName = $project->getProjectName() . '.' . $simpleMemberName;
		} else {
			$newMemberName = $member;
		}
		return $newMemberName;
	}

	public function execute() {
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerServiceGroupPrefix;

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
			$serviceGroups = $project->getServiceGroups();

			foreach ( $serviceGroups as $serviceGroup ) {
				$fullGroupName = $serviceGroup->getGroupName();

				if ( strpos( $fullGroupName, $wgOpenStackManagerServiceGroupPrefix, 0 ) === 0 ) {
					$groupName = substr( $fullGroupName, strlen( $wgOpenStackManagerServiceGroupPrefix ) );
				} else {
					$groupName = $fullGroupName;
				}

				$groupMembers = $serviceGroup->getMembers();

				if ( empty( $groupMembers ) ) {
					continue;
				}

				$originalMember = $groupMembers[0];
				$retval = OpenStackNovaServiceGroup::createServiceGroup( $groupName, $project, $this->updateMemberName( $originalMember, $project ) );
				$attempt_count++;

				if ( $retval ) {
					$this->output( "Succeeded copying service group $groupName in $projectName\n" );
					$synced_count++;
					foreach ( $groupMembers as $member ) {
						if ( $member === $originalMember ) {
							continue;
						}
						$serviceGroup->addMember( $this->updateMemberName( $member, $project ) );
					}
				} else {
					$this->output( "Failed copying service group $groupName in $projectName\n" );
					$failedSync = true;
					$failed_count++;
				}
			}
		}

		$this->output( "$attempt_count service groups were synced, $synced_count changed, $failed_count failed.\n" );
		$this->output( "Done.\n" );

		// return true if there were no failed syncs
		return !$failedSync;
	}

}

$maintClass = "OpenStackNovaTransitionServiceGroups";
require_once RUN_MAINTENANCE_IF_MAIN;

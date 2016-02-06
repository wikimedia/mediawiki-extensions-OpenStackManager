<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );


class OpenStackNovaDumpProjects extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Read ldap-based projects, roles and memberships, and insert them into keystone.";
	}

	public function execute() {
		global $wgOpenStackManagerCreateResourcePages;

		$wgOpenStackManagerCreateResourcePages = false;

		$projects = OpenStackNovaProjectLdap::getAllProjects();
		foreach ( $projects as $ldapproject) {
			$name = $ldapproject->getProjectName();
			$ldapproject->fetchProjectInfo( true );
			$keystoneproject = OpenStackNovaProject::createProject($name);
			if ( !$keystoneproject ) {
				print "Failed to create project $name, loading instead\n";
				$keystoneproject = OpenStackNovaProject::getProjectByName($name);
			}
			if ( !$keystoneproject ) {
				print "Failed to create or load project $name, skipping\n";
				continue;
			}
			$id = $keystoneproject->getId();
			print "Migrating project $name to keystone project with id $id\n";
			$keystoneproject->fetchProjectInfo( true );
			$ldapmembers = $ldapproject->getMembers();
			foreach ( $ldapmembers as $member ) {
				print "* Adding $member to $name\n";
				if ( !$keystoneproject->addMember( $member ) ) {
					print "Failed to add member $member to $name\n";
				}
			}
			$ldaproles = $ldapproject->getRoles();
			foreach ( $ldaproles as $ldaprole ) {
				$rolename = $ldaprole->getRoleName();
				$keystonerole = OpenStackNovaRole::getProjectRoleByName( $rolename, $keystoneproject );
				foreach ( $ldaprole->getMembers() as $membername ) {
					print "* Adding $membername to $rolename in $name\n";
					if ( !$keystonerole->addMember( $membername ) ) {
						print "Failed to add member $membername to role $rolename in $name\n";
					}
				}
			}
		}
	}
}

$maintClass = "OpenStackNovaDumpProjects";
require_once( RUN_MAINTENANCE_IF_MAIN );

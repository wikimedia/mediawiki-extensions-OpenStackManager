<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Maintenance
 * @author Ryan Lane
 */

$IP = getenv( 'MW_INSTALL_PATH' );

if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';

}
require_once "$IP/maintenance/Maintenance.php";

class Updatedomains extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'name', 'The instance hostname, e.g. bastion1', false, true );
		$this->addOption( 'project', 'The instance project name, e.g. bastion', false, true );
		$this->addOption( 'region', 'The instance region, e.g. eqiad', false, true );
		$this->addOption( 'all-instances', 'Run this command on every instance.', false, false );
	}

	public function execute() {
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;

		if ( $this->hasOption( 'all-instances' ) ) {
			if ( $this->hasOption( 'region' ) ) {
				$this->error( "--all-instances cannot be used with --region.\n", true );
			}
			$instancelist = [];
			$user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
			$userNova = OpenStackNovaController::newFromUser( $user );
			$projects = OpenStackNovaProject::getAllProjects();
			$userNova->setProject( 'bastion' );
			$userNova->authenticate(
				$wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword
			);
			$regions = $userNova->getRegions( 'compute' );
			foreach ( $regions as $region ) {
				foreach ( $projects as $project ) {
					$projectName = $project->getProjectName();
					$userNova->setProject( $projectName );
					$userNova->setRegion( $region );
					$instances = $userNova->getInstances();
					if ( $instances ) {
						foreach ( $instances as $instance ) {
							$instancelist[] = [
								$region, $instance->getInstanceName(), $projectName
							];
						}
					}
				}
			}
		} elseif ( $this->hasOption( 'name' ) ) {
			if ( !$this->hasOption( 'region' ) ) {
				$this->error( "--name requires --region.\n", true );
			}
			if ( !$this->hasOption( 'project' ) ) {
				$this->error( "--name requires --project.\n", true );
			}
			$instancelist = [ [
				$this->getOption( 'region' ),
				$this->getOption( 'name' ),
				$this->getOption( 'project' ),
			] ];
		} else {
			$this->error( "Must specify either --name or --all-instances.\n", true );
		}

		OpenStackNovaLdapConnection::connect();
		foreach ( $instancelist as $instancepair ) {
			list( $instanceregion, $instancename, $instanceproject ) = $instancepair;
			$host = OpenStackNovaHost::getHostByNameAndProject(
				$instancename, $instanceproject, $instanceregion
			);
			if ( !$host ) {
				print "Skipping $instancename.$instanceproject.$instanceregion; not found.\n";
				continue;
			}

			print "\nFor instance $instancename in region $instanceregion and " .
				" project $instanceproject:\n\n";

			$namefqdn = $instancename . '.' . $instanceproject . '.' . $instanceregion .
				'.' . 'wmflabs';
			$host->addAssociatedDomain( $namefqdn );
		}
	}
}

$maintClass = "Updatedomains";
require_once RUN_MAINTENANCE_IF_MAIN;

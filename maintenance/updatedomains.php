<?php
/**
 * This script will display or modify puppet information for a given
 * puppet host.
 *
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
require_once( "$IP/maintenance/Maintenance.php" );

class UpdateDomains extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'instance', 'The instance hostname, e.g. i-00000001', false, true );
		$this->addOption( 'name', 'The instance hostname, e.g. i-00000001', false, true );
		$this->addOption( 'project', 'The instance hostname, e.g. i-00000001', false, true );
		$this->addOption( 'region', 'The instance region, e.g. pmtpa', false, true );
		$this->addOption( 'all-instances', 'Run this command on every instance.', false, false );
	}

	public function execute() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;

		if ( $this->hasOption( 'all-instances' ) ) {
			if ( $this->hasOption( 'region' ) ) {
				$this->error( "--all-instances cannot be used with --region.\n", true );
			}
			$instancelist = array();
			$user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
			$userNova = OpenStackNovaController::newFromUser( $user );
			$projects = OpenStackNovaProject::getAllProjects();
			$userNova->setProject( 'bastion' );
			$userNova->authenticate( $wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword );
			$regions = $userNova->getRegions( 'compute' );
			foreach ( $regions as $region ) {
				foreach ( $projects as $project ) {
					$projectName = $project->getProjectName();
					$userNova->setProject( $projectName );
					$userNova->setRegion( $region );
					$instances = $userNova->getInstances();
					if ( $instances ) {
						foreach ( $instances as $instance ) {
							$id = $instance->getInstanceId();
							$instancelist[] = array( $instance->getInstanceId(), $region,
                                                                                 $instance->getInstanceName(), $projectName );
						}
					}
				}
			}
		} elseif ( $this->hasOption( 'instance' ) )  {
			if ( ! $this->hasOption( 'region' ) ) {
				$this->error( "--instance requires --region.\n", true );
			}
			if ( ! $this->hasOption( 'name' ) ) {
				$this->error( "--instance requires --name.\n", true );
			}
			if ( ! $this->hasOption( 'project' ) ) {
				$this->error( "--instance requires --project.\n", true );
			}
			$instancelist = array( array( $this->getOption( 'instance' ), $this->getOption( 'region' ),
                                                      $this->getOption( 'name' ), $this->getOption( 'project' ), ) );
		} else {
			$this->error( "Must specify either --instance or --all-instances.\n", true );
		}

		if ( !class_exists( 'OpenStackNovaHost' ) ) {
			$this->error( "Couldn't find OpenStackNovaHost class.\n", true );
		}
		OpenStackNovaLdapConnection::connect();
		foreach ( $instancelist as $instancepair ) {
			$instance = $instancepair[0];
			$instanceregion = $instancepair[1];
			$instancename = $instancepair[2];
			$instanceproject = $instancepair[3];
			$host = OpenStackNovaHost::getHostByInstanceId( $instance, $instanceregion );
			if ( ! $host ) {
				print "Skipping $instance.$instanceregion; not found.\n";
				continue;
			}

			print "\nFor instance $instance name $instancename in region $instanceregion and project $instanceproject:\n\n";

			$idfqdn = $instance . '.' . $instanceproject . '.' . $instanceregion . '.' . 'wmflabs';
			$host->addAssociatedDomain($idfqdn);
			$namefqdn = $instancename . '.' . $instanceproject . '.' . $instanceregion . '.' . 'wmflabs';
			$host->addAssociatedDomain($namefqdn);
		}
	}
}

$maintClass = "UpdateDomains";
require_once( RUN_MAINTENANCE_IF_MAIN );

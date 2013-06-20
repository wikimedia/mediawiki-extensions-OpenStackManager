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

/**
 * Maintenance script that triggers an Echo notification for instance action completion.
 *
 * @ingroup Maintenance
 */
class PuppetValues extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'instance', 'The instance hostname, e.g. i-00000001', false, true );
		$this->addOption( 'all-instances', 'Run this command on every instance.', false, false );
		$this->addOption( 'delete-var', 'The variable name to delete', false, true );
		$this->addOption( 'delete-class', 'The class index to delete', false, true );
		$this->addOption( 'delete-class-name', 'Class name to delete', false, true );
		$this->addOption( 'add-class', 'New class name to add', false, true );
	}

	public function execute() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;

		if ( $this->hasOption( 'all-instances' ) ) {
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
							$instancelist[] = $instance->getInstanceId();
						}
					}
				}
			}
		} elseif ( $this->hasOption( 'instance' ) )  {
			$instancelist = array( $this->getOption( 'instance' ) );
		} else {
			$this->error( "Must specify either --instance or --all-instances.\n", true );
		}

		if ( !class_exists( 'OpenStackNovaHost' ) ) {
			$this->error( "Couldn't find OpenStackNovaHost class.\n", true );
		}
		OpenStackNovaLdapConnection::connect();
		foreach ( $instancelist as $instance ) {
			print "\nFor instance $instance:\n\n";
			$host = OpenStackNovaHost::getHostByInstanceId( $instance );
			$puppetconf = $host->getPuppetConfiguration();
			$puppetclasses = $puppetconf['puppetclass'];
			$puppetvars = $puppetconf['puppetvar'];
			$deletevar = $this->getOption( 'delete-var' );
			$deleteclass = $this->getOption( 'delete-class' );
			$deleteclassname = $this->getOption( 'delete-class-name' );
			$addclass = $this->getOption( 'add-class' );
			if ( $deletevar !== null || $deleteclass !== null || $addclass !== null || $deleteclassname != null ) {
				if ( $deletevar !== null ) {
					unset( $puppetvars[$deletevar] );
				}
				if ( $deleteclass !== null ) {
					$deleteclass = (int)$deleteclass;
					unset( $puppetclasses[$deleteclass] );
				}
				if ( $deleteclassname !== null ) {
					$deleteclassindex = array_search( $deleteclassname, $puppetclasses );
					if ( $deleteclassindex === false ) {
						$this->error( "Couldn't find class to delete $deleteclassname.\n" );
					}
					unset( $puppetclasses[$deleteclassindex] );
				}
				if ( $addclass !== null ) {
					$puppetclasses[] = $addclass;
				}
				$hostEntry = array();
				foreach ( $puppetvars as $variable => $value ) {
					$hostEntry['puppetvar'][] = $variable . '=' . $value;
				}
				foreach ( $puppetclasses as $class ) {
					$hostEntry['puppetclass'][] = $class;
				}
				$success = LdapAuthenticationPlugin::ldap_modify( $wgAuth->ldapconn, $host->hostDN, $hostEntry );
				if ( $success ) {
					print "Modified $instance.\r\n";
				}
			} else {
				print "classes\r\n\r\n";
				for ( $i=0; $i < count( $puppetclasses ); $i++ ) {
					print "$i: " . $puppetclasses[$i] . "\r\n";
				}
				print "\r\n";
				print "variables\r\n\r\n";
				foreach ( $puppetvars as $variable => $value ) {
					print "$variable: $value\r\n";
				}
			}
		}
	}
}

$maintClass = "PuppetValues";
require_once( RUN_MAINTENANCE_IF_MAIN );

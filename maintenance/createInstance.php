<?php
/**
 * This script will create a new instance -- it provides
 * a commandline alternative to the web interface for instance creation.
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
 * @author Andrew Bogott
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
class CreateInstance extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'instance', 'The instance name, e.g. testhost', true, true );
		$this->addOption( 'project', 'The instance project, e.g. testlabs', true, true );
		$this->addOption( 'region', 'The instance region, e.g. pmtpa', true, true );
		$this->addOption( 'image', 'The image ID to use when creating', true, true );
		$this->addOption( 'flavor', 'The flavor of the new instance, e.g. m1.small', true, true );
		$this->addOption( 'securitygroups', 'Comma-separated list of security groups for new instance', false, true );
		$this->addOption( 'puppetclasses', 'Comma-separated list of security groups for new instance', false, true );
	}

	public function execute() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;


		if ( $this->hasOption( 'securitygroups' ) ) {
			$secGroups = explode(',', $this->getOption( 'securitygroups' ) );
		} else {
			$secGroups = array();
		}
		if ( $this->hasOption( 'puppetclasses' ) ) {
			$pupClasses = explode(',', $this->getOption( 'puppetclasses' ) );
		} else {
			$pupClasses = array();
		}
		$region = $this->getOption( 'region' );
		$flavor = $this->getOption( 'flavor' );
		$image = $this->getOption( 'image' );
		$instance = $this->getOption( 'instance' );
		$project = $this->getOption( 'project' );

		$this->user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
		$this->userNova = OpenStackNovaController::newFromUser( $this->user );
		$this->userNova->setProject( 'testlabs' );
		$this->userNova->authenticate( $wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword );

		$this->userNova->setRegion( $region );

		$domain = OpenStackNovaDomain::getDomainByName( $region );
		if ( !$domain ) {
			print "invalid domain\n";
			return true;
		}
		$instance = $this->userNova->createInstance( $instance, $image, '', $flavor, $secGroups );
		if ( $instance ) {
			// In essex it seems attributes from extensions aren't returned. So,
			// for now we need to work around this by fetching the instance again.
			$instanceId = $instance->getInstanceOSId();
			$instance = $this->userNova->getInstance( $instanceId );
		}
		if ( $instance ) {
			$host = OpenStackNovaHost::addHostFromInstance( $instance, $domain, $pupClasses );

			if ( $host ) {
				$instance->setHost( $host );
				$title = Title::newFromText("createInstance script");
				$job = new OpenStackNovaHostJob( $title, array( 'instanceid' => $instance->getInstanceId(), 'instanceosid' => $instance->getInstanceOSId(), 'project' => $project, 'region' => $region ) );
				$job->insert();
				$image = $this->userNova->getImage( $instance->getImageId() );
				$imageName = $image->getImageName();
				print "created instance.\n";
			} else {
				$instance->deleteInstance( $this->userNova );
				print "ldap creation failed\n";
			}
		} else {
			print "instance creation failed\n";
		}

		return true;
	}
}

$maintClass = "CreateInstance";
require_once( RUN_MAINTENANCE_IF_MAIN );

<?php
/**
 * This script should be run when an instance build is completed or when an instance reboot is completed.
 * It triggers an Echo notification to the relevant users
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
 * @author Alex Monk <krenair@gmail.com>
 */

$IP = getenv( 'MW_INSTALL_PATH' );

if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';

}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script that triggers an Echo notification for instance action completion.
 *
 * @ingroup Maintenance
 */
class OnInstanceActionComplete extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'action', 'The action which was taken. Either build or reboot.', true, true );
		$this->addOption( 'instance', 'The instance hostname, e.g. i-00000001.pmtpa.wmflabs.', true, true );
	}

	public function execute() {
		if ( !class_exists( 'EchoEvent' ) ) {
			$this->error( "Couldn't find EchoEvent class.\n", true );
		} elseif ( !OpenStackNovaHost::validateHostname( $this->getOption( 'instance' ) ) ) {
			$this->error( "Instance hostname is invalid.\n", true );
		}

		$validActions = [
			'reboot',
			'build'
		];

		if ( !in_array( $this->getOption( 'action' ), $validActions ) ) {
			$this->error( "Unrecognised action.\n", true );
		}

		$dbw = wfGetDB( DB_MASTER );
		$result = $dbw->selectRow(
			'openstack_notification_event',
			[
				'event_actor_id',
				'event_project',
				'event_instance_name'
			],
			[
				'event_action' => $this->getOption( 'action' ),
				'event_instance_host' => $this->getOption( 'instance' )
			],
			__METHOD__
		);

		if ( !$result ) {
			$this->error( "Lookup of temporary event info failed.\n", true );
		}

		$successful = EchoEvent::create( [
			'type' => 'osm-instance-' . $this->getOption( 'action' ) . '-completed',
			'title' => Title::newFromText( $result->event_project, NS_NOVA_RESOURCE ),
			'agent' => User::newFromId( $result->event_actor_id ),
			'extra' => [
				'instanceName' => $result->event_instance_name,
				'projectName' => $result->event_project,
				'notifyAgent' => true
			]
		] );

		if ( $successful ) {
			$dbw->delete(
				'openstack_notification_event',
				[
					'event_action' => $this->getOption( 'action' ),
					'event_instance_host' => $this->getOption( 'instance' ),
					'event_instance_name' => $result->event_instance_name,
					'event_project' => $result->event_project,
					'event_actor_id' => $result->event_actor_id
				],
				__METHOD__
			);
			$this->output( "Event created successfully.\n" );
		} else {
			$this->error( "Something went wrong creating the echo notification.\n", true );
		}
	}
}

$maintClass = "OnInstanceActionComplete";
require_once RUN_MAINTENANCE_IF_MAIN;

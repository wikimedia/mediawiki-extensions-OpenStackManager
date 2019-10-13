<?php
class OpenStackManagerEvent {
	public static function createDeletionEvent( $instanceName, $project, $user ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			EchoEvent::create( [
				'type' => 'osm-instance-deleted',
				'title' => Title::newFromText( $project, NS_NOVA_RESOURCE ),
				'agent' => $user,
				'extra' => [
					'instanceName' => $instanceName,
					'projectName' => $project
				]
			] );
		}
	}

	/**
	 * Store the event information in a DB table. We'll get this back out in the
	 * maintenance/onInstanceActionCompletion.php script.
	 * @param string $type
	 * @param User $user
	 * @param OpenStackNovaInstance $instance
	 * @param string $project
	 */
	public static function storeEventInfo( $type, $user, $instance, $project ) {
		$dbw = wfGetDB( DB_MASTER );
		$host = $instance->getHost();
		'@phan-var OpenStackNovaPrivateHost $host';
		$dbw->insert(
			'openstack_notification_event',
			[
				'event_action' => $type,
				'event_actor_id' => $user->getId(),
				'event_instance_host' => $host->getFullyQualifiedHostName(),
				'event_instance_name' => $instance->getInstanceName(),
				'event_project' => $project
			],
			__METHOD__
		);
	}
}

<?php
class OpenStackNovaHostJob extends Job {
	public function __construct( $title, $params ) {
		// Replace synchroniseThreadArticleData with the an identifier for your job.
		parent::__construct( 'addDNSHostToLDAP', $title, $params );
	}

	/**
	 * Execute the job
	 *
	 * @return bool
	 */
	public function run() {
		global $wgOpenStackManagerNovaAdminKeys;
		global $wgAuth;

		$instanceid = $this->params['instanceid'];
		$wgAuth->printDebug( "Running DNS job for $instanceid", NONSENSITIVE );

		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$adminNova = new OpenStackNovaController( $adminCredentials );
		$instance = $adminNova->getInstance( $instanceid );
		if ( ! $instance ) {
			$wgAuth->printDebug( "Instance doesn't exist for $instanceid", NONSENSITIVE );
			# Instance no longer exists
			return true;
		}
		$ip = (string)$instance->getInstancePrivateIP();
		if ( trim( $ip ) == '' ) {
			# IP hasn't been assigned yet
			# re-add to queue
			$wgAuth->printDebug( "Readding job for $instanceid", NONSENSITIVE );
			$job = new OpenStackNovaHostJob( $this->title, $this->params );
			$job->insert();
			return true;
		}
		$host = OpenStackNovaHost::getHostByInstanceId( $instanceid );
		if ( ! $host ) {
			$wgAuth->printDebug( "Host record doesn't exist for $instanceid", NONSENSITIVE );
			return true;
		}
		$host->setARecord( $ip );

		return true;
	}
}

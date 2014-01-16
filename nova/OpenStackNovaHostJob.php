<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaHostJob extends Job {

	/**
	 * @param  $title
	 * @param  $params
	 */
	public function __construct( $title, $params ) {
		// Replace synchroniseThreadArticleData with the an identifier for your job.
		parent::__construct( 'addDNSHostToLDAP', $title, $params );
	}

	/**
	 * Execute the job. Add an IP address to a DNS record when it is available
	 * on the instance. If the instance does not exist, or it has not been
	 * assigned an IP address, re-add the job.
	 *
	 * Upon successfully adding the host, this job will also add an Article for the
	 * instance.
	 *
	 * @return bool
	 */
	public function run() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;

		$instanceid = $this->params['instanceid'];
		$instanceosid = $this->params['instanceosid'];
		$project = $this->params['project'];
		$region = $this->params['region'];
		$wgAuth->printDebug( "Running DNS job for $instanceid", NONSENSITIVE );

		$user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
		$userNova = OpenStackNovaController::newFromUser( $user );
		$userNova->setProject( $project );
		$userNova->setRegion( $region );
		$userNova->authenticate( $wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword );
		$instance = $userNova->getInstance( $instanceosid );
		if ( ! $instance ) {
			$wgAuth->printDebug( "Instance doesn't exist for $instanceosid", NONSENSITIVE );
			# Instance no longer exists
			return true;
		}
		$ip = $instance->getInstancePrivateIPs();
		$ip = $ip[0];
		if ( trim( $ip ) === '' ) {
			# IP hasn't been assigned yet
			# re-add to queue
			$wgAuth->printDebug( "Readding job for $instanceid", NONSENSITIVE );
			$job = new OpenStackNovaHostJob( $this->title, $this->params );
			$job->insert();
			return true;
		}
		$host = OpenStackNovaHost::getHostByInstanceId( $instanceid, $region );
		if ( ! $host ) {
			$wgAuth->printDebug( "Host record doesn't exist for $instanceid", NONSENSITIVE );
			return true;
		}
		$host->setARecord( $ip );
		$instance->editArticle( $userNova );

		return true;
	}
}

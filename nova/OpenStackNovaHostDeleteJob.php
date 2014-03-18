<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaHostDeleteJob extends Job {

	/**
	 * @param  $title
	 * @param  $params
	 */
	public function __construct( $title, $params ) {
		// Replace synchroniseThreadArticleData with the an identifier for your job.
		parent::__construct( 'deleteDNSHostFromLDAP', $title, $params );
	}

	/**
	 * Execute the job. Delete the selected record... if deletion fails,
	 *  resubmit.
	 *
	 * @return bool
	 */
	public function run() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;

		if ( array_key_exists( 'count', $this->params ) ) {
			$this->params['count'] += 1;
		} else {
			$this->params['count'] = 0;
		}

		if ( $this->params['count'] > 8 ) {
			$wgAuth->printDebug( "DNS delete job for $instanceid failed many times, giving up.", NONSENSITIVE );
			return true;
		}

		$count = $this->params['count'];
		$instanceid = $this->params['instanceid'];
		$instanceosid = $this->params['instanceosid'];
		$project = $this->params['project'];
		$region = $this->params['region'];
		$wgAuth->printDebug( "Running DNS delete job for $instanceid, attempt number $count", NONSENSITIVE );

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
		$host = $instance->getHost();
		if ( ! $host ) {
			$wgAuth->printDebug( "Host entry doesn't exist for $instanceosid", NONSENSITIVE );
			return true;
		}
		$success = $host->deleteHost();
		if ( $success ) {
			return true;
		} else {
			# re-add to queue
			$wgAuth->printDebug( "Readding host deletion job for $instanceid", NONSENSITIVE );
			$job = new OpenStackNovaHostDeleteJob( $this->title, $this->params );
			$job->insert();
			return true;
		}

		return true;
	}
}

<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaHostDeleteJob extends Job {

	/**
	 * @param Title $title
	 * @param array $params
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
		global $wgUser;

		if ( array_key_exists( 'count', $this->params ) ) {
			$this->params['count'] += 1;
		} else {
			$this->params['count'] = 0;
		}

		$instancename = $this->params['instancename'];
		$project = $this->params['project'];

		if ( $this->params['count'] > 8 ) {
			$wgAuth->printDebug( "DNS delete job for $instanceid failed many times, giving up.", NONSENSITIVE );
			return true;
		}

		$user = isset( $this->params['user'] )
			? User::newFromName( $this->params['user'] )
			: User::newFromName( 'OpenStackManager Extension' );
		if ( !$user instanceof User ) {
			$user = User::newFromName( 'OpenStackManager Extension' );
		}
		$wgUser = $user;

		$count = $this->params['count'];
		$region = $this->params['region'];
		$wgAuth->printDebug( "Running DNS delete job for $instanceid, attempt number $count", NONSENSITIVE );

		$host = OpenStackNovaHost::getHostByNameAndProject( $instancename, $project, $region );
		if ( ! $host ) {
			$wgAuth->printDebug( "Host entry doesn't exist for $instanceid", NONSENSITIVE );
			return true;
		}
		$success = $host->deleteHost();
		if ( !$success ) {
			# re-add to queue
			$wgAuth->printDebug( "Readding host deletion job for $instanceid", NONSENSITIVE );
			$job = new OpenStackNovaHostDeleteJob( $this->title, $this->params );
			JobQueueGroup::singleton()->push( $job );
		}

		return true;
	}
}

<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaHostJob extends Job {

	/**
	 * @param Title $title
	 * @param array $params
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
		global $wgUser;
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;
		$auth = $this->params['auth'];

		$user = isset( $this->params['user'] )
			? User::newFromName( $this->params['user'] )
			: User::newFromName( 'OpenStackManager Extension' );
		if ( !$user instanceof User ) {
			$user = User::newFromName( 'OpenStackManager Extension' );
		}
		$wgUser = $user;

		$instancename = $this->params['instancename'];
		$instanceosid = $this->params['instanceosid'];
		$project = $this->params['project'];
		$region = $this->params['region'];
		$auth->printDebug( "Running DNS job for $instanceosid", NONSENSITIVE );

		$user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
		$userNova = OpenStackNovaController::newFromUser( $user );
		$userNova->setProject( $project );
		$userNova->setRegion( $region );
		$userNova->authenticate( $wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword );
		$instance = $userNova->getInstance( $instanceosid );
		if ( ! $instance ) {
			$auth->printDebug( "Instance doesn't exist for $instanceosid", NONSENSITIVE );
			# Instance no longer exists
			return true;
		}
		$ip = $instance->getInstancePrivateIPs();
		$ip = $ip[0];
		if ( trim( $ip ) === '' ) {
			# IP hasn't been assigned yet
			# re-add to queue
			$auth->printDebug( "Readding job for $instanceosid", NONSENSITIVE );
			$job = new OpenStackNovaHostJob( $this->title, $this->params );
			$job->insert();
			return true;
		}
		$host = OpenStackNovaHost::getHostByNameAndProject( $instancename, $project, $region );
		if ( ! $host ) {
			$auth->printDebug( "Host record doesn't exist for $instancename in $project", NONSENSITIVE );
			return true;
		}
		$host->setARecord( $ip );
		$instance->editArticle( $userNova );

		return true;
	}
}

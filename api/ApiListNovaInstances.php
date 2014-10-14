<?php

class ApiListNovaInstances extends ApiQueryGeneratorBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'ni' );
	}

	public function execute() {
		$this->run();
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run();
	}

	public function run() {
		global $wgOpenStackManagerLDAPUsername;
		global $wgOpenStackManagerLDAPUserPassword;
		global $wgMemc;

		$params = $this->extractRequestParams();
		$project = OpenStackNovaProject::getProjectByName( $params['project'] );
		if ( !$project ) {
			// This shouldn't be possible since the API should enforce valid names
			$this->dieUsage( 'Invalid project specified.', 'badproject' );
		}

		$key = wfMemcKey( 'openstackmanager', 'apilistnovainstances', $params['region'], $params['project'] );
		$instancesInfo = $wgMemc->get( $key );
		if ( $instancesInfo === false ) {
			$user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
			$userNova = OpenStackNovaController::newFromUser( $user );
			$userNova->authenticate( $wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword );

			$userNova->setProject( $project->getName() );
			$userNova->setRegion( $params['region'] ); // validated by API

			$instances = $userNova->getInstances();
			$instancesInfo = array();
			foreach ( $instances as $instance ) {
				$instancesInfo[ ] = array(
					'name' => $instance->getInstanceName(),
					'state' => $instance->getInstanceState(),
					'ip' => $instance->getInstancePrivateIPs(),
					'id' => $instance->getInstanceId(),
					'floatingip' => $instance->getInstancePublicIPs(),
					'securitygroups' => $instance->getSecurityGroups(),
					'imageid' => $instance->getImageId(),
				);
			}
		}

		// Cache info for 1 minute, not caching for longer since we do not invalidate
		$wgMemc->set( $key, $instancesInfo, 1 * 60 );

		foreach ( $instancesInfo as $info ) {
			// UGH I hate XML
			$this->getResult()->setIndexedTagName( $info['securitygroups'], 'group' );
			$this->getResult()->setIndexedTagName( $info['ip'], 'ip' );
			$this->getResult()->setIndexedTagName( $info['floatingip'], 'floatingip' );

			$this->getResult()->addValue( array( 'query', $this->getModuleName() ), null, $info );
		}

		$this->getResult()->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'instance' );
	}

	/**
	 * HACK: I can't figure out the proper way to do this
	 */
	private function getRegions() {
		global $wgOpenStackManagerProxyGateways;
		return array_keys( $wgOpenStackManagerProxyGateways );
	}

	public function getAllowedParams() {
		return array(
			'project' => array(
				ApiBase::PARAM_TYPE => OpenStackNovaProject::getAllProjectNames(),
				ApiBase::PARAM_REQUIRED => true,
			),
			'region' => array(
				ApiBase::PARAM_TYPE => $this->getRegions(),
				ApiBase::PARAM_REQUIRED => true,
			)
		);
	}

	public function getDescription() {
		return array(
			'Returns a list of instances for the given project'
		);
	}

	public function getExamples() {
		return 'api.php?action=query&list=novainstances&niproject=cvn&niregion=eqiad';
	}
}

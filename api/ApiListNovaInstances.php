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
			$this->dieWithError( 'apierror-openstackmanager-badproject', 'badproject' );
		}

		$key = wfMemcKey( 'openstackmanager', 'apilistnovainstances',
			$params['region'], $params['project'] );
		$instancesInfo = $wgMemc->get( $key );
		if ( $instancesInfo === false ) {
			$user = new OpenStackNovaUser( $wgOpenStackManagerLDAPUsername );
			$userNova = OpenStackNovaController::newFromUser( $user );
			$userNova->authenticate(
				$wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword
			);

			$userNova->setProject( $project->getName() );
			$userNova->setRegion( $params['region'] ); // validated by API

			$instances = $userNova->getInstances();
			$instancesInfo = [];
			foreach ( $instances as $instance ) {
				$instancesInfo[ ] = [
					'name' => $instance->getInstanceName(),
					'state' => $instance->getInstanceState(),
					'ip' => $instance->getInstancePrivateIPs(),
					'id' => $instance->getInstanceId(),
					'floatingip' => $instance->getInstancePublicIPs(),
					'securitygroups' => $instance->getSecurityGroups(),
					'imageid' => $instance->getImageId(),
				];
			}
			// Cache info for 1 minute, not longer since we do not invalidate
			$wgMemc->set( $key, $instancesInfo, 60 );
		}

		foreach ( $instancesInfo as $info ) {
			// UGH I hate XML
			$this->getResult()->setIndexedTagName( $info['securitygroups'], 'group' );
			$this->getResult()->setIndexedTagName( $info['ip'], 'ip' );
			$this->getResult()->setIndexedTagName( $info['floatingip'], 'floatingip' );

			$this->getResult()->addValue( [ 'query', $this->getModuleName() ], null, $info );
		}

		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			$this->getResult()->addIndexedTagName(
				[ 'query', $this->getModuleName() ], 'instance'
			);
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->getResult()->setIndexedTagName_internal(
				[ 'query', $this->getModuleName() ], 'instance'
			);
		}
	}

	/**
	 * HACK: I can't figure out the proper way to do this
	 */
	private function getRegions() {
		global $wgOpenStackManagerProxyGateways;
		return array_keys( $wgOpenStackManagerProxyGateways );
	}

	public function getAllowedParams() {
		return [
			'project' => [
				ApiBase::PARAM_TYPE => OpenStackNovaProject::getAllProjectNames(),
				ApiBase::PARAM_REQUIRED => true,
			],
			'region' => [
				ApiBase::PARAM_TYPE => $this->getRegions(),
				ApiBase::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=novainstances&niproject=testing&niregion=mars'
				=> 'apihelp-query+novainstances-example-1',
		];
	}
}

<?php
class ApiNovaProjectLimits extends ApiBase {
	public $userLDAP;
	public $userNova;
	public $params;

	public function canExecute( $rights = [] ) {
		if ( !$this->userLDAP->exists() ) {
			$this->dieWithError( 'openstackmanager-nonovacred' );
		}
		if ( in_array( 'inproject', $rights ) || in_array( 'isprojectadmin', $rights ) ) {
			if ( !$this->userLDAP->inProject( $this->params['project'] ) ) {
				$this->dieWithError( [
					'openstackmanager-noaccount', wfEscapeWikiText( $this->params['project'] )
				] );
			}
		}
		if ( in_array( 'isprojectadmin', $rights ) ) {
			if ( !$this->userLDAP->inRole( 'projectadmin', $this->params['project'] ) ) {
				$this->dieWithError( [
					'openstackmanager-needrole',
					'projectadmin',
					wfEscapeWikiText( $this->params['project'] ),
				] );
			}
		}
	}

	public function execute() {
		$this->params = $this->extractRequestParams();
		$this->userLDAP = new OpenStackNovaUser( $this->getUser()->getName() );

		switch ( $this->params['subaction'] ) {
		case 'getlimits':
			$this->canExecute( [ 'isprojectadmin' ] );
			$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
			$this->userNova->setProject( $this->params['project'] );
			if ( isset( $this->params['region'] ) ) {
				$regions = [ $this->params['region'] ];
			} else {
				$regions = $this->userNova->getRegions( 'compute' );
			}
			$limitsOut = [];
			foreach ( $regions as $region ) {
				$this->userNova->setRegion( $region );
				$limits = $this->userNova->getLimits();
				$limitsRegion = [];
				$limitsRegion["maxTotalRAMSize"] = $limits->getRamAvailable();
				$limitsRegion["totalRAMUsed"] = $limits->getRamUsed();
				$limitsRegion["maxTotalFloatingIps"] = $limits->getFloatingIpsAvailable();
				$limitsRegion["totalFloatingIpsUsed"] = $limits->getFloatingIpsUsed();
				$limitsRegion["maxTotalCores"] = $limits->getCoresAvailable();
				$limitsRegion["totalCoresUsed"] = $limits->getCoresUsed();
				$limitsRegion["maxTotalInstances"] = $limits->getInstancesAvailable();
				$limitsRegion["totalInstancesUsed"] = $limits->getInstancesUsed();
				$limitsRegion["maxSecurityGroups"] = $limits->getSecurityGroupsAvailable();
				$limitsRegion["totalSecurityGroupsUsed"] = $limits->getSecurityGroupsUsed();
				$limitsOut[$region] = [ 'absolute' => $limitsRegion ];
			}
			$this->getResult()->addValue(
				null, $this->getModuleName(), [ 'regions' => $limitsOut ]
			);
		}
	}

	/**
	 * Face parameter
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'subaction' => [
				ApiBase::PARAM_TYPE => [
					'getlimits',
				],
				ApiBase::PARAM_REQUIRED => true
			],
			'project' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'region' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=novaprojectlimits&subaction=limits&project=testing'
				=> 'apihelp-novaprojectlimits-example-1',
			'action=novaprojectlimits&subaction=limits&project=testing&region=A'
				=> 'apihelp-novaprojectlimits-example-2',
		];
	}

	public function mustBePosted() {
		return false;
	}

}

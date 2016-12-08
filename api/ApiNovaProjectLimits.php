<?php
class ApiNovaProjectLimits extends ApiBase {
	public $userLDAP;
	public $userNova;
	public $params;

	public function canExecute( $rights=array() ) {
		if ( ! $this->userLDAP->exists() ) {
			if ( is_callable( array( $this, 'dieWithError' ) ) ) {
				$this->dieWithError( 'openstackmanager-nonovacred' );
			} else {
				$this->dieUsage( wfMessage( 'openstackmanager-nonovacred' )->escaped(), 'openstackmanager-nonovacred' );
			}
		}
		if ( in_array( 'inproject', $rights ) || in_array( 'isprojectadmin', $rights ) ) {
			if ( ! $this->userLDAP->inProject( $this->params['project'] ) ) {
				if ( is_callable( array( $this, 'dieWithError' ) ) ) {
					$this->dieWithError( array( 'openstackmanager-noaccount', wfEscapeWikiText( $this->params['project'] ) ) );
				} else {
					$this->dieUsage( wfMessage( 'openstackmanager-noaccount', $this->params['project'] )->escaped(), 'openstackmanager-noaccount' );
				}
			}
		}
		if ( in_array( 'isprojectadmin', $rights ) ) {
			if ( ! $this->userLDAP->inRole( 'projectadmin', $this->params['project'] ) ) {
				if ( is_callable( array( $this, 'dieWithError' ) ) ) {
					$this->dieWithError( [
						'openstackmanager-needrole',
						'projectadmin',
						wfEscapeWikiText( $this->params['project'] ),
					] );
				} else {
					$this->dieUsage( wfMessage( 'openstackmanager-needrole', 'projectadmin', $this->params['project'] )->escaped(), 'openstackmanager-needrole' );
				}
			}
		}
	}

	function execute() {
		$this->params = $this->extractRequestParams();
		$this->userLDAP = new OpenStackNovaUser();

		switch( $this->params['subaction'] ) {
		case 'getlimits':
			$this->canExecute( array( 'isprojectadmin' ) );
			$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
			$this->userNova->setProject( $this->params['project'] );
			if ( isset( $this->params['region'] ) ) {
				$regions = array( $this->params['region'] );
			} else {
				$regions = $this->userNova->getRegions( 'compute' );
			}
			$limitsOut = array();
			foreach ( $regions as $region ) {
				$this->userNova->setRegion( $region );
				$limits = $this->userNova->getLimits();
				$limitsRegion = array();
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
				$limitsOut[$region] = array( 'absolute' => $limitsRegion );
			}
			$this->getResult()->addValue( null, $this->getModuleName(), array( 'regions' => $limitsOut ) );
		}

	}

	// Face parameter.
	public function getAllowedParams() {
		return array(
			'subaction' => array (
				ApiBase::PARAM_TYPE => array(
					'getlimits',
				),
				ApiBase::PARAM_REQUIRED => true
			),
			'project' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'region' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			),
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'subaction' => 'The subaction to perform.',
			'project' => 'The project to perform the subaction upon',
			'region' => 'The region to perform the subaction upon',
		) );
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Gets information on projects.';
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return array(
			'api.php?action=novaproject&subaction=limits&project=testing'
			=> 'Get limits for all regions for the testing project',
			'api.php?action=novaproject&subaction=limits&project=testing&region=A'
			=> 'Get limits for region A for the testing project',
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=novaprojectlimits&subaction=limits&project=testing'
				=> 'apihelp-novaprojectlimits-example-1',
			'action=novaprojectlimits&subaction=limits&project=testing&region=A'
				=> 'apihelp-novaprojectlimits-example-2',
		);
	}

	public function mustBePosted() {
		return false;
	}

}

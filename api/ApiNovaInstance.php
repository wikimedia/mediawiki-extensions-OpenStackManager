<?
class ApiNovaInstance extends ApiBase {
	var $userLDAP;
	var $userNova;
	var $params;

	public function canExecute() {
		if ( ! $this->userLDAP->exists() ) {
			$this->dieUsage( wfMessage( 'openstackmanager-nonovacred' )->escaped() );
		}
		if ( ! $this->userLDAP->inProject( $this->params['project'] ) ) {
			$this->dieUsage( wfMessage( 'openstackmanager-noaccount', $this->params['project'] )->escaped() );
		}
		if ( ! $this->userLDAP->inRole( 'projectadmin', $this->params['project'] ) ) {
			$this->dieUsage( wfMessage( 'openstackmanager-needrole', 'projectadmin', $this->params['project'] )->escaped() );
		}
	}

	function execute() {
		$this->params = $this->extractRequestParams();

		$this->userLDAP = new OpenStackNovaUser();
		$this->canExecute();
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $this->params['project'] );
		$this->userNova->setRegion( $this->params['region'] );

		switch( $this->params['subaction'] ) {
		case 'reboot':
			$success = $this->userNova->rebootInstance( $this->params['instanceid'] );
			if ( ! $success ) {
				$this->dieUsage( wfMessage( 'openstackmanager-rebootinstancefailed', $this->params['instanceid'] )->escaped() );
			}
			$this->getResult()->addValue( null, $this->getModuleName(), array ( 'instancestate' => 'rebooting' ) );
			break;
		}
	}

	public function getPossibleErrors() {
		return array(
			array( 'openstackmanager-rebootinstancefailed', 'instance' ),
			array( 'openstackmanager-noaccount' ),
			array( 'openstackmanager-needrole' )
		);
	}

	// Face parameter.
	public function getAllowedParams() {
		return array(
			'subaction' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'instanceid' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'project' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'region' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'token' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
		);
	}
 
	public function getParamDescription() {
		return array(
			'subaction' => 'The subaction to perform.',
			'instanceid' => 'The Nova instance ID to perform a subaction on',
			'project' => 'The project in which the instance exists',
			'region' => 'The region in which the instance exists',
			'token' => 'An edit token',
		);
	}

	public function getDescription() {
		return 'Perform actions on instances.';
	}

	public function getExamples() {
		return array(
			'api.php?action=novainstancereboot&instanceid=eb195097-8539-4e66-b0b5-be8347d8caec&project=testing&region=mars&token=aq9h9eh9eh98hebt89b'
			=> 'Reboot instance id eb195097-8539-4e66-b0b5-be8347d8caec in project testing in region mars',
		);
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return true;
	}

	public function getTokenSalt() {
		return '';
	}

	public function mustBePosted() {
		return true;
	}

}

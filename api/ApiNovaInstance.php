<?
class ApiNovaInstance extends ApiBase {
	var $userLDAP;
	var $userNova;
	var $params;

	public function canExecute() {
		if ( ! $this->userLDAP->exists() ) {
			$this->dieUsageMsg( 'openstackmanager-nonovacred' );
		}
		if ( ! $this->userLDAP->inProject( $this->params['project'] ) ) {
			$this->dieUsageMsg( 'openstackmanager-noaccount' );
		}
		if ( ! $this->userLDAP->inRole( 'projectadmin', $this->params['project'] ) ) {
			$this->dieUsageMsg( 'openstackmanager-needrole' );
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
				$this->dieUsageMsg( array( 'openstackmanager-rebootinstancefailed', $this->params['instanceid'] ) );
			}
			$this->getResult()->addValue( null, $this->getModuleName(), array ( 'instancestate' => 'rebooting' ) );
			break;
		}
	}

	public function getPossibleErrors() {
		return array(
			array( 'openstackmanager-rebootinstancefailed', 'instance' ),
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
		);
	}
 
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'subaction' => 'The subaction to perform.',
			'instanceid' => 'The Nova instance ID to perform a subaction on',
			'project' => 'The project in which the instance exists',
			'region' => 'The region in which the instance exists',
		) );
	}

	public function getDescription() {
		return 'Perform actions on instances.';
	}

	public function getExamples() {
		return array(
			'api.php?action=novainstancereboot&instanceid=eb195097-8539-4e66-b0b5-be8347d8caec&project=testing&region=mars'
			=> 'Reboot instance id eb195097-8539-4e66-b0b5-be8347d8caec in project testing in region mars',
		);
	}

	public function mustBePosted() {
		return true;
	}

}

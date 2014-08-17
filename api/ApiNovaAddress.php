<?php
class ApiNovaAddress extends ApiBase {
	public $userLDAP;
	public $userNova;
	public $params;

	public function canExecute() {
		if ( ! $this->userLDAP->exists() ) {
			$this->dieUsage( 'No credentials found for your account.', 'openstackmanager-nonovacred' );
		}

		$projects = split( ',', $this->params['project'] );

		foreach ( $projects as $p ) {
			if ( ! $this->userLDAP->inProject( $p ) ) {
				$this->dieUsage( 'User account is not in the project specified.', 'openstackmanager-noaccount' );
			}

			if ( ! $this->userLDAP->inRole( 'projectadmin', $p ) ) {
				$this->dieUsage( 'User account is not in the projectadmin role.', 'openstackmanager-needrole' );
			}
		}
	}

	function execute() {
		$result = $this->getResult();

		$this->params = $this->extractRequestParams();

		$this->userLDAP = new OpenStackNovaUser();
		$this->canExecute();
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $this->params['project'] );
		$this->userNova->setRegion( $this->params['region'] );
		$address = $this->userNova->getAddress( $this->params['id'] );
		if ( !$address ) {
			$this->dieUsage( 'Address specified does not exist.', 'openstackmanager-nonexistenthost' );
		}
		$ipAddr = $address->getPublicIp();
		$instanceId = $address->getInstanceId();

		$subaction = $this->params['subaction'];

		switch( $subaction ) {
			case 'disassociate':
				$success = $this->userNova->disassociateAddress(
					$instanceId,
					$ipAddr
				);

				if ( ! $success ) {
					$this->dieUsage( 'Failed to disassociate address', 'openstackmanager-disassociateaddressfailed' );
				}

				$result->addValue( null, $this->getModuleName(), array ( 'addressstate' => 'free' ) );
				break;
		}
	}

	// Face parameter.
	public function getAllowedParams() {
		return array(
			'subaction' => array(
				self::PARAM_TYPE => array(
					'disassociate',
				),
				self::PARAM_REQUIRED => true,
			),
			'id' => array(
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			),
			'project' => array(
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			),
			'region' => array(
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			),
			'token' => array(
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			),
		);
	}

	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'subaction' => 'The subaction to perform.',
			'address' => 'The ID of the Nova IP address on which we will perform the action.',
			'project' => 'The project in which the address exists.',
			'region' => 'The region of the currently-associated instance.',
		) );
	}

	public function getDescription() {
		return 'Perform actions on Nova IP addresses.';
	}

	public function getExamples() {
		return array(
			'api.php?action=novaaddress&subaction=disassociate&address=7&project=testing&region=mars'
			=> 'Disassociate IP 208.80.153.198 in project testing',
		);
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	public function getTokenSalt() {
		return '';
	}

	public function mustBePosted() {
		return true;
	}
}

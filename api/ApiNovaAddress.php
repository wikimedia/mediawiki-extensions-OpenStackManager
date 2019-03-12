<?php
class ApiNovaAddress extends ApiBase {
	public $userLDAP;
	public $userNova;
	public $params;

	public function canExecute() {
		if ( !$this->userLDAP->exists() ) {
			$this->dieWithError( 'openstackmanager-nonovacred' );
		}

		$projects = explode( ',', $this->params['project'] );

		foreach ( $projects as $p ) {
			if ( !$this->userLDAP->inProject( $p ) ) {
				$this->dieWithError( [ 'openstackmanager-noaccount', wfEscapeWikiText( $p ) ] );
			}

			if ( !$this->userLDAP->inRole( 'projectadmin', $p ) ) {
				$this->dieWithError( [
					'openstackmanager-needrole',
					'projectadmin',
					wfEscapeWikiText( $p ),
				] );
			}
		}
	}

	public function execute() {
		$result = $this->getResult();

		$this->params = $this->extractRequestParams();

		$this->userLDAP = new OpenStackNovaUser( $this->getUser()->getName() );
		$this->canExecute();
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $this->params['project'] );
		$this->userNova->setRegion( $this->params['region'] );
		$address = $this->userNova->getAddress( $this->params['id'] );
		if ( !$address ) {
			$this->dieWithError( 'openstackmanager-nonexistenthost' );
		}
		$ipAddr = $address->getPublicIp();
		$instanceId = $address->getInstanceId();

		$subaction = $this->params['subaction'];

		switch ( $subaction ) {
			case 'disassociate':
				$success = $this->userNova->disassociateAddress(
					$instanceId,
					$ipAddr
				);

				if ( !$success ) {
					$this->dieWithError( [
						'openstackmanager-disassociateaddressfailed',
						wfEscapeWikiText( $ipAddr )
					] );
				}

				$result->addValue( null, $this->getModuleName(), [ 'addressstate' => 'free' ] );
				break;
		}
	}

	/**
	 * Face parameter
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'subaction' => [
				self::PARAM_TYPE => [
					'disassociate',
				],
				self::PARAM_REQUIRED => true,
			],
			'id' => [
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			],
			'project' => [
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			],
			'region' => [
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			],
			'token' => [
				self::PARAM_TYPE => 'string',
				self::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=novaaddress&subaction=disassociate&id=7&project=testing&region=mars'
				=> 'apihelp-novaaddress-example-1',
		];
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

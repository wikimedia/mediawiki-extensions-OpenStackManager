<?php
class ApiNovaAddress extends ApiBase {
	public $userLDAP;
	public $userNova;
	public $params;

	public function canExecute() {
		if ( !$this->userLDAP->exists() ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( 'openstackmanager-nonovacred' );
			} else {
				$this->dieUsage(
					'No credentials found for your account.', 'openstackmanager-nonovacred'
				);
			}
		}

		$projects = explode( ',', $this->params['project'] );

		foreach ( $projects as $p ) {
			if ( !$this->userLDAP->inProject( $p ) ) {
				if ( is_callable( [ $this, 'dieWithError' ] ) ) {
					$this->dieWithError( [ 'openstackmanager-noaccount', wfEscapeWikiText( $p ) ] );
				} else {
					$this->dieUsage(
						'User account is not in the project specified.',
						'openstackmanager-noaccount'
					);
				}
			}

			if ( !$this->userLDAP->inRole( 'projectadmin', $p ) ) {
				if ( is_callable( [ $this, 'dieWithError' ] ) ) {
					$this->dieWithError( [
						'openstackmanager-needrole',
						'projectadmin',
						wfEscapeWikiText( $p ),
					] );
				} else {
					$this->dieUsage(
						'User account is not in the projectadmin role.', 'openstackmanager-needrole'
					);
				}
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
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( 'openstackmanager-nonexistenthost' );
			} else {
				$this->dieUsage(
					'Address specified does not exist.', 'openstackmanager-nonexistenthost'
				);
			}
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
					if ( is_callable( [ $this, 'dieWithError' ] ) ) {
						$this->dieWithError( [
							'openstackmanager-disassociateaddressfailed',
							wfEscapeWikiText( $ipAddr )
						] );
					} else {
						$this->dieUsage(
							'Failed to disassociate address',
							'openstackmanager-disassociateaddressfailed'
						);
					}
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
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), [
			'subaction' => 'The subaction to perform.',
			'id' => 'The ID of the Nova IP address on which we will perform the action.',
			'project' => 'The project in which the address exists.',
			'region' => 'The region of the currently-associated instance.',
		] );
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Perform actions on Nova IP addresses.';
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return [
			'api.php?action=novaaddress&subaction=disassociate' .
				'&id=7&project=testing&region=mars'
			=> 'Disassociate IP 208.80.153.198 in project testing',
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

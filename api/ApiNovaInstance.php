<?php
class ApiNovaInstance extends ApiBase {
	public $userLDAP;
	public $userNova;
	public $params;

	public function canExecute() {
		if ( !$this->userLDAP->exists() ) {
			$this->dieWithError( 'openstackmanager-nonovacred' );
		}
		if ( !$this->userLDAP->inProject( $this->params['project'] ) ) {
			$this->dieWithError( [
				'openstackmanager-noaccount', wfEscapeWikiText( $this->params['project'] )
			] );
		}
		if ( !$this->userLDAP->inRole( 'projectadmin', $this->params['project'] ) ) {
			$this->dieWithError( [
				'openstackmanager-needrole',
				'projectadmin',
				wfEscapeWikiText( $this->params['project'] ),
			] );
		}
	}

	function execute() {
		$this->params = $this->extractRequestParams();

		$this->userLDAP = new OpenStackNovaUser( $this->getUser()->getName() );
		$this->canExecute();
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $this->params['project'] );
		$this->userNova->setRegion( $this->params['region'] );

		switch ( $this->params['subaction'] ) {
		case 'reboot':
			$success = $this->userNova->rebootInstance( $this->params['instanceid'] );
			if ( !$success ) {
				$this->dieWithError( [
					'openstackmanager-rebootinstancefailed',
					wfEscapeWikiText( $this->params['instanceid'] )
				] );
			}
			$instance = $this->userNova->getInstance( $this->params['instanceid'] );
			if ( $instance ) {
				$this->getResult()->addValue(
					null,
					$this->getModuleName(),
					[ 'instancestate' => $instance->getInstanceState() ]
				);
			}
			break;
		case 'consoleoutput':
			$output = $this->userNova->getConsoleOutput( $this->params['instanceid'] );
			$this->getResult()->addValue(
				null, $this->getModuleName(), [ 'consoleoutput' => $output ]
			);
			break;
		case 'delete':
			$instanceOSID = $this->params['instanceid'];
			$instance = $this->userNova->getInstance( $instanceOSID );
			if ( !$instance ) {
				$this->dieWithError( 'openstackmanager-nonexistenthost' );
			}
			$result = $instance->deleteInstance( $this->userNova );
			if ( !$result ) {
				$this->dieWithError( [
					'openstackmanager-deleteinstancefailed',
					$instance->getInstanceId(),
					wfEscapeWikiText( $instance->getInstanceName() ),
				] );
			}

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
				ApiBase::PARAM_TYPE => [
					'reboot',
					'consoleoutput',
					'delete',
				],
				ApiBase::PARAM_REQUIRED => true
			],
			'instanceid' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'project' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'region' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'token' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=novainstance&subaction=reboot&instanceid=' .
				'eb195097-8539-4e66-b0b5-be8347d8caec&project=testing&region=mars&token=123ABC'
				=> 'apihelp-novainstance-example-1',
			'action=novainstance&subaction=consoleoutput&instanceid=' .
				'eb195097-8539-4e66-b0b5-be8347d8caec&project=testing&region=mars&token=123ABC'
				=> 'apihelp-novainstance-example-2',
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

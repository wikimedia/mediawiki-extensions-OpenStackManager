<?php
class ApiNovaServiceGroups extends ApiBase {
	public $userLDAP;
	public $params;

	public function execute() {
		$this->params = $this->extractRequestParams();

		switch ( $this->params['subaction'] ) {
		case 'getservicegroups':
			$project = OpenStackNovaProject::getProjectByName( $this->params['project'] );
			$project->fetchServiceGroups();
			$serviceGroups = $project->getServiceGroups();
			$data = [];
			foreach ( $serviceGroups as $serviceGroup ) {
				$serviceGroupName = $serviceGroup->getGroupName();
				if ( $this->params['shellmembers'] ) {
					$data[$serviceGroupName]['members'] = $serviceGroup->getUidMembers();
				} else {
					$data[$serviceGroupName]['members'] = $serviceGroup->getMembers();
				}
				$this->getResult()->setIndexedTagName(
					$data[$serviceGroupName]['members'], 'member'
				);
			}
			$this->getResult()->addValue( null, $this->getModuleName(), $data );
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
					'getservicegroups',
				],
				ApiBase::PARAM_REQUIRED => true
			],
			'project' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'shellmembers' => [
				ApiBase::PARAM_TYPE => 'boolean',
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
			'action=novaservicegroups&subaction=getservicegroups&project=testing'
				=> 'apihelp-novaservicegroups-example-1',
		];
	}

	public function mustBePosted() {
		return false;
	}

}

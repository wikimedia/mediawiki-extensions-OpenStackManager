<?php
class ApiNovaServiceGroups extends ApiBase {
	var $userLDAP;
	var $params;

	function execute() {
		$this->params = $this->extractRequestParams();

		switch( $this->params['subaction'] ) {
		case 'getservicegroups':
			$project = OpenStackNovaProject::getProjectByName( $this->params['project'] );
			$project->fetchServiceGroups();
			$serviceGroups = $project->getServiceGroups();
			$data = array();
			foreach ( $serviceGroups as $serviceGroup ) {
				$serviceGroupName = $serviceGroup->getGroupName();
				if ( $this->params['shellmembers'] ) {
					$data[$serviceGroupName]['members'] = $serviceGroup->getUidMembers();
				} else {
					$data[$serviceGroupName]['members'] = $serviceGroup->getMembers();
				}
				$this->getResult()->setIndexedTagName( $data[$serviceGroupName]['members'], 'member' );
			}
			$this->getResult()->addValue( null, $this->getModuleName(), $data );
			break;
		}

	}

	public function getPossibleErrors() {
		return array(
			array( 'openstackmanager-noaccount' ),
			array( 'openstackmanager-needrole' )
		);
	}

	// Face parameter.
	public function getAllowedParams() {
		return array(
			'subaction' => array (
				ApiBase::PARAM_TYPE => array(
					'getservicegroups',
				),
				ApiBase::PARAM_REQUIRED => true
			),
			'project' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			),
			'shellmembers' => array (
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_REQUIRED => false
			),
		);
	}
 
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'subaction' => 'The subaction to perform.',
			'project' => 'The project to perform the subaction upon',
			'shellmembers' => 'Return shell account names for service group members, rather than MediaWiki usernames',
		) );
	}

	public function getDescription() {
		return 'Gets information on service groups.';
	}

	public function getExamples() {
		return array(
			'api.php?action=novaservicegroups&subaction=getservicegroups&project=testing'
			=> 'Get all service groups in project testing',
		);
	}

	public function mustBePosted() {
		return false;
	}

}

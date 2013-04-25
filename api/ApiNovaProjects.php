<?php
class ApiNovaProjects extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();

		$projectNames = array();
		if ( array_key_exists( 'getall', $params ) && $params['getall'] ) {
			$projects = OpenStackNovaProject::getAllProjects();
			foreach ( $projects as $project ) {
				$projectNames[] = $project->getProjectName();
			}
		} else {
			$userLDAP = new OpenStackNovaUser();
			$projectNames = $userLDAP->getProjects();
		}

		$this->getResult()->setIndexedTagName( $projectNames, 'project' );
		$this->getResult()->addValue( null, $this->getModuleName(), $projectNames );
	}

	public function getAllowedParams() {
		return array(
			'getall' => array (
				ApiBase::PARAM_TYPE => 'boolean',
			),
		);
	}

	public function getParamDescription() {
		return array(
			'getall' => 'Fetch all projects, not just the projects associated with this user.',
		);
	}

	public function getDescription() {
		return array(
			'Get a list of OpenStack projects for this user or a list of all projects.',
		);
	}

	public function getExamples() {
		return array(
			'api.php?action=novaprojects&getall=true',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': 1.0';
	}

}

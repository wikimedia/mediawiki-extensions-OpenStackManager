<?php
class ApiNovaProjects extends ApiBase {
	public $userLDAP;
	public $params;

	function execute() {
		$this->params = $this->extractRequestParams();
		$this->userLDAP = new OpenStackNovaUser();

		switch( $this->params['subaction'] ) {
		case 'getall':
			if ( isset( $this->params['project'] ) ) {
				$projects = array( OpenStackNovaProject::getProjectByName( $this->params['project'] ) );
			} else {
				$projects = OpenStackNovaProject::getAllProjects();
			}
			$data = array();
			foreach ( $projects as $project ) {
				$project->fetchProjectInfo();
				if ( !$project->loaded ) {
					continue;
				}
				$projectName = $project->getProjectName();
				$data[$projectName]['members'] = $project->getMembers();
				$data[$projectName]['roles'] = array();
				foreach ( $project->getRoles() as $role ) {
					$roleName = $role->getRoleName();
					$data[$projectName]['roles'][$roleName] = array( 'members' => $role->getMembers() );
					$this->getResult()->setIndexedTagName( $data[$projectName]['roles'][$roleName]['members'], 'member' );
				}
				$this->getResult()->setIndexedTagName( $data[$projectName]['members'], 'member' );
				$this->getResult()->setIndexedTagName( $data[$projectName]['roles'], 'roles' );
			}
			$this->getResult()->addValue( null, $this->getModuleName(), $data );
			break;
		case 'getuser':
			$data = array();
			if ( $this->params['username'] ) {
				$user = new OpenStackNovaUser( $this->params['username'] );
			} else {
				$user = $this->userLDAP;
			}
			$projectNames = $user->getProjects();
			foreach ( $projectNames as $projectName ) {
				$project = OpenStackNovaProject::getProjectByName( $projectName );
				$project->fetchProjectInfo();
				if ( !$project->loaded ) {
					continue;
				}
				$projectName = $project->getProjectName();
				$data[$projectName] = array();
				$data[$projectName]['roles'] = array();
				foreach ( $project->getRoles() as $role ) {
					if ( $role->userInRole( $user ) ) {
						$data[$projectName]['roles'][] = $role->getRoleName();
					}
				}
				$this->getResult()->setIndexedTagName( $data[$projectName]['roles'], 'role' );
			}
			$this->getResult()->setIndexedTagName( $data, 'project' );
			$this->getResult()->addValue( null, $this->getModuleName(), $data );
			break;
		}

	}

	// Face parameter.
	public function getAllowedParams() {
		return array(
			'subaction' => array (
				ApiBase::PARAM_TYPE => array(
					'getall',
					'getuser',
				),
				ApiBase::PARAM_REQUIRED => true
			),
			'project' => array (
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			),
			'username' => array (
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
			'username' => 'The username to get information about',
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
			'api.php?action=novaprojects&subaction=getall'
			=> 'Get all projects',
			'api.php?action=novaprojects&subaction=getuser'
			=> 'Get all projects and role info for the logged-in user',
			'api.php?action=novaprojects&subaction=getuser&username=testuser'
			=> 'Get all projects and role info for testuser',
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=novaprojects&subaction=getall'
				=> 'apihelp-novaprojects-example-1',
			'action=novaprojects&subaction=getuser'
				=> 'apihelp-novaprojects-example-2',
			'action=novaprojects&subaction=getuser&username=testuser'
				=> 'apihelp-novaprojects-example-3',
		);
	}

	public function mustBePosted() {
		return false;
	}

}

<?php
class ApiNovaProjects extends ApiBase {
	public $userLDAP;
	public $params;

	public function execute() {
		$this->params = $this->extractRequestParams();
		$this->userLDAP = new OpenStackNovaUser( $this->getUser()->getName() );

		switch ( $this->params['subaction'] ) {
		case 'getall':
			if ( isset( $this->params['project'] ) ) {
				$projects = [ OpenStackNovaProject::getProjectByName( $this->params['project'] ) ];
			} else {
				$projects = OpenStackNovaProject::getAllProjects();
			}
			$data = [];
			foreach ( $projects as $project ) {
				$project->fetchProjectInfo();
				if ( !$project->loaded ) {
					continue;
				}
				$projectName = $project->getProjectName();
				$data[$projectName]['members'] = $project->getMembers();
				$data[$projectName]['roles'] = [];
				foreach ( $project->getRoles() as $role ) {
					$roleName = $role->getRoleName();
					$data[$projectName]['roles'][$roleName] = [ 'members' => $role->getMembers() ];
					$this->getResult()->setIndexedTagName(
						$data[$projectName]['roles'][$roleName]['members'], 'member'
					);
				}
				$this->getResult()->setIndexedTagName( $data[$projectName]['members'], 'member' );
				$this->getResult()->setIndexedTagName( $data[$projectName]['roles'], 'roles' );
			}
			$this->getResult()->addValue( null, $this->getModuleName(), $data );
			break;
		case 'getuser':
			$data = [];
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
				$data[$projectName] = [];
				$data[$projectName]['roles'] = [];
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

	/**
	 * Face parameter
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'subaction' => [
				ApiBase::PARAM_TYPE => [
					'getall',
					'getuser',
				],
				ApiBase::PARAM_REQUIRED => true
			],
			'project' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false
			],
			'username' => [
				ApiBase::PARAM_TYPE => 'string',
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
			'action=novaprojects&subaction=getall'
				=> 'apihelp-novaprojects-example-1',
			'action=novaprojects&subaction=getuser'
				=> 'apihelp-novaprojects-example-2',
			'action=novaprojects&subaction=getuser&username=testuser'
				=> 'apihelp-novaprojects-example-3',
		];
	}

	public function mustBePosted() {
		return false;
	}

}

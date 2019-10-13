<?php

class ApiListNovaProjects extends ApiQueryGeneratorBase {

	public function __construct( ApiQuery $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'np' );
	}

	public function execute() {
		$this->run();
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run();
	}

	public function run() {
		$projects = OpenStackNovaProject::getAllProjects();
		foreach ( $projects as $project ) {
			$this->getResult()->addValue(
				[ 'query', $this->getModuleName() ],
				null,
				$project->getName()
			);
		}

		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			$this->getResult()->addIndexedTagName( [ 'query', $this->getModuleName() ], 'project' );
		} else {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->getResult()->setIndexedTagName_internal( [ 'query', $this->getModuleName() ], 'project' );
		}
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=novaprojects'
				=> 'apihelp-query+novaprojects-example-1',
		];
	}
}

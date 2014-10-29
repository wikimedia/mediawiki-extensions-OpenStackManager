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
				array( 'query', $this->getModuleName() ),
				null,
				$project->getName()
			);
		}

		$this->getResult()->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'project' );
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return array(
			'Returns a list of all the known projects'
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return 'api.php?action=query&list=novaprojects';
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=query&list=novaprojects'
				=> 'apihelp-query+novaprojects-example-1',
		);
	}
}

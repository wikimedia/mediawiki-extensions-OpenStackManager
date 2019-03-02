<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class UpdateProjectPages extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Update all project pages in the wiki";
	}

	public function execute() {
		$projects = OpenStackNovaProject::getAllProjects();
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			$project->fetchProjectInfo();
			$this->output( "Running project : " . $projectName . "\n" );
			$project->editArticle();
		}

		$this->output( "Done.\n" );
	}

}

$maintClass = UpdateProjectPages::class;
require_once RUN_MAINTENANCE_IF_MAIN;

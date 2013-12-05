<?php

/**
 * Special page to interact with a Nova instance.
 *
 * @file
 * @ingroup Extensions
 */

/**
 * Class to handle [[Special:NovaResources]].
 *
 * This page lists things that are 'owned' by the current users:
 *   Projects are incuded if the user is a project admin, and
 *   instances are listed if the user created the instance.
 *
 */
class SpecialNovaResources extends SpecialNova {
	/**
	 * @var OpenStackNovaController
	 */
	private $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	private $userLDAP;

	function __construct() {
		parent::__construct( 'NovaResources' );
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();
		if ( ! $this->userLDAP->exists() ) {
			$this->noCredentials();
			return;
		}
		$this->checkTwoFactor();
		$project = $this->getRequest()->getVal( 'project' );
		$region = $this->getRequest()->getVal( 'region' );
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $project );
		$this->userNova->setRegion( $region );

		$this->listInstances();
	}

	/**
	 * Default action
	 * @return void
	 */
	function listInstances() {
		$this->setHeaders();
		$this->getOutput()->addModules( 'ext.openstack.Instance' );
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-ownedprojects' ) );

		$projects = OpenStackNovaProject::getProjectsByName( $this->userLDAP->getProjects() );

		$instanceOut = '';
		$ownedProjects = array();
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			if ( $this->userLDAP->inRole( 'projectadmin', $projectName ) ) {
				$ownedProjects[] = $projectName;
			}
			$projectactions = array( 'projectadmin' => array() );
			$regions = '';
			$this->userNova->setProject( $projectName );
			$hasInstances = false;
			foreach ( $this->userNova->getRegions( 'compute' ) as $region ) {
				$regionactions = null;
				$instances = $this->getInstances( $projectName, $region );
				if ( $instances ) {
					$hasInstances = true;
				}
				$regions .= $this->createRegionSection( $region, $projectName, $regionactions, $instances );
			}
			if ( $hasInstances ) {
			    $instanceOut .= $this->createProjectSection( $projectName, $projectactions, $regions );
			}
		}

		$out = '';

		foreach ( $ownedProjects as $ownedProject ) {
			$projectNameOut = $this->createResourceLink( $ownedProject );
			$out .= $projectNameOut . " ";
		}

		$out .= Html::rawElement( 'h1', array(), $this->msg( 'openstackmanager-ownedinstances' )->text() );
		$out .= $instanceOut;


		$this->getOutput()->addHTML( $out );
	}

	function getInstances( $projectName, $region ) {
		global $wgMemc;

		$this->userNova->setRegion( $region );
		$headers = array( 'openstackmanager-instancename', 'openstackmanager-instanceid', 'openstackmanager-instancestate', 'openstackmanager-instanceip', 'openstackmanager-projectname', 'openstackmanager-launchtime', 'openstackmanager-instancecreator' );
		$instances = $this->userNova->getInstances();
		$instanceRows = array();
		/**
		 * @var $instance OpenStackNovaInstance
		 */
		foreach ( $instances as $instance ) {

			# Only display instances created by the current user.
			if ( $instance->getInstanceCreator() != $this->userLDAP->getUid() ) {
				continue;
			}

			$instanceRow = array();
			$this->pushResourceColumn( $instanceRow, $instance->getInstanceName(), array( 'class' => 'novainstancename' ) );
			$this->pushRawResourceColumn( $instanceRow, $this->createResourceLink( $instance->getInstanceId() ), array( 'class' => 'novainstanceid' ) );
			$state = $instance->getInstanceState();
			$taskState = $instance->getInstanceTaskState();
			if ( $taskState ) {
				$stateDisplay = "$state ($taskState)";
			} else {
				$stateDisplay = $state;
			}
			$this->pushResourceColumn( $instanceRow, $stateDisplay, array( 'class' => 'novainstancestate' ) );
			$this->pushRawResourceColumn( $instanceRow, $this->createResourceList( $instance->getInstancePrivateIPs() ) );
			$this->pushResourceColumn( $instanceRow, $projectName );
			$this->pushResourceColumn( $instanceRow, $instance->getLaunchTime() );
			$this->pushResourceColumn( $instanceRow, $instance->getInstanceCreator() );
			$actions = array();
			$instanceDataAttributes = array(
				'data-osid' => $instance->getInstanceOSId(),
				'data-id' => $instance->getInstanceId(),
				'data-name' => $instance->getInstanceName(),
				'data-project' => $projectName,
				'data-region' => $region,
				'class' => 'novainstanceaction',
			);
			$instanceRows[] = $instanceRow;
		}
		if ( $instanceRows ) {
			return $this->createResourceTable( $headers, $instanceRows );
		} 
		return '';
	}
}

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

	function __construct() {
		parent::__construct( 'NovaResources' );
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser( $this->getUser()->getName() );
		if ( !$this->userLDAP->exists() ) {
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

		$projects = OpenStackNovaProject::getProjectsByName( $this->userLDAP->getProjects() );

		$instanceOut = '';
		$ownedProjects = [];
		$instanceCount = 0;
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			$instancesInProject = 0;
			if ( $this->userLDAP->inRole( 'projectadmin', $projectName ) ) {
				$ownedProjects[] = $projectName;
			}
			$projectactions = [ 'projectadmin' => [] ];
			$regions = '';
			$this->userNova->setProject( $projectName );
			foreach ( $this->userNova->getRegions( 'compute' ) as $region ) {
				$regionactions = [];
				$thisCount = 0;
				$instances = $this->getInstances( $projectName, $region, $thisCount );
				$instancesInProject += $thisCount;
				if ( $thisCount > 0 ) {
					$regions .= $this->createRegionSection(
						$region, $projectName, $regionactions, $instances
					);
				}
			}
			if ( $instancesInProject ) {
				$instanceOut .= $this->createProjectSection(
					$projectName, $projectactions, $regions
				);
				$instanceCount += $instancesInProject;
			} else {
			}
		}

		$out = '';

		if ( $ownedProjects ) {
			$this->getOutput()->setPageTitle(
				$this->msg( 'openstackmanager-ownedprojects', count( $ownedProjects ) )
			);
			foreach ( $ownedProjects as $ownedProject ) {
				$projectNameOut = $this->createResourceLink( $ownedProject );
				$out .= $projectNameOut . " ";
			}
		} else {
			$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-noownedprojects' ) );
		}

		if ( $instanceCount ) {
			$out .= Html::element( 'h1', [],
				$this->msg( 'openstackmanager-ownedinstances', $instanceCount )->text()
			);
			$out .= $instanceOut;
		} else {
			$out .= Html::element( 'h1', [],
				$this->msg( 'openstackmanager-noownedinstances' )->text()
			);
		}

		$this->getOutput()->addHTML( $out );
	}

	function getInstances( $projectName, $region, &$instanceCount ) {
		$this->userNova->setRegion( $region );
		$headers = [
			'openstackmanager-instancename',
			'openstackmanager-instanceid',
			'openstackmanager-instancestate',
			'openstackmanager-instanceip',
			'openstackmanager-projectname',
			'openstackmanager-launchtime',
			'openstackmanager-instancecreator'
		];
		$instances = $this->userNova->getInstances();
		$instanceRows = [];
		$instanceCount = 0;
		/**
		 * @var $instance OpenStackNovaInstance
		 */
		foreach ( $instances as $instance ) {
			# Only display instances created by the current user.
			if ( $instance->getInstanceCreator() != $this->userLDAP->getUid() ) {
				continue;
			}

			$instanceRow = [];
			$this->pushResourceColumn(
				$instanceRow, $instance->getInstanceName(), [ 'class' => 'novainstancename' ]
			);
			$host = $instance->getHost();
			if ( $host ) {
				'@phan-var OpenStackNovaPrivateHost $host';
				$this->pushRawResourceColumn(
					$instanceRow,
					$this->createResourceLink( $host->getFullyQualifiedHostName() ),
					[ 'class' => 'novainstanceid' ]
				);
			} else {
				$this->pushResourceColumn(
					$instanceRow, $instance->getInstanceId(), [ 'class' => 'novainstanceid' ]
				);
			}
			$state = $instance->getInstanceState();
			$taskState = $instance->getInstanceTaskState();
			if ( $taskState ) {
				$stateDisplay = "$state ($taskState)";
			} else {
				$stateDisplay = $state;
			}
			$this->pushResourceColumn(
				$instanceRow, $stateDisplay, [ 'class' => 'novainstancestate' ]
			);
			$this->pushRawResourceColumn(
				$instanceRow, $this->createResourceList( $instance->getInstancePrivateIPs() )
			);
			$this->pushResourceColumn( $instanceRow, $projectName );
			$this->pushResourceColumn( $instanceRow, $instance->getLaunchTime() );
			$this->pushResourceColumn( $instanceRow, $instance->getInstanceCreator() );
			$actions = [];
			$instanceDataAttributes = [
				'data-osid' => $instance->getInstanceOSId(),
				'data-id' => $instance->getInstanceId(),
				'data-name' => $instance->getInstanceName(),
				'data-project' => $projectName,
				'data-region' => $region,
				'class' => 'novainstanceaction',
			];
			$instanceRows[] = $instanceRow;
			$instanceCount += 1;
		}
		if ( $instanceRows ) {
			return $this->createResourceTable( $headers, $instanceRows );
		}
		return '';
	}

	protected function getGroupName() {
		return 'nova';
	}
}

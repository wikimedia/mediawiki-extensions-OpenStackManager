<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaVolume extends SpecialNova {
	/**
	 * @var OpenStackNovaController
	 */
	public $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	public $userLDAP;

	function __construct() {
		parent::__construct( 'NovaVolume' );
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

		$action = $this->getRequest()->getVal( 'action' );

		if ( $action === "create" ) {
			if ( !$this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->createVolume();
		} elseif ( $action === "delete" ) {
			if ( !$this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->deleteVolume();
		} elseif ( $action === "attach" ) {
			if ( !$this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->attachVolume();
		} elseif ( $action === "detach" ) {
			if ( !$this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->detachVolume();
		} else {
			$this->listVolumes();
		}
	}

	/**
	 * @return bool
	 */
	function createVolume() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-createvolume' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$volumeInfo = [];
		$volumeInfo['volumename'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-volumename',
			'validation-callback' => [ $this, 'validateText' ],
			'default' => '',
			'section' => 'volume/info',
			'name' => 'volumename',
		];
		$volumeInfo['volumedescription'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-volumedescription',
			'default' => '',
			'section' => 'volume/info',
			'name' => 'volumedescription',
		];
		$volumeInfo['volumeSize'] = [
			'type' => 'int',
			'section' => 'volume/info',
			'label-message' => 'openstackmanager-volumesize',
			'name' => 'volumeSize',
		];
		$volumeInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$volumeInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$volumeInfo['action'] = [
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		];

		$volumeForm = new HTMLForm(
			$volumeInfo,
			$this->getContext(),
			'openstackmanager-novavolume'
		);
		$volumeForm->setSubmitID( 'openstackmanager-novavolume-createvolumesubmit' );
		$volumeForm->setSubmitCallback( [ $this, 'tryCreateSubmit' ] );
		$volumeForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteVolume() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-deletevolume' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$volumeid = $this->getRequest()->getText( 'volumeid' );
		if ( !$this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletevolumequestion', $volumeid );
		}
		$volumeInfo = [];
		$volumeInfo['volumeid'] = [
			'type' => 'hidden',
			'default' => $volumeid,
			'name' => 'volumeid',
		];
		$volumeInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$volumeInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$volumeInfo['action'] = [
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		];
		$volumeForm = new HTMLForm(
			$volumeInfo,
			$this->getContext(),
			'openstackmanager-novavolume'
		);
		$volumeForm->setSubmitID( 'novavolume-form-deletevolumesubmit' );
		$volumeForm->setSubmitCallback( [ $this, 'tryDeleteSubmit' ] );
		$volumeForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function attachVolume() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-attachvolume' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$instances = $this->userNova->getInstances();
		$instance_keys = [];
		foreach ( $instances as $instance ) {
			if ( $instance->getProject() === $project ) {
				$instancename = $instance->getInstanceName();
				$instanceid = $instance->getInstanceId();
				$instance_keys[$instancename] = $instanceid;
			}
		}
		$volumeInfo = [];
		$volumeInfo['volumeinfo'] = [
			'type' => 'info',
			'label-message' => 'openstackmanager-volumename',
			'default' => $this->getRequest()->getText( 'volumeid' ),
			'section' => 'volume/info',
			'name' => 'volumeinfo',
		];
		$volumeInfo['volumeid'] = [
			'type' => 'hidden',
			'default' => $this->getRequest()->getText( 'volumeid' ),
			'name' => 'volumeid',
		];
		$volumeInfo['volumedescription'] = [
			'type' => 'info',
			'label-message' => 'openstackmanager-volumedescription',
			'section' => 'volume/info',
			'name' => 'volumedescription',
		];
		$volumeInfo['instanceid'] = [
			'type' => 'select',
			'label-message' => 'openstackmanager-instancename',
			'options' => $instance_keys,
			'section' => 'volume/info',
			'name' => 'instanceid',
		];
		$volumeInfo['device'] = [
			'type' => 'select',
			'label-message' => 'openstackmanager-device',
			'options' => $this->getDrives(),
			'section' => 'volume/info',
			'name' => 'device',
		];
		$volumeInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$volumeInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$volumeInfo['action'] = [
			'type' => 'hidden',
			'default' => 'attach',
			'name' => 'action',
		];
		$volumeForm = new HTMLForm(
			$volumeInfo,
			$this->getContext(),
			'openstackmanager-novavolume'
		);
		$volumeForm->setSubmitID( 'novavolume-form-attachvolumesubmit' );
		$volumeForm->setSubmitCallback( [ $this, 'tryAttachSubmit' ] );
		$volumeForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function detachVolume() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-detachvolume' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$volumeInfo = [];
		$volumeInfo['volumeinfo'] = [
			'type' => 'info',
			'label-message' => 'openstackmanager-volumename',
			'default' => $this->getRequest()->getText( 'volumeid' ),
			'section' => 'volume/info',
			'name' => 'volumeinfo',
		];
		$volumeInfo['force'] = [
			'type' => 'toggle',
			'label-message' => 'openstackmanager-forcedetachment',
			'help-message' => 'openstackmanager-forcedetachmenthelp',
			'section' => 'volume/info',
			'name' => 'volumeinfo',
		];
		$volumeInfo['volumeid'] = [
			'type' => 'hidden',
			'default' => $this->getRequest()->getText( 'volumeid' ),
			'name' => 'volumeid',
		];
		$volumeInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$volumeInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$volumeInfo['action'] = [
			'type' => 'hidden',
			'default' => 'detach',
			'name' => 'action',
		];
		$volumeForm = new HTMLForm(
			$volumeInfo,
			$this->getContext(),
			'openstackmanager-novavolume'
		);
		$volumeForm->setSubmitID( 'novavolume-form-detachvolumesubmit' );
		$volumeForm->setSubmitCallback( [ $this, 'tryDetachSubmit' ] );
		$volumeForm->show();

		return true;
	}

	/**
	 * @return void
	 */
	function listVolumes() {
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.openstack' );
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-volumelist' ) );

		if ( $this->getUser()->isAllowed( 'listall' ) ) {
			$projects = OpenStackNovaProject::getAllProjects();
		} else {
			$projects = OpenStackNovaProject::getProjectsByName( $this->userLDAP->getProjects() );
		}
		$this->showProjectFilter( $projects );
		$projectfilter = $this->getProjectFilter();
		if ( !$projectfilter ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-setprojectfilter' );
			return null;
		}

		$out = '';
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			if ( !in_array( $projectName, $projectfilter ) ) {
				continue;
			}
			$projectactions = [ 'projectadmin' => [] ];
			$regions = '';
			$this->userNova->setProject( $projectName );
			foreach ( $this->userNova->getRegions( 'compute' ) as $region ) {
				$regionactions = [
					'projectadmin' => [
						$this->createActionLink( 'openstackmanager-createvolume', [
							'action' => 'create',
							'project' => $projectName,
							'region' => $region
						] )
					]
				];
				$volumes = $this->getVolumes( $projectName, $region );
				$regions .= $this->createRegionSection(
					$region, $projectName, $regionactions, $volumes
				);
			}
			$out .= $this->createProjectSection( $projectName, $projectactions, $regions );
		}

		$this->getOutput()->addHTML( $out );
	}

	function getVolumes( $projectName, $region ) {
		$headers = [ 'openstackmanager-volumename',
			'openstackmanager-volumeid', 'openstackmanager-volumedescription',
			'openstackmanager-volumeattachmentinstance',
			'openstackmanager-volumeattachmentdevice', 'openstackmanager-volumeattachmentstatus',
			'openstackmanager-volumesize',
			'openstackmanager-volumecreationtime', 'openstackmanager-actions'
		];
		$this->userNova->setRegion( $region );
		$volumes = $this->userNova->getVolumes();
		$volumeRows = [];
		foreach ( $volumes as $volume ) {
			$volumeRow = [];
			$this->pushResourceColumn( $volumeRow, $volume->getVolumeName() );
			$volumeId = $volume->getVolumeId();
			$this->pushRawResourceColumn( $volumeRow, $this->createResourceLink( $volumeId ) );
			$this->pushResourceColumn( $volumeRow, $volume->getVolumeDescription() );
			$this->pushResourceColumn( $volumeRow, $volume->getAttachedInstanceId() );
			$this->pushResourceColumn( $volumeRow, $volume->getAttachedDevice() );
			$this->pushResourceColumn( $volumeRow, $volume->getAttachmentStatus() );
			$this->pushResourceColumn( $volumeRow, $volume->getVolumeSize() );
			$this->pushResourceColumn( $volumeRow, $volume->getVolumeCreationTime() );
			$actions = [];
			$actions[] = $this->createActionLink( 'openstackmanager-delete', [
				'action' => 'delete', 'project' => $projectName,
				'region' => $region, 'volumeid' => $volumeId
			] );
			$actions[] = $this->createActionLink( 'openstackmanager-attach', [
				'action' => 'attach', 'project' => $projectName,
				'region' => $region, 'volumeid' => $volumeId
			] );
			$actions[] = $this->createActionLink( 'openstackmanager-detach', [
				'action' => 'detach', 'project' => $projectName,
				'region' => $region, 'volumeid' => $volumeId
			] );
			$this->pushRawResourceColumn( $volumeRow, $this->createResourceList( $actions ) );
			$volumeRows[] = $volumeRow;
		}
		if ( $volumeRows ) {
			return $this->createResourceTable( $headers, $volumeRows );
		} else {
			return '';
		}
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		$volume = $this->userNova->createVolume(
			'',
			$formData['volumeSize'],
			$formData['volumename'],
			$formData['volumedescription']
		);
		if ( $volume ) {
			$this->getOutput()->addWikiMsg(
				'openstackmanager-createdvolume', $volume->getVolumeID()
			);
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createevolumefailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backvolumelist' )->escaped()
		);

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		$volume = $this->userNova->getVolume( $formData['volumeid'] );
		if ( !$volume ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistantvolume' );
			return true;
		}
		$volumeid = $volume->getVolumeId();
		$success = $this->userNova->deleteVolume( $volumeid );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedvolume', $volumeid );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletevolumefailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backvolumelist' )->escaped()
		);

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAttachSubmit( $formData, $entryPoint = 'internal' ) {
		$success = $this->userNova->attachVolume(
			$formData['volumeid'], $formData['instanceid'], $formData['device']
		);
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-attachedvolume' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-attachvolumefailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backvolumelist' )->escaped()
		);

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDetachSubmit( $formData, $entryPoint = 'internal' ) {
		if ( isset( $formData['force'] ) && $formData['force'] ) {
			$force = true;
		} else {
			$force = false;
		}
		$success = $this->userNova->detachVolume( $formData['volumeid'], $force );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-detachedvolume' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-detachvolumefailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backvolumelist' )->escaped()
		);

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * Return an array of drive devices
	 *
	 * @return string[]
	 */
	function getDrives() {
		$drives = [];
		foreach ( range( 'a', 'z' ) as $letter ) {
			$drive = '/dev/vd' . $letter;
			$drives[$drive] = $drive;
		}
		foreach ( range( 'a', 'z' ) as $letter ) {
			$drive = '/dev/vda' . $letter;
			$drives[$drive] = $drive;
		}

		return $drives;
	}

	protected function getGroupName() {
		return 'nova';
	}
}

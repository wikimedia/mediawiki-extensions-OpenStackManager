<?php
class SpecialNovaVolume extends SpecialNova {

	/**
	 * @var OpenStackNovaController
	 */
	var $adminNova, $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaVolume' );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;
		global $wgOpenStackManagerNovaAdminKeys;

		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return true;
		}
		$this->userLDAP = new OpenStackNovaUser();
		if ( ! $this->userLDAP->exists() ) {
			$this->noCredentials();
			return true;
		}
		$project = $wgRequest->getVal( 'project' );
		$userCredentials = $this->userLDAP->getCredentials( $project );
		$this->userNova = new OpenStackNovaController( $userCredentials );
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );

		$action = $wgRequest->getVal( 'action' );

		if ( $action == "create" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->createVolume();
		} else if ( $action == "delete" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->deleteVolume();
		} else if ( $action == "attach" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->attachVolume();
		} else if ( $action == "detach" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
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
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-createvolume' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$volumeInfo = array();
		$volumeInfo['volumename'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-volumename',
			'validation-callback' => array( $this, 'validateText' ),
			'default' => '',
			'section' => 'volume/info',
			'name' => 'volumename',
		);
		$volumeInfo['volumedescription'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-volumedescription',
			'default' => '',
			'section' => 'volume/info',
			'name' => 'volumedescription',
		);


		# Availability zone names can't be translated. Get the keys, and make an array
		# where the name points to itself as a value
		$availabilityZones = $this->adminNova->getAvailabilityZones();
		$availabilityZone_keys = array();
		foreach ( array_keys( $availabilityZones ) as $availabilityZone_key ) {
			$availabilityZone_keys["$availabilityZone_key"] = $availabilityZone_key;
		}
		$volumeInfo['availabilityZone'] = array(
			'type' => 'select',
			'section' => 'volume/info',
			'options' => $availabilityZone_keys,
			'label-message' => 'openstackmanager-availabilityzone',
			'name' => 'availabilityZone',
		);

		$volumeInfo['volumeSize'] = array(
			'type' => 'int',
			'section' => 'volume/info',
			'label-message' => 'openstackmanager-volumesize',
			'name' => 'volumeSize',
		);

		$volumeInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$volumeInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$volumeForm = new SpecialNovaVolumeForm( $volumeInfo, 'openstackmanager-novavolume' );
		$volumeForm->setTitle( SpecialPage::getTitleFor( 'NovaVolume' ) );
		$volumeForm->setSubmitID( 'openstackmanager-novavolume-createvolumesubmit' );
		$volumeForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$volumeForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteVolume() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-deletevolume' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$volumeid = $wgRequest->getText( 'volumeid' );
		if ( ! $wgRequest->wasPosted() ) {
			$wgOut->addWikiMsg( 'openstackmanager-deletevolumequestion', $volumeid );
		}
		$volumeInfo = array();
		$volumeInfo['volumeid'] = array(
			'type' => 'hidden',
			'default' => $volumeid,
			'name' => 'volumeid',
		);
		$volumeInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$volumeInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$volumeForm = new SpecialNovaVolumeForm( $volumeInfo, 'openstackmanager-novavolume' );
		$volumeForm->setTitle( SpecialPage::getTitleFor( 'NovaVolume' ) );
		$volumeForm->setSubmitID( 'novavolume-form-deletevolumesubmit' );
		$volumeForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$volumeForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function attachVolume() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-attachvolume' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$instances = $this->userNova->getInstances();
		$instance_keys = array();
		foreach ( $instances as $instance ) {
			if ( $instance->getOwner() == $project ) {
				$instancename = $instance->getInstanceName();
				$instanceid = $instance->getInstanceId();
				$instance_keys["$instancename"] = $instanceid;
			}
		}
		$volumeInfo = array();
		$volumeInfo['volumeinfo'] = array(
			'type' => 'info',
			'label-message' => 'openstackmanager-volumename',
			'default' => $wgRequest->getText( 'volumeid' ),
			'section' => 'volume/info',
			'name' => 'volumeinfo',
		);
		$volumeInfo['volumeid'] = array(
			'type' => 'hidden',
			'default' => $wgRequest->getText( 'volumeid' ),
			'name' => 'volumeid',
		);
		$volumeInfo['volumedescription'] = array(
			'type' => 'info',
			'label-message' => 'openstackmanager-volumedescription',
			'section' => 'volume/info',
			'name' => 'volumedescription',
		);
		$volumeInfo['instanceid'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-instancename',
			'options' => $instance_keys,
			'section' => 'volume/info',
			'name' => 'instanceid',
		);
		$volumeInfo['device'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-device',
			'options' => $this->getDrives(),
			'section' => 'volume/info',
			'name' => 'device',
		);
		$volumeInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$volumeInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'attach',
			'name' => 'action',
		);
		$volumeForm = new SpecialNovaVolumeForm( $volumeInfo, 'openstackmanager-novavolume' );
		$volumeForm->setTitle( SpecialPage::getTitleFor( 'NovaVolume' ) );
		$volumeForm->setSubmitID( 'novavolume-form-attachvolumesubmit' );
		$volumeForm->setSubmitCallback( array( $this, 'tryAttachSubmit' ) );
		$volumeForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function detachVolume() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-detachvolume' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$volumeInfo = array();
		$volumeInfo['volumeinfo'] = array(
			'type' => 'info',
			'label-message' => 'openstackmanager-volumename',
			'default' => $wgRequest->getText( 'volumeid' ),
			'section' => 'volume/info',
			'name' => 'volumeinfo',
		);
		$volumeInfo['force'] = array(
			'type' => 'toggle',
			'label-message' => 'openstackmanager-forcedetachment',
			'help-message' => 'openstackmanager-forcedetachmenthelp',
			'section' => 'volume/info',
			'name' => 'volumeinfo',
		);
		$volumeInfo['volumeid'] = array(
			'type' => 'hidden',
			'default' => $wgRequest->getText( 'volumeid' ),
			'name' => 'volumeid',
		);
		$volumeInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$volumeInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'detach',
			'name' => 'action',
		);
		$volumeForm = new SpecialNovaVolumeForm( $volumeInfo, 'openstackmanager-novavolume' );
		$volumeForm->setTitle( SpecialPage::getTitleFor( 'NovaVolume' ) );
		$volumeForm->setSubmitID( 'novavolume-form-detachvolumesubmit' );
		$volumeForm->setSubmitCallback( array( $this, 'tryDetachSubmit' ) );
		$volumeForm->show();

		return true;
	}
	/**
	 * @return void
	 */
	function listVolumes() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-volumelist' ) );

		$userProjects = $this->userLDAP->getProjects();
		$sk = $wgOut->getSkin();
		$out = '';
		$volumes = $this->adminNova->getVolumes();
		$header = Html::element( 'th', array(), wfMsg( 'openstackmanager-volumename' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumeid' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumedescription' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumestate' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumeattachmentinstance' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumeattachmentdevice' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumeattachmentstatus' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumesize' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumedeleteonvolumedelete' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-availabilityzone' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-volumecreationtime' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$projectArr = array();
		foreach ( $volumes as $volume ) {
			$project = $volume->getOwner();
			if ( ! in_array( $project, $userProjects ) ) {
				continue;
			}
			$volumeOut = Html::element( 'td', array(), $volume->getVolumeName() );
			$volumeId = $volume->getVolumeId();
			$volumeId = htmlentities( $volumeId );
			$title = Title::newFromText( $volumeId, NS_NOVA_RESOURCE );
			$volumeIdLink = $sk->link( $title, $volumeId );
			$volumeOut .= Html::rawElement( 'td', array(), $volumeIdLink );
			$volumeOut .= Html::element( 'td', array(), $volume->getVolumeDescription() );
			$volumeOut .= Html::element( 'td', array(), $volume->getVolumeStatus() );
			$volumeOut .= Html::element( 'td', array(), $volume->getAttachedInstanceId() );
			$volumeOut .= Html::element( 'td', array(), $volume->getAttachedDevice() );
			$volumeOut .= Html::element( 'td', array(), $volume->getAttachmentStatus() );
			$volumeOut .= Html::element( 'td', array(), $volume->getVolumeSize() );
			$volumeOut .= Html::element( 'td', array(), $volume->deleteOnInstanceDeletion() );
			$volumeOut .= Html::element( 'td', array(), $volume->getVolumeAvailabilityZone() );
			$volumeOut .= Html::element( 'td', array(), $volume->getVolumeCreationTime() );
			$msg = wfMsgHtml( 'openstackmanager-delete' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
								  array( 'action' => 'delete',
									   'project' => $project,
									   'volumeid' => $volume->getVolumeId() ) );
			$actions = Html::rawElement( 'li', array(), $link );
			#$msg = wfMsgHtml( 'openstackmanager-rename' );
			#$actions .= $sk->link( $this->getTitle(), $msg, array(),
			#					   array( 'action' => 'rename',
			#							'project' => $project,
			#							'volumeid' => $volume->getVolumeId() ) );
			$msg = wfMsgHtml( 'openstackmanager-attach' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
								   array( 'action' => 'attach',
										'project' => $project,
										'volumeid' => $volume->getVolumeId() ) );
			$actions .= Html::rawElement( 'li', array(), $link );
			$msg = wfMsgHtml( 'openstackmanager-detach' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
								   array( 'action' => 'detach',
										'project' => $project,
										'volumeid' => $volume->getVolumeId() ) );
			$actions .= Html::rawElement( 'li', array(), $link );
			$actions = Html::rawElement( 'ul', array(), $actions );
			$volumeOut .= Html::rawElement( 'td', array(), $actions );
			if ( isset( $projectArr["$project"] ) ) {
				$projectArr["$project"] .= Html::rawElement( 'tr', array(), $volumeOut );
			} else {
				$projectArr["$project"] = Html::rawElement( 'tr', array(), $volumeOut );
			}
		}
		foreach ( $userProjects as $project ) {
			$out .= Html::element( 'h2', array(), $project );
			$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-createvolume' ), array(),
							   array( 'action' => 'create', 'project' => $project ) );
			if ( isset( $projectArr["$project"] ) ) {
				$projectOut = $header;
				$projectOut .= $projectArr["$project"];
				$out .= Html::rawElement( 'table',
										  array( 'id' => 'novavolumelist', 'class' => 'wikitable sortable collapsible' ), $projectOut );
			}
		}

		$wgOut->addHTML( $out );
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$volume = $this->userNova->createVolume( $formData['availabilityZone'], $formData['volumeSize'], $formData['volumename'], $formData['volumedescription'] );
		if ( $volume ) {
			$wgOut->addWikiMsg( 'openstackmanager-createdvolume', $volume->getVolumeID() );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-createevolumefailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backvolumelist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$volume = $this->adminNova->getVolume( $formData['volumeid'] );
		if ( ! $volume ) {
			$wgOut->addWikiMsg( 'openstackmanager-nonexistantvolume' );
			return true;
		}
		$volumeid = $volume->getVolumeId();
		$success = $this->userNova->deleteVolume( $volumeid );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-deletedvolume', $volumeid );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-deletevolumefailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backvolumelist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAttachSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = $this->userNova->attachVolume( $formData['volumeid'], $formData['instanceid'], $formData['device'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-attachedvolume' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-attachvolumefailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backvolumelist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDetachSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		if ( isset( $formData['force'] ) && $formData['force'] ) {
			$force = true;
		} else {
			$force = false;
		}
		$success = $this->userNova->detachVolume( $formData['volumeid'], $force );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-detachedvolume' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-detachvolumefailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backvolumelist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * Return an array of drive devices
	 *
	 * @return string
	 */
	function getDrives() {
		$drives = array();
		foreach ( range('a', 'z') as $letter ) {
			$drive = '/dev/vd' . $letter;
			$drives["$drive"] = $drive;
		}
		foreach ( range('a', 'z') as $letter ) {
			$drive = '/dev/vda' . $letter;
			$drives["$drive"] = $drive;
		}

		return $drives;
	}
}

class SpecialNovaVolumeForm extends HTMLForm {
}

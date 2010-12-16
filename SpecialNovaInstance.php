<?php
class SpecialNovaInstance extends SpecialPage {

	var $adminNova, $userNova;
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaInstance' );
	}
 
	function execute( $par ) {
		global $wgRequest, $wgUser;
		global $wgOpenStackManagerNovaAdminKeys;

		wfLoadExtensionMessages('OpenStackManager');
		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return true;
		}
		$user = new OpenStackNovaUser();
		if ( ! $user->exists() ) {
			$this->noCredentials();
			return true;
		}
		$this->userLDAP = new OpenStackNovaUser();
		$project = $wgRequest->getVal('project');
		$userCredentials = $user->getCredentials( $project );
		$this->userNova = new OpenStackNovaController( $userCredentials );
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );

		$action = $wgRequest->getVal('action');

		if ( $action == "create" ) {
			if ( ! $user->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->createInstance();
		} else if ( $action == "delete" ) {
			if ( ! $user->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->deleteInstance();
		} else if ( $action == "rename" ) {
			if ( ! $user->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->renameInstance();
		} else if ( $action == "configure" ) {
			if ( ! $user->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->configureInstance();
		} else {
			$this->listInstances();
		}
	}

	function notLoggedIn() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("Not logged in");
		$wgOut->addHTML('<p>You must be logged in to perform this action</p>');
	}

	function noCredentials() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("No Nova credentials found for your account");
		$wgOut->addHTML('<p>There were no Nova credentials found for your user account. Please ask a Nova administrator to create credentials for you.</p>');
	}

	function notInProject() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("Your account is not in the project requested");
		$wgOut->addHTML('<p>You can not complete the action requested as your user account is not in the project requested.</p>');
	}

	function createInstance() { 
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("Create Instance");
 
		$project = $wgRequest->getVal('project');

		# TODO: Add project name field

		$instanceInfo = Array(); 
		$instanceInfo['instanceName'] = array(
			'type' => 'text',
			'label-message' => 'instancename',
			'default' => '',
			'section' => 'instance/info',
		);

		$instanceTypes = $this->adminNova->getInstanceTypes();
		$instanceType_keys = Array();
		foreach ( $instanceTypes as $instanceType ) {
			$instanceType_keys["$instanceType"] = $instanceType;
		}
		$instanceInfo['instanceType'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $instanceType_keys,
			'label-message' => 'instancetype',
		);

		# Availability zone names can't be translated. Get the keys, and make an array
		# where the name points to itself as a value
		$availabilityZones = $this->adminNova->getAvailabilityZones();
		$availabilityZone_keys = Array();
		foreach ( array_keys( $availabilityZones ) as $availabilityZone_key ) {
			$availabilityZone_keys["$availabilityZone_key"] = $availabilityZone_key;
		}
		$instanceInfo['availabilityZone'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $availabilityZone_keys,
			'label-message' => 'availabilityzone',
		);

		# Image names can't be translated. Get the image, and make an array
		# where the name points to itself as a value
		$images = $this->adminNova->getImages();
		$image_keys = Array();
		foreach ( array_keys($images) as $image_key ) {
			$image_keys["$image_key"] = $image_key;
		}
		$instanceInfo['imageType'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $image_keys,
			'label-message' => 'imagetype',
		);

		# Keypair names can't be translated. Get the keys, and make an array
		# where the name points to itself as a value
		# TODO: get keypairs as the user, not the admin
		$keypairs = $this->userNova->getKeypairs();
		$keypair_keys = Array();
		foreach ( array_keys( $keypairs ) as $keypair_key ) {
			$keypair_keys["$keypair_key"] = $keypair_key;
		}
		$instanceInfo['keypair'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $keypair_keys,
			'label-message' => 'keypair',
		);

		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
		);

		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => htmlentities( $project ),
		);

		#TODO: Add availablity zone field

		$instanceForm = new OpenStackCreateInstanceForm( $instanceInfo, 'novainstance-form' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ));
		$instanceForm->setSubmitID( 'novainstance-form-createinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$instanceForm->show();

	}

	function modifyInstance() {
		return true;
	}

	function listInstances() {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle("Instance list");

		$userProjects = $this->userLDAP->getProjects();
		$sk = $wgUser->getSkin();
		$out = '';
		$instances = $this->adminNova->getInstances();
		$header = Html::element( 'th', array(), 'Instance Name' );
		$header .= Html::element( 'th', array(), 'Instance ID' );
		$header .= Html::element( 'th', array(), 'Instance State' );
		$header .= Html::element( 'th', array(), 'Instance Type' );
		$header .= Html::element( 'th', array(), 'Image ID' );
		$header .= Html::element( 'th', array(), 'Actions' );
		$projectArr = array();
		foreach ( $instances as $instance ) {
			$project = $instance->getOwner();
			if ( ! in_array( $project, $userProjects ) ) {
				continue;
			}
			$instanceName = (string)$instance->getInstanceName();
			$title = Title::newFromText( $instanceName, NS_VM );
			$instanceNameLink = $sk->link( $title, $instanceName, array(), array(), array() );
			$projectArr["$project"] = Html::rawElement( 'td', array(), $instanceNameLink );
			$projectArr["$project"] .= Html::element( 'td', array(), $instance->getInstanceId() );
			$projectArr["$project"] .= Html::element( 'td', array(), $instance->getInstanceState() );
			$projectArr["$project"] .= Html::element( 'td', array(), $instance->getInstanceType() );
			$projectArr["$project"] .= Html::element( 'td', array(), $instance->getImageId() );
			$actions = $sk->link( $this->getTitle(), 'delete', array(), array( 'action' => 'delete', 'project' => $projectname, 'instanceid' => $instance->getInstanceId() ), array() );
			$actions .= ', ';
			$actions .= $sk->link( $this->getTitle(), 'rename', array(), array( 'action' => 'rename', 'project' => $projectname, 'instanceid' => $instance->getInstanceId() ), array() );
			$actions .= ', ';
			$actions .= $sk->link( $this->getTitle(), 'configure', array(), array( 'action' => 'configure', 'project' => $projectname, 'instanceid' => $instance->getInstanceId() ), array() );
			$projectArr["$project"] .= Html::rawElement( 'td', array(), $actions );
		}
		foreach ( $userProjects as $projectname ) {
			$out .= Html::element( 'h2', array(), $projectname );
			$out .= $sk->link( $this->getTitle(), 'Create a new instance', array(), array( 'action' => 'create', 'project' => $projectname ), array() );
			if ( isset( $projectArr["$projectname"] ) ) {
				$projectOut = $header;
				$projectOut .= Html::rawElement( 'tr', array(), $projectArr["$projectname"] );
				$out .= Html::rawElement( 'table', array( 'id' => 'novainstancelist', 'class' => 'wikitable' ), $projectOut );
			}
		}

		$wgOut->addHTML( $out );
	}

	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$instance = $this->userNova->createInstance( $formData['instanceName'], $formData['imageType'], $formData['keypair'], $formData['instanceType'], $formData['availabilityZone'] );

		$out = Html::element( 'p', array(), 'Created instance ' . $instance->getInstanceID() . ' with image ' . $instance->getImageId() );
		$out .= $sk->link( $this->getTitle(), 'Back to instance list', array(), array(), array() );

		$wgOut->addHTML( $out );
		return true;
	}
}

class OpenStackCreateInstanceForm extends HTMLForm {
}

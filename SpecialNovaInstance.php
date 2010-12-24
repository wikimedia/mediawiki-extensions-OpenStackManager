<?php
class SpecialNovaInstance extends SpecialNova {

	var $adminNova, $userNova;
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaInstance' );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;
		global $wgOpenStackManagerNovaAdminKeys;

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

	function createInstance() { 
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("Create Instance");

		$instanceInfo = Array(); 
		$instanceInfo['instancename'] = array(
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
		#$keypairs = $this->userNova->getKeypairs();
		#$keypair_keys = Array();
		#foreach ( array_keys( $keypairs ) as $keypair_key ) {
		#	$keypair_keys["$keypair_key"] = $keypair_key;
		#}
		#$instanceInfo['keypair'] = array(
		#	'type' => 'select',
		#	'section' => 'instance/info',
		#	'options' => $keypair_keys,
		#	'label-message' => 'keypair',
		#);

		$domains = OpenStackNovaDomain::getAllDomains();
		$domain_keys = array();
		foreach ( $domains as $domain ) {
			$domainname = $domain->getDomainName();
			$domain_keys["$domainname"] = $domainname;
		}
		$instanceInfo['domain'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $domain_keys,
			'label-message' => 'domain',
		);

		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $wgRequest->getText( 'project' ),
		);

		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
		);

		$instanceForm = new SpecialNovaInstanceForm( $instanceInfo, 'novainstance-form' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ));
		$instanceForm->setSubmitID( 'novainstance-form-createinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$instanceForm->show();

	}

	function deleteInstance() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle("Delete domain");

		$instanceid = $wgRequest->getText('instanceid');
		$project = $wgRequest->getText('project');
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), 'Are you sure you wish to delete instance "' . $instanceid .  '"?' );
			$wgOut->addHTML( $out );
		}
		$instanceInfo = Array();
		$instanceInfo['instanceid'] = array(
			'type' => 'hidden',
			'default' => $instanceid,
		);
		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
		);
		$instanceForm = new SpecialNovaInstanceForm( $instanceInfo, 'novainstance-form' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ));
		$instanceForm->setSubmitID( 'novainstance-form-deleteinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$instanceForm->setSubmitText( 'confirm' );
		$instanceForm->show();

		return true;
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
			$actions = $sk->link( $this->getTitle(), 'delete', array(),
								  array( 'action' => 'delete',
									   'project' => $project,
									   'instanceid' => $instance->getInstanceId() ),
								  array() );
			$actions .= ', ';
			$actions .= $sk->link( $this->getTitle(), 'rename', array(),
								   array( 'action' => 'rename',
										'project' => $project,
										'instanceid' => $instance->getInstanceId() ),
								   array() );
			$actions .= ', ';
			$actions .= $sk->link( $this->getTitle(), 'configure', array(),
								   array( 'action' => 'configure',
										'project' => $project,
										'instanceid' => $instance->getInstanceId() ),
								   array() );
			$projectArr["$project"] .= Html::rawElement( 'td', array(), $actions );
		}
		foreach ( $userProjects as $project ) {
			$out .= Html::element( 'h2', array(), $project );
			$out .= $sk->link( $this->getTitle(), 'Create a new instance', array(),
							   array( 'action' => 'create', 'project' => $project ), array() );
			if ( isset( $projectArr["$project"] ) ) {
				$projectOut = $header;
				$projectOut .= Html::rawElement( 'tr', array(), $projectArr["$project"] );
				$out .= Html::rawElement( 'table',
										  array( 'id' => 'novainstancelist', 'class' => 'wikitable' ), $projectOut );
			}
		}

		$wgOut->addHTML( $out );
	}

	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		#$instance = $this->userNova->createInstance( $formData['instancename'], $formData['imageType'], $formData['keypair'], $formData['instanceType'], $formData['availabilityZone'] );
		$domain = new OpenStackNovaDomain( $formData['domain'] );
		$domain = OpenStackNovaDomain::getDomainByName( $formData['domain'] );
		if ( ! $domain ) {
			$out = Html::element( 'p', array(), 'Requested domain is invalid' );
		}
		$instance = $this->userNova->createInstance( $formData['instancename'], $formData['imageType'], '', $formData['instanceType'], $formData['availabilityZone'], $domain );
		$sk = $wgUser->getSkin();
		if ( $instance ) {
			$host = OpenStackNovaHost::addHost( $instance->getInstanceName(), $instance->getInstancePrivateIP(), $domain );
			if ( $host ) {
				$out = Html::element( 'p', array(), 'Created instance ' . $instance->getInstanceID() .  ' with image ' . $instance->getImageId() . ' and hostname ' . $host->getFullyQualifiedHostName() . ' and ip ' . $instance->getInstancePrivateIP() );
			} else {
				$this->userNova->terminateInstance( $instance->getInstanceId() );
				$out = Html::element( 'p', array(), 'Failed to create instance as the host could not be added to LDAP' );
			}
			# TODO: also add puppet
		} else {
			$out = Html::element( 'p', array(), 'Failed to create instance' );
		}
		$out .= $sk->link( $this->getTitle(), 'Back to instance list', array(), array(), array() );

		$wgOut->addHTML( $out );
		return true;
	}

	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$instance = $this->adminNova->getInstance( $formData['instanceid'] );
		$domain = $instance->getInstanceDomain();
		$instancename = $instance->getInstanceName();
		$success = $this->userNova->terminateInstance( $formData['instanceid'] );
		$sk = $wgUser->getSkin();
		if ( $success ) {
			$success = OpenStackNovaHost::deleteHost( $instancename, $domain );
			if ( $success ) {
				$out = Html::element( 'p', array(), "Deleted instance $instancename" );
			} else {
				$out = Html::element( 'p', array(), 'Successfully deleted instance, but failed to remove it from LDAP' );
			}
		} else {
			$out = Html::element( 'p', array(), 'Failed to create instance' );
		}
		$out .= $sk->link( $this->getTitle(), 'Back to instance list', array(), array(), array() );

		$wgOut->addHTML( $out );
		return true;
	}

}

class SpecialNovaInstanceForm extends HTMLForm {
}

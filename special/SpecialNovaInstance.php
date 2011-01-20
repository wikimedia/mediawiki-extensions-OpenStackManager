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
		$project = $wgRequest->getVal( 'project' );
		$userCredentials = $user->getCredentials( $project );
		$this->userNova = new OpenStackNovaController( $userCredentials );
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );

		$action = $wgRequest->getVal( 'action' );

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
		global $wgOpenStackManagerPuppetOptions;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-createinstance' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$instanceInfo = Array();
		$instanceInfo['instancename'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-instancename',
			'validation-callback' => array( $this, 'validateInstanceName' ),
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
			'label-message' => 'openstackmanager-instancetype',
			'section' => 'instance/info',
			'options' => $instanceType_keys,
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
			'label-message' => 'openstackmanager-availabilityzone',
		);

		# Image names can't be translated. Get the image, and make an array
		# where the name points to itself as a value
		$images = $this->adminNova->getImages();
		$image_keys = Array();
		foreach ( array_keys( $images ) as $image_key ) {
			$image_keys["$image_key"] = $image_key;
		}
		$instanceInfo['imageType'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $image_keys,
			'label-message' => 'openstackmanager-imagetype',
		);

		# Keypair names can't be translated. Get the keys, and make an array
		# where the name points to itself as a value
		# TODO: get keypairs as the user, not the admin
		# $keypairs = $this->userNova->getKeypairs();
		# $keypair_keys = Array();
		# foreach ( array_keys( $keypairs ) as $keypair_key ) {
		#	$keypair_keys["$keypair_key"] = $keypair_key;
		# }
		# $instanceInfo['keypair'] = array(
		#	'type' => 'select',
		#	'section' => 'instance/info',
		#	'options' => $keypair_keys,
		#	'label-message' => 'keypair',
		# );

		$domains = OpenStackNovaDomain::getAllDomains( 'local' );
		$domain_keys = array();
		foreach ( $domains as $domain ) {
			$domainname = $domain->getDomainName();
			$domain_keys["$domainname"] = $domainname;
		}
		$instanceInfo['domain'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $domain_keys,
			'label-message' => 'openstackmanager-dnsdomain',
		);

		$securityGroups = $this->adminNova->getSecurityGroups();
		$group_keys = array();
		foreach ( $securityGroups as $securityGroup ) {
			if ( $securityGroup->getOwner() == $project ) {
				$securityGroupName = $securityGroup->getGroupName();
				$group_keys["$securityGroupName"] = $securityGroupName;
			}
		}
		$instanceInfo['groups'] = array(
			'type' => 'multiselect',
			'section' => 'instance/info',
			'options' => $group_keys,
			'label-message' => 'openstackmanager-securitygroups',
		);

		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);

		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			if ( $wgOpenStackManagerPuppetOptions['availableclasses'] ) {
				$classes = array();
				foreach ( $wgOpenStackManagerPuppetOptions['availableclasses'] as $class ) {
					$classes["$class"] = $class;
				}
				$instanceInfo['puppetclasses'] = array(
					'type' => 'multiselect',
					'section' => 'instance/puppetinfo',
					'options' => $classes,
					'label-message' => 'openstackmanager-puppetclasses',
				);
			}

			if ( $wgOpenStackManagerPuppetOptions['availablevariables'] ) {
				foreach ( $wgOpenStackManagerPuppetOptions['availablevariables'] as $variable ) {
					$instanceInfo["$variable"] = array(
						'type' => 'text',
						'section' => 'instance/puppetinfo',
						'label' => $variable,
					);
				}
			}
		}

		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
		);

		$instanceForm = new SpecialNovaInstanceForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'openstackmanager-novainstance-createinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$instanceForm->show();

	}

	function configureInstance() {
		global $wgRequest, $wgOut;
		global $wgOpenStackManagerPuppetOptions;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-configureinstance' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$instanceid = $wgRequest->getText( 'instanceid' );
		$instanceInfo = Array();
		$instanceInfo['instanceid'] = array(
			'type' => 'hidden',
			'default' => $instanceid,
		);
		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);

		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			$host = OpenStackNovaHost::getHostByInstanceId( $instanceid );
			if ( ! $host ) {
				$wgOut->addHTML( Html::element( 'p', array(), wfMsg( 'openstackmanager-nonexistanthost' ) ) );
				return false;
			}
			$puppetinfo = $host->getPuppetConfiguration();

			if ( $wgOpenStackManagerPuppetOptions['availableclasses'] ) {
				$classes = array();
				$defaults = array();
				foreach ( $wgOpenStackManagerPuppetOptions['availableclasses'] as $class ) {
					$classes["$class"] = $class;
					if ( in_array( $class, $puppetinfo['puppetclass'] ) ) {
						$defaults["$class"] = $class;
					}
				}
				$instanceInfo['puppetclasses'] = array(
					'type' => 'multiselect',
					'section' => 'instance/puppetinfo',
					'options' => $classes,
					'default' => $defaults,
					'label-message' => 'openstackmanager-puppetclasses',
				);
			}

			if ( $wgOpenStackManagerPuppetOptions['availablevariables'] ) {
				foreach ( $wgOpenStackManagerPuppetOptions['availablevariables'] as $variable ) {
					$default = '';
					if ( array_key_exists( $variable, $puppetinfo['puppetvar'] ) ) {
						$default = $puppetinfo['puppetvar']["$variable"];
					}
					$instanceInfo["$variable"] = array(
						'type' => 'text',
						'section' => 'instance/puppetinfo',
						'label' => $variable,
						'default' => $default,
					);
				}
			}
		}

		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'configure',
		);

		$instanceForm = new SpecialNovaInstanceForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'novainstance-form-configureinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryConfigureSubmit' ) );
		$instanceForm->show();

		return true;
	}

	function deleteInstance() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-deletedomain' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$instanceid = $wgRequest->getText( 'instanceid' );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-deleteinstancequestion', array(), $instanceid ) );
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
		$instanceForm = new SpecialNovaInstanceForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'novainstance-form-deleteinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$instanceForm->setSubmitText( 'confirm' );
		$instanceForm->show();

		return true;
	}

	function listInstances() {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-instancelist' ) );

		$userProjects = $this->userLDAP->getProjects();
		$sk = $wgUser->getSkin();
		$out = '';
		$instances = $this->adminNova->getInstances();
		$header = Html::element( 'th', array(), wfMsg( 'openstackmanager-instancename' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instanceid' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instancestate' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instancetype' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instanceip' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygroups' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-imageid' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$projectArr = array();
		foreach ( $instances as $instance ) {
			$project = $instance->getOwner();
			if ( ! in_array( $project, $userProjects ) ) {
				continue;
			}
			$instanceName = (string)$instance->getInstanceName();
			$title = Title::newFromText( $instanceName, NS_VM );
			$instanceNameLink = $sk->link( $title, $instanceName, array(), array(), array() );
			$instanceOut = Html::rawElement( 'td', array(), $instanceNameLink );
			$instanceOut .= Html::element( 'td', array(), $instance->getInstanceId() );
			$instanceOut .= Html::element( 'td', array(), $instance->getInstanceState() );
			$instanceOut .= Html::element( 'td', array(), $instance->getInstanceType() );
			$instanceOut .= Html::element( 'td', array(), $instance->getInstancePrivateIP() );
			$groupsOut = '';
			foreach ( $instance->getSecurityGroups() as $group ) {
				$groupsOut .= Html::element( 'li', array(), $group );
			}
			$groupsOut = Html::rawElement( 'ul', array(), $groupsOut );
			$instanceOut .= Html::rawElement( 'td', array(), $groupsOut );
			$instanceOut .= Html::element( 'td', array(), $instance->getImageId() );
			$msg = wfMsg( 'openstackmanager-delete' );
			$actions = $sk->link( $this->getTitle(), $msg, array(),
								  array( 'action' => 'delete',
									   'project' => $project,
									   'instanceid' => $instance->getInstanceId() ),
								  array() );
			$actions .= ', ';
			#$msg = wfMsg( 'openstackmanager-rename' );
			#$actions .= $sk->link( $this->getTitle(), $msg, array(),
			#					   array( 'action' => 'rename',
			#							'project' => $project,
			#							'instanceid' => $instance->getInstanceId() ),
			#					   array() );
			#$actions .= ', ';
			$msg = wfMsg( 'openstackmanager-configure' );
			$actions .= $sk->link( $this->getTitle(), $msg, array(),
								   array( 'action' => 'configure',
										'project' => $project,
										'instanceid' => $instance->getInstanceId() ),
								   array() );
			$instanceOut .= Html::rawElement( 'td', array(), $actions );
			$projectArr["$project"] .= Html::rawElement( 'tr', array(), $instanceOut );
		}
		foreach ( $userProjects as $project ) {
			$out .= Html::element( 'h2', array(), $project );
			$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-createinstance' ), array(),
							   array( 'action' => 'create', 'project' => $project ), array() );
			if ( isset( $projectArr["$project"] ) ) {
				$projectOut = $header;
				$projectOut .= $projectArr["$project"];
				$out .= Html::rawElement( 'table',
										  array( 'id' => 'novainstancelist', 'class' => 'wikitable' ), $projectOut );
			}
		}

		$wgOut->addHTML( $out );
	}

	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;
		global $wgOpenStackManagerPuppetOptions;

		$sk = $wgUser->getSkin();
		$domain = OpenStackNovaDomain::getDomainByName( $formData['domain'] );
		if ( ! $domain ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-invaliddomain' ) );
			return true;
		}
		$instance = $this->userNova->createInstance( $formData['instancename'], $formData['imageType'], '', $formData['instanceType'], $formData['availabilityZone'], $formData['groups'] );
		if ( $instance ) {
			$puppetinfo = array();
			if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
				foreach ( $formData['puppetclasses'] as $class ) {
					if ( in_array( $class, $wgOpenStackManagerPuppetOptions['availableclasses'] ) ) {
						$puppetinfo['classes'][] = $class;
					}
				}
				foreach ( $wgOpenStackManagerPuppetOptions['availablevariables'] as $variable ) {
					if ( isset ( $formData["$variable"] ) ) {
						$puppetinfo['variables']["$variable"] = $formData["$variable"];
					}
				}
			}
			$host = OpenStackNovaHost::addHost( $instance, $domain, $puppetinfo );

			if ( $host ) {
				$title = Title::newFromText( $wgOut->getPageTitle() );
				$job = new OpenStackNovaHostJob( $title, array( 'instanceid' => (string)$instance->getInstanceId() ) );
				$job->insert();
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-createdinstance', array(),
				                                              $instance->getInstanceID(), $instance->getImageId(),
				                                              $host->getFullyQualifiedHostName() )  );
			} else {
				$this->userNova->terminateInstance( $instance->getInstanceId() );
				$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-createfailedldap' ) );
			}
			# TODO: also add puppet
		} else {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-createinstancefailed' ) );
		}
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backinstancelist' ), array(), array(), array() );

		$wgOut->addHTML( $out );
		return true;
	}

	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$sk = $wgUser->getSkin();
		$instance = $this->adminNova->getInstance( $formData['instanceid'] );
		if ( ! $instance ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-nonexistanthost' ) );
			return true;
		}
		$instancename = $instance->getInstanceName();
		$instanceid = $instance->getInstanceId();
		$success = $this->userNova->terminateInstance( $instanceid );
		if ( $success ) {
			$success = OpenStackNovaHost::deleteHostByInstanceId( $instanceid );
			if ( $success ) {
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-deletedinstance', array(), $instanceid ) );
			} else {
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-deletedinstance-faileddns', array(), $instancename, $instanceid ) );
			}
		} else {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-deleteinstancefailed' ) );
		}
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backinstancelist' ), array(), array(), array() );

		$wgOut->addHTML( $out );
		return true;
	}

	function tryConfigureSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;
		global $wgOpenStackManagerPuppetOptions;

		$sk = $wgUser->getSkin();
		$host = OpenStackNovaHost::getHostByInstanceId( $formData['instanceid'] );
		if ( $host ) {
			$puppetinfo = array();
			if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
				foreach ( $formData['puppetclasses'] as $class ) {
					if ( in_array( $class, $wgOpenStackManagerPuppetOptions['availableclasses'] ) ) {
						$puppetinfo['classes'][] = $class;
					}
				}
				foreach ( $wgOpenStackManagerPuppetOptions['availablevariables'] as $variable ) {
					if ( isset ( $formData["$variable"] ) ) {
						$puppetinfo['variables']["$variable"] = $formData["$variable"];
					}
				}
			}
			$success = $host->modifyPuppetConfiguration( $puppetinfo );
			if ( $success ) {
				$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-modifiedinstance' ) );
			} else {
				$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-modifyinstancefailed' ) );
			}
		} else {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-nonexistanthost' ) );
		}
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backinstancelist' ), array(), array(), array() );

		$wgOut->addHTML( $out );
		return true;
	}

	function validateInstanceName( $instancename, $alldata ) {
		if ( ! preg_match( "/^[a-z][a-z0-9\-]*$/", $instancename ) ) {
			return Xml::element( 'span', array( 'class' => 'error' ), wfMsg( 'openstackmanager-badinstancename' ) );
		} else {
			return true;
		}
	}

}

class SpecialNovaInstanceForm extends HTMLForm {
}

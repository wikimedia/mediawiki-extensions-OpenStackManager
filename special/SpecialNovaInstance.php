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
			$this->createInstance();
		} else if ( $action == "delete" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->deleteInstance();
		} else if ( $action == "rename" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->renameInstance();
		} else if ( $action == "configure" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->configureInstance();
		} else if ( $action == "consoleoutput" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$this->getConsoleOutput();
		} else {
			$this->listInstances();
		}
	}

	/**
	 * @return bool
	 */
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
		$instanceInfo = array();
		$instanceInfo['instancename'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-instancename',
			'validation-callback' => array( $this, 'validateInstanceName' ),
			'default' => '',
			'section' => 'instance/info',
			'name' => 'instancename',
		);

		$instanceTypes = $this->adminNova->getInstanceTypes();
		$instanceType_keys = array();
		foreach ( $instanceTypes as $instanceType ) {
			$instanceTypeName = $instanceType->getInstanceTypeName();
			$cpus = $instanceType->getNumberOfCPUs();
			$ram = $instanceType->getMemorySize();
			$storage = $instanceType->getStorageSize();
			$instanceLabel = $instanceTypeName . ' (' . wfMsgExt( 'openstackmanager-instancetypelabel', 'parsemag', $cpus, $ram, $storage ) . ')';
			$instanceType_keys["$instanceLabel"] = $instanceTypeName;
		}
		$instanceInfo['instanceType'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-instancetype',
			'section' => 'instance/info',
			'options' => $instanceType_keys,
			'name' => 'instanceType',
		);

		# Availability zone names can't be translated. Get the keys, and make an array
		# where the name points to itself as a value
		$availabilityZones = $this->adminNova->getAvailabilityZones();
		$availabilityZone_keys = array();
		foreach ( array_keys( $availabilityZones ) as $availabilityZone_key ) {
			$availabilityZone_keys["$availabilityZone_key"] = $availabilityZone_key;
		}
		$instanceInfo['availabilityZone'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $availabilityZone_keys,
			'label-message' => 'openstackmanager-availabilityzone',
			'name' => 'availabilityZone',
		);

		# Image names can't be translated. Get the image, and make an array
		# where the name points to itself as a value
		$images = $this->adminNova->getImages();
		$image_keys = array();
		foreach ( $images as $image ) {
			if ( ! $image->imageIsPublic() ) {
				continue;
			}
			if ( $image->getImageState() != "available" ) {
				continue;
			}
			if ( $image->getImageType() != "machine" ) {
				continue;
			}
			$imageName = $image->getImageName();
			if ( $imageName == '' ) {
				continue;
			}
			$imageLabel = $imageName . ' (' . $image->getImageArchitecture() . ')';
			$image_keys["$imageLabel"] = $image->getImageId();
		}
		$instanceInfo['imageType'] = array(
			'type' => 'select',
			'section' => 'instance/info',
			'options' => $image_keys,
			'label-message' => 'openstackmanager-imagetype',
			'name' => 'imageType',
		);

		# Keypair names can't be translated. Get the keys, and make an array
		# where the name points to itself as a value
		# TODO: get keypairs as the user, not the admin
		# $keypairs = $this->userNova->getKeypairs();
		# $keypair_keys = array();
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
			'name' => 'domain',
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
			'name' => 'groups',
		);

		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
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
					'name' => 'puppetclasses',
				);
			}

			if ( $wgOpenStackManagerPuppetOptions['availablevariables'] ) {
				foreach ( $wgOpenStackManagerPuppetOptions['availablevariables'] as $variable ) {
					$instanceInfo["$variable"] = array(
						'type' => 'text',
						'section' => 'instance/puppetinfo',
						'label' => $variable,
						'name' => "$variable",
					);
				}
			}
		}

		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$instanceForm = new SpecialNovaInstanceForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'openstackmanager-novainstance-createinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$instanceForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
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
		$instanceInfo = array();
		$instanceInfo['instanceid'] = array(
			'type' => 'hidden',
			'default' => $instanceid,
			'name' => 'instanceid',
		);
		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);

		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			$host = OpenStackNovaHost::getHostByInstanceId( $instanceid );
			if ( ! $host ) {
				$wgOut->addWikiMsg( 'openstackmanager-nonexistenthost' );
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
					'name' => 'puppetclasses',
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
						'name' => "$variable",
					);
				}
			}
		}

		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'configure',
			'name' => 'action',
		);

		$instanceForm = new SpecialNovaInstanceForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'novainstance-form-configureinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryConfigureSubmit' ) );
		$instanceForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
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
			$wgOut->addWikiMsg( 'openstackmanager-deleteinstancequestion', $instanceid );
		}
		$instanceInfo = array();
		$instanceInfo['instanceid'] = array(
			'type' => 'hidden',
			'default' => $instanceid,
			'name' => 'instanceid',
		);
		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$instanceForm = new SpecialNovaInstanceForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'novainstance-form-deleteinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$instanceForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function getConsoleOutput() {
		global $wgOut, $wgRequest;
		global $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-consoleoutput' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$instanceid = $wgRequest->getText( 'instanceid' );
		$consoleOutput = $this->userNova->getConsoleOutput( $instanceid );
		$sk = $wgUser->getSkin();
		$out = $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backinstancelist' ) );
		$out .= Html::element( 'pre', array(), $consoleOutput );
		$wgOut->addHTML( $out );
	}

	/**
	 * @return void
	 */
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
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instancepublicip' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygroups' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-availabilityzone' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-imageid' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-launchtime' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$projectArr = array();
		foreach ( $instances as $instance ) {
			$project = $instance->getOwner();
			if ( ! in_array( $project, $userProjects ) ) {
				continue;
			}
			$instanceName = $instance->getInstanceName();
			$instanceName = htmlentities( $instanceName );
			$title = Title::newFromText( $instanceName, NS_VM );
			$instanceNameLink = $sk->link( $title, $instanceName );
			$instanceOut = Html::rawElement( 'td', array(), $instanceNameLink );
			$instanceOut .= Html::element( 'td', array(), $instance->getInstanceId() );
			$instanceOut .= Html::element( 'td', array(), $instance->getInstanceState() );
			$instanceOut .= Html::element( 'td', array(), $instance->getInstanceType() );
			$privateip = $instance->getInstancePrivateIP();
			$publicip = $instance->getInstancePublicIP();
			$instanceOut .= Html::element( 'td', array(), $privateip );
			if ( $privateip != $publicip ) {
				$instanceOut .= Html::element( 'td', array(), $publicip );
			} else {
				$instanceOut .= Html::element( 'td', array(), '' );
			}
			$groupsOut = '';
			foreach ( $instance->getSecurityGroups() as $group ) {
				$groupsOut .= Html::element( 'li', array(), $group );
			}
			$groupsOut = Html::rawElement( 'ul', array(), $groupsOut );
			$instanceOut .= Html::rawElement( 'td', array(), $groupsOut );
			$instanceOut .= Html::element( 'td', array(), $instance->getAvailabilityZone() );
			$instanceOut .= Html::element( 'td', array(), $instance->getImageId() );
			$instanceOut .= Html::element( 'td', array(), $instance->getLaunchTime() );
			$msg = wfMsgHtml( 'openstackmanager-delete' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
								  array( 'action' => 'delete',
									   'project' => $project,
									   'instanceid' => $instance->getInstanceId() ) );
			$actions = Html::rawElement( 'li', array(), $link );
			#$msg = wfMsgHtml( 'openstackmanager-rename' );
			#$actions .= $sk->link( $this->getTitle(), $msg, array(),
			#					   array( 'action' => 'rename',
			#							'project' => $project,
			#							'instanceid' => $instance->getInstanceId() ) );
			$msg = wfMsgHtml( 'openstackmanager-configure' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
								   array( 'action' => 'configure',
										'project' => $project,
										'instanceid' => $instance->getInstanceId() ) );
			$actions .= Html::rawElement( 'li', array(), $link );
			$msg = wfMsgHtml( 'openstackmanager-getconsoleoutput' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
								   array( 'action' => 'consoleoutput',
										'project' => $project,
										'instanceid' => $instance->getInstanceId() ) );
			$actions .= Html::rawElement( 'li', array(), $link );
			$actions = Html::rawElement( 'ul', array(), $actions );
			$instanceOut .= Html::rawElement( 'td', array(), $actions );
			if ( isset( $projectArr["$project"] ) ) {
				$projectArr["$project"] .= Html::rawElement( 'tr', array(), $instanceOut );
			} else {
				$projectArr["$project"] = Html::rawElement( 'tr', array(), $instanceOut );
			}
		}
		foreach ( $userProjects as $project ) {
			$out .= Html::element( 'h2', array(), $project );
			$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-createinstance' ), array(),
							   array( 'action' => 'create', 'project' => $project ) );
			$out .= ' ' . $sk->link( Special::getTitleFor( 'NovaAddress' ), wfMsgHtml( 'openstackmanager-allocateips' ) );
			if ( isset( $projectArr["$project"] ) ) {
				$projectOut = $header;
				$projectOut .= $projectArr["$project"];
				$out .= Html::rawElement( 'table',
										  array( 'id' => 'novainstancelist', 'class' => 'wikitable' ), $projectOut );
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
		global $wgOut, $wgUser;
		global $wgOpenStackManagerPuppetOptions;

		$domain = OpenStackNovaDomain::getDomainByName( $formData['domain'] );
		if ( !$domain ) {
			$wgOut->addWikiMsg( 'openstackmanager-invaliddomain' );
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
				$job = new OpenStackNovaHostJob( $title, array( 'instanceid' => $instance->getInstanceId() ) );
				$job->insert();
				$wgOut->addWikiMsg( 'openstackmanager-createdinstance', $instance->getInstanceID(), $instance->getImageId(), $host->getFullyQualifiedHostName() );
			} else {
				$this->userNova->terminateInstance( $instance->getInstanceId() );
				$wgOut->addWikiMsg( 'openstackmanager-createfailedldap' );
			}
			# TODO: also add puppet
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-createeinstancefailed' );
		}
		$sk = $wgUser->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backinstancelist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$instance = $this->adminNova->getInstance( $formData['instanceid'] );
		if ( ! $instance ) {
			$wgOut->addWikiMsg( 'openstackmanager-nonexistanthost' );
			return true;
		}
		$instancename = $instance->getInstanceName();
		$instanceid = $instance->getInstanceId();
		$success = $this->userNova->terminateInstance( $instanceid );
		if ( $success ) {
			$success = OpenStackNovaHost::deleteHostByInstanceId( $instanceid );
			if ( $success ) {
				$wgOut->addWikiMsg( 'openstackmanager-deletedinstance', $instanceid );
			} else {
				$wgOut->addWikiMsg( 'openstackmanager-deletedinstance-faileddns', $instancename, $instanceid );
			}
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-deleteinstancefailed' );
		}
		$sk = $wgUser->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backinstancelist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryConfigureSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;
		global $wgOpenStackManagerPuppetOptions;

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
				$wgOut->addWikiMsg( 'openstackmanager-modifiedinstance' );
			} else {
				$wgOut->addWikiMsg( 'openstackmanager-modifyinstancefailed' );
			}
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-nonexistanthost' );
		}
		$sk = $wgUser->getSkin();
		$out = '<br /';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backinstancelist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $instancename
	 * @param  $alldata
	 * @return bool|string
	 */
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

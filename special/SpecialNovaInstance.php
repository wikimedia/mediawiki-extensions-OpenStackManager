<?php

/**
 * Special page to interact with a Nova instance.
 *
 * @file
 * @ingroup Extensions
 */

/**
 * Class to handle [[Special:NovaInstance]].
 *
 * By default, the special page will list all instances.
 *
 * The page can be passed a (project,instance,action) see execute()
 */
class SpecialNovaInstance extends SpecialNova {
	/**
	 * @var OpenStackNovaController
	 */
	var $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaInstance' );
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

		# ?action=
		$action = $this->getRequest()->getVal( 'action' );

		if ( $action === "create" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->createInstance();
		} elseif ( $action === "delete" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->deleteInstance();
		} elseif ( $action === "configure" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->configureInstance();
		} elseif ( $action === "reboot" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->rebootInstance();
		} elseif ( $action === "consoleoutput" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return;
			}
			$this->getConsoleOutput();
		} else {
			# Fall back to listing all instances
			$this->listInstances();
		}
	}

	/**
	 * Handle ?action=create
	 * @return bool
	 */
	function createInstance() {
		global $wgOpenStackManagerPuppetOptions;
		global $wgOpenStackManagerInstanceBannedInstanceTypes;
		global $wgOpenStackManagerInstanceDefaultImage;
		global $wgOpenStackManagerInstanceBannedImages;

		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-createinstance' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$instanceInfo = array();
		$instanceInfo['instancename'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-instancename',
			'validation-callback' => array( $this, 'validateText' ),
			'default' => '',
			'section' => 'info',
			'name' => 'instancename',
		);

		$instanceTypes = $this->userNova->getInstanceTypes();
		$instanceType_keys = array();
		foreach ( $instanceTypes as $instanceType ) {
			$instanceTypeName = $instanceType->getInstanceTypeName();
			if ( in_array( $instanceTypeName, $wgOpenStackManagerInstanceBannedInstanceTypes ) ) {
				continue;
			}
			$instanceTypeId = $instanceType->getInstanceTypeId();
			$cpus = $instanceType->getNumberOfCPUs();
			$ram = $instanceType->getMemorySize();
			$root_storage = $instanceType->getRootStorageSize();
			$storage = $instanceType->getStorageSize();
			// @todo FIXME: Hard coded parentheses.
			$instanceLabel = $instanceTypeName . ' (' . $this->msg( 'openstackmanager-instancetypelabel', $cpus, $ram, $root_storage, $storage )->text() . ')';
			$instanceType_keys[$instanceLabel] = $instanceTypeId;
		}
		$instanceInfo['instanceType'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-instancetype',
			'section' => 'info',
			'options' => $instanceType_keys,
			'name' => 'instanceType',
		);

		$instanceInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);

		# Image names can't be translated. Get the image, and make an array
		# where the name points to itself as a value
		$images = $this->userNova->getImages();
		$image_keys = array();
		$default = "";
		foreach ( $images as $image ) {
			if ( $image->getImageState() !== "ACTIVE" ) {
				continue;
			}
			$imageName = $image->getImageName();
			if ( $imageName === '' ) {
				continue;
			}
			if ( in_array( $image->getImageId(), $wgOpenStackManagerInstanceBannedImages ) ) {
				continue;
			}
			$imageLabel = $imageName;
			if ( $image->getImageId() === $wgOpenStackManagerInstanceDefaultImage ) {
				$default = $imageLabel;
			}
			$image_keys[$imageLabel] = $image->getImageId();
		}
		if ( isset( $image_keys[$default] ) ) {
			$default = $image_keys[$default];
		}
		$instanceInfo['imageType'] = array(
			'type' => 'select',
			'section' => 'info',
			'options' => $image_keys,
			'default' => $default,
			'label-message' => 'openstackmanager-imagetype',
			'name' => 'imageType',
		);

		# Keypair names can't be translated. Get the keys, and make an array
		# where the name points to itself as a value
		# TODO: get keypairs as the user, not the admin
		# $keypairs = $this->userNova->getKeypairs();
		# $keypair_keys = array();
		# foreach ( array_keys( $keypairs ) as $keypair_key ) {
		#	$keypair_keys[$keypair_key] = $keypair_key;
		# }
		# $instanceInfo['keypair'] = array(
		#	'type' => 'select',
		#	'section' => 'info',
		#	'options' => $keypair_keys,
		#	'label-message' => 'keypair',
		# );

		#$domains = OpenStackNovaDomain::getAllDomains( 'local' );
		#$domain_keys = array();
		#foreach ( $domains as $domain ) {
		#	$domainname = $domain->getDomainName();
		#	$domain_keys[$domainname] = $domainname;
		#}
		#$instanceInfo['domain'] = array(
		#	'type' => 'select',
		#	'section' => 'info',
		#	'options' => $domain_keys,
		#	'label-message' => 'openstackmanager-dnsdomain',
		#	'name' => 'domain',
		#);

		$securityGroups = $this->userNova->getSecurityGroups();
		$group_keys = array();
		$defaults = array();
		foreach ( $securityGroups as $securityGroup ) {
			if ( $securityGroup->getProject() === $project ) {
				$securityGroupName = $securityGroup->getGroupName();
				$group_keys[$securityGroupName] = $securityGroupName;
				if ( $securityGroupName === "default" ) {
					$defaults["default"] = "default";
				}
			}
		}
		$instanceInfo['groups'] = array(
			'type' => 'multiselect',
			'section' => 'info',
			'options' => $group_keys,
			'default' => $defaults,
			'label-message' => 'openstackmanager-securitygroups',
			'name' => 'groups',
		);

		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);

		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			$this->setPuppetInfo( $instanceInfo );
		}

		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$instanceForm = new HTMLForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->addHeaderText( $this->msg( 'openstackmanager-createinstancepuppetwarning' )->text() .
			'<div class="mw-collapsible mw-collapsed">', 'puppetinfo' );
		$instanceForm->addFooterText( '</div>', 'puppetinfo' );
		$instanceForm->setSubmitID( 'openstackmanager-novainstance-createinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$instanceForm->show();

		return true;
	}

	/**
	 * Handle ?action=configure
	 * @return bool
	 */
	function configureInstance() {
		global $wgOpenStackManagerPuppetOptions;

		$this->setHeaders();

		$region = $this->getRequest()->getText( 'region' );
		$instanceosid = $this->getRequest()->getText( 'instanceid' );
		$instance = $this->userNova->getInstance( $instanceosid );
		if ( !$instance ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}

		if ( !$this->userLDAP->inRole( 'projectadmin', $instance->getProject() ) ) {
			$this->notInRole( 'projectadmin', $instance->getProject() );
			return false;
		}

		$instanceid = $instance->getInstanceId();
		$instancename = $instance->getInstanceName();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-configureinstance', $instanceid, $instancename ) );
		$instanceInfo = array();
		$instanceInfo['instanceid'] = array(
			'type' => 'hidden',
			'default' => $instanceosid,
			'name' => 'instanceid',
		);
		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $instance->getProject(),
			'name' => 'project',
		);
		$instanceInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);

		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			$host = OpenStackNovaHost::getHostByInstanceId( $instanceid );
			if ( ! $host ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistenthost' );
				return false;
			}
			$puppetinfo = $host->getPuppetConfiguration();

			$this->setPuppetInfo( $instanceInfo, $puppetinfo );
		}

		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'configure',
			'name' => 'action',
		);

		$instanceForm = new HTMLForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'novainstance-form-configureinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryConfigureSubmit' ) );
		$instanceForm->show();

		return true;
	}

	/**
	 * Handle ?action=delete
	 * @return bool
	 */
	function deleteInstance() {
		$this->setHeaders();

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$instanceosid = $this->getRequest()->getText( 'instanceid' );
		if ( ! $this->getRequest()->wasPosted() ) {
			#TODO: memcache this instanceid lookup
			$instance = $this->userNova->getInstance( $instanceosid );
			if ( !$instance ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistanthost' );
				return false;
			}
			$this->getOutput()->addWikiMsg( 'openstackmanager-deleteinstancequestion', $instance->getInstanceId() );
			$titleid = $instance->getInstanceId();
			$titlename = $instance->getInstanceName();
			$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-deleteinstancewithname',
				                          $titleid, $titlename ) );
		} else {
			$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-deleteinstance' ) );
		}

		$instanceInfo = array();
		$instanceInfo['instanceid'] = array(
			'type' => 'hidden',
			'default' => $instanceosid,
			'name' => 'instanceid',
		);
		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$instanceInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$instanceForm = new HTMLForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'novainstance-form-deleteinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$instanceForm->show();

		return true;
	}

	/**
	 * Handle ?action=reboot
	 * @return bool
	 */
	function rebootInstance() {
		$this->setHeaders();

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$instanceosid = $this->getRequest()->getText( 'instanceid' );
		if ( ! $this->getRequest()->wasPosted() ) {
			# @todo memcache this instanceid lookup
			$instance = $this->userNova->getInstance( $instanceosid );
			if ( !$instance ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistanthost' );
				return false;
			}
			$this->getOutput()->addWikiMsg( 'openstackmanager-rebootinstancequestion', $instance->getInstanceId() );
			$instanceid = $instance->getInstanceId();
			$instancename = $instance->getInstanceName();
			$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-rebootinstancewithname',
			                                              $instanceid, $instancename ) );
		} else {
			$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-rebootinstance' ) );
		}

		$instanceInfo = array();
		$instanceInfo['instanceid'] = array(
			'type' => 'hidden',
			'default' => $instanceosid,
			'name' => 'instanceid',
		);
		$instanceInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$instanceInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$instanceInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'reboot',
			'name' => 'action',
		);
		$instanceForm = new HTMLForm( $instanceInfo, 'openstackmanager-novainstance' );
		$instanceForm->setTitle( SpecialPage::getTitleFor( 'NovaInstance' ) );
		$instanceForm->setSubmitID( 'novainstance-form-deleteinstancesubmit' );
		$instanceForm->setSubmitCallback( array( $this, 'tryRebootSubmit' ) );
		$instanceForm->show();

		return true;
	}

	/**
	 * Handle ?action=console
	 * @return bool
	 */
	function getConsoleOutput() {
		$this->setHeaders();
		$instanceosid = $this->getRequest()->getText( 'instanceid' );
		$instance = $this->userNova->getInstance( $instanceosid );
		$instanceid = $instance->getInstanceId();
		$instancename = $instance->getInstanceName();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-consoleoutput', $instanceid, $instancename ) );

		$project = $this->getRequest()->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return;
		}
		$consoleOutput = $this->userNova->getConsoleOutput( $instanceosid );
		$out = Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backinstancelist' )->escaped()
		);
		$out .= Html::element( 'pre', array(), $consoleOutput );
		$this->getOutput()->addHTML( $out );
	}

	/**
	 * Default action
	 * @return void
	 */
	function listInstances() {
		$this->setHeaders();
		$this->getOutput()->addModules( 'ext.openstack.Instance' );
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-instancelist' ) );

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
			$projectactions = array( 'projectadmin' => array() );
			$regions = '';
			$this->userNova->setProject( $projectName );
			foreach ( $this->userNova->getRegions( 'compute' ) as $region ) {
				$regionactions = array( 'projectadmin' => array( $this->createActionLink( 'openstackmanager-createinstance', array( 'action' => 'create', 'project' => $projectName, 'region' => $region ) ) ) );
				$instances = $this->getInstances( $projectName, $region );
				$regions .= $this->createRegionSection( $region, $projectName, $regionactions, $instances );
			}
			$out .= $this->createProjectSection( $projectName, $projectactions, $regions );
		}

		$this->getOutput()->addHTML( $out );
	}

	function getInstances( $projectName, $region ) {
		global $wgMemc;

		$this->userNova->setRegion( $region );
		$headers = array( 'openstackmanager-instancename', 'openstackmanager-instanceid', 'openstackmanager-instancestate',
			'openstackmanager-instanceip', 'openstackmanager-instancepublicip',
			'openstackmanager-securitygroups', 'openstackmanager-imageid',
			'openstackmanager-launchtime', 'openstackmanager-actions' );
		$instances = $this->userNova->getInstances();
		$instanceRows = array();
		/**
		 * @var $instance OpenStackNovaInstance
		 */
		foreach ( $instances as $instance ) {
			$instanceRow = array();
			$this->pushResourceColumn( $instanceRow, $instance->getInstanceName(), array( 'class' => 'novainstancename' ) );
			$this->pushRawResourceColumn( $instanceRow, $this->createResourceLink( $instance->getInstanceId() ) );
			$this->pushResourceColumn( $instanceRow, $instance->getInstanceState(), array( 'class' => 'novainstancestate' ) );
			$this->pushRawResourceColumn( $instanceRow, $this->createResourceList( $instance->getInstancePrivateIPs() ) );
			$this->pushRawResourceColumn( $instanceRow, $this->createResourceList( $instance->getInstancePublicIPs() ) );
			$this->pushRawResourceColumn( $instanceRow, $this->createResourceList( $instance->getSecurityGroups() ) );
			$imageId = $instance->getImageId();
			$key = wfMemcKey( 'openstackmanager', "imagename", $imageId );
			$imageNameRet = $wgMemc->get( $key );
			if ( is_string( $imageNameRet ) ) {
				$imageName = $imageNameRet;
			} else {
				$image = $this->userNova->getImage( $imageId );
				$imageName = $image->getImageName();
				$wgMemc->set( $key, $imageName, 86400 );
			}
			$this->pushResourceColumn( $instanceRow, $imageName );
			$this->pushResourceColumn( $instanceRow, $instance->getLaunchTime() );
			$actions = array();
			$instanceDataAttributes = array(
				'data-id' => $instance->getInstanceOSId(),
				'data-name' => $instance->getInstanceName(),
				'data-project' => $projectName,
				'data-region' => $region,
				'class' => 'novainstanceaction',
			);
			if ( $this->userLDAP->inRole( 'projectadmin', $projectName ) ) {
				$actions[] = $this->createActionLink(
					'openstackmanager-delete',
					array(
						'action' => 'delete',
						'instanceid' => $instance->getInstanceOSId(),
						'project' => $projectName,
						'region' => $region )
				);
				$actions[] = $this->createActionLink(
					'openstackmanager-reboot',
					array(
						'action' => 'reboot',
						'instanceid' => $instance->getInstanceOSId(),
						'project' => $projectName,
						'region' => $region
					),
					null,
					$instanceDataAttributes
				);
				$actions[] = $this->createActionLink(
					'openstackmanager-configure',
					array(
						'action' => 'configure',
						'instanceid' => $instance->getInstanceOSId(),
						'project' => $projectName,
						'region' => $region
					)
				);
				$actions[] = $this->createActionLink(
					'openstackmanager-getconsoleoutput',
					array(
						'action' => 'consoleoutput',
						'project' => $projectName,
						'instanceid' => $instance->getInstanceOSId(),
						'region' => $region
					)
				);
			}
			$this->pushRawResourceColumn( $instanceRow, $this->createResourceList( $actions ) );
			$instanceRows[] = $instanceRow;
		}
		if ( $instanceRows ) {
			return $this->createResourceTable( $headers, $instanceRows );
		} else {
			return '';
		}
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		$domain = OpenStackNovaDomain::getDomainByName( $formData['region'] );
		$project = $formData['project'];
		$region = $formData['region'];
		if ( !$domain ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-invaliddomain' );
			return true;
		}
		$instance = $this->userNova->createInstance( $formData['instancename'], $formData['imageType'], '', $formData['instanceType'], $formData['groups'] );
		if ( $instance ) {
			// In essex it seems attributes from extensions aren't returned. So,
			// for now we need to work around this by fetching the instance again.
			$instanceId = $instance->getInstanceOSId();
			$instance = $this->userNova->getInstance( $instanceId );
		}
		if ( $instance ) {
			$host = OpenStackNovaHost::addHost( $instance, $domain, $this->getPuppetInfo( $formData ) );

			if ( $host ) {
				$instance->setHost( $host );
				OpenStackManagerEvent::storeEventInfo( 'build', $this->getUser(), $instance, $project );
				$title = Title::newFromText( $this->getOutput()->getPageTitle() );
				$job = new OpenStackNovaHostJob( $title, array( 'instanceid' => $instance->getInstanceId(), 'instanceosid' => $instance->getInstanceOSId(), 'project' => $project, 'region' => $region ) );
				$job->insert();
				$image = $this->userNova->getImage( $instance->getImageId() );
				$imageName = $image->getImageName();
				$this->getOutput()->addWikiMsg( 'openstackmanager-createdinstance', $instance->getInstanceID(),
					$imageName, $host->getFullyQualifiedHostName() );
			} else {
				$this->userNova->terminateInstance( $instance->getInstanceId() );
				$this->getOutput()->addWikiMsg( 'openstackmanager-createfailedldap' );
			}
			# TODO: also add puppet
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createinstancefailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backinstancelist' )->escaped()
		);

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		$instance = $this->userNova->getInstance( $formData['instanceid'] );
		if ( ! $instance ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistanthost' );
			return true;
		}
		$instancename = $instance->getInstanceName();
		$instanceosid = $instance->getInstanceOSId();
		$instanceproject = $instance->getProject();
		$instanceid = $instance->getInstanceId();
		$success = $this->userNova->terminateInstance( $instanceosid );
		if ( $success ) {
			OpenStackManagerEvent::createDeletionEvent( $instancename, $instanceproject, $this->getUser() );
			$success = OpenStackNovaHost::deleteHostByInstanceId( $instanceid );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-deletedinstance', $instanceid );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-deletedinstance-faileddns', $instancename, $instanceid );
			}
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deleteinstancefailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backinstancelist' )->escaped()
		);

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryRebootSubmit( $formData, $entryPoint = 'internal' ) {
		$instanceid = $formData['instanceid'];
		$success = $this->userNova->rebootInstance( $instanceid );
		if ( $success ) {
			OpenStackManagerEvent::storeEventInfo( 'reboot', $this->getUser(), $this->userNova->getInstance( $instanceid ), $formData['project'] );
			$this->getOutput()->addWikiMsg( 'openstackmanager-rebootedinstance', $instanceid );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-rebootinstancefailed', $instanceid );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backinstancelist' )->escaped()
		);

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryConfigureSubmit( $formData, $entryPoint = 'internal' ) {
		$instance = $this->userNova->getInstance( $formData['instanceid'] );
		$host = $instance->getHost();
		if ( $host ) {
			$success = $host->modifyPuppetConfiguration( $this->getPuppetInfo( $formData ) );
			if ( $success ) {
				$instance->editArticle( $this->userNova );
				$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedinstance', $instance->getInstanceId(), $instance->getInstanceName() );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-modifyinstancefailed' );
			}
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistanthost' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backinstancelist' )->escaped()
		);

		$this->getOutput()->addHTML( $out );
		return true;
	}

	#### Puppet related methods #######################################

	function getPuppetInfo( $formData ) {
		global $wgOpenStackManagerPuppetOptions;

		$puppetinfo = array();
		if ( $wgOpenStackManagerPuppetOptions['enabled'] ) {
			$puppetGroups = OpenStackNovaPuppetGroup::getGroupList( $formData['project'] );
			$this->getPuppetInfoByGroup( $puppetinfo, $puppetGroups, $formData );
			$puppetGroups = OpenStackNovaPuppetGroup::getGroupList();
			$this->getPuppetInfoByGroup( $puppetinfo, $puppetGroups, $formData );
		}
		return $puppetinfo;
	}

	function setPuppetInfo( &$instanceInfo, $puppetinfo=array() ) {
		$project = $instanceInfo['project']['default'];
		$projectGroups = OpenStackNovaPuppetGroup::getGroupList( $project );
		$this->setPuppetInfoByGroups( $instanceInfo, $puppetinfo, $projectGroups );
		$globalGroups = OpenStackNovaPuppetGroup::getGroupList();
		$this->setPuppetInfoByGroups( $instanceInfo, $puppetinfo, $globalGroups );
	}

	function getPuppetInfoByGroup( &$puppetinfo, $puppetGroups, $formData ) {
		foreach ( $puppetGroups as $puppetGroup ) {
			$puppetgroupname = $puppetGroup->getName();
			foreach ( $puppetGroup->getClasses() as $class ) {
				if ( in_array( $class["name"], $formData["$puppetgroupname-puppetclasses"] ) ) {
					$classname = $class["name"];
					if ( !in_array( $classname, $puppetinfo['classes'] ) ) {
						$puppetinfo['classes'][] = $classname;
					}
				}
			}
			foreach ( $puppetGroup->getVars() as $variable ) {
				$variablename = $variable["name"];
				if ( isset ( $formData["$puppetgroupname-$variablename"] ) && trim( $formData["$puppetgroupname-$variablename"] ) ) {
					$puppetinfo['variables'][$variablename] = $formData["$puppetgroupname-$variablename"];
				}
			}
		}
	}

	function setPuppetInfoByGroups( &$instanceInfo, $puppetinfo, $puppetGroups ) {
		global $wgOpenStackManagerPuppetDocBase;

		foreach ( $puppetGroups as $puppetGroup ) {
			$classes = array();
			$defaults = array();
			$puppetgroupname = $puppetGroup->getName();
			$puppetgroupproject = $puppetGroup->getProject();
			if ( $puppetgroupproject ) {
				$section = 'puppetinfo/project';
			} else {
				$section = 'puppetinfo/global';
			}
			foreach ( $puppetGroup->getClasses() as $class ) {
				$classname = $class["name"];
				$classlabel = $classname;
				if ( $wgOpenStackManagerPuppetDocBase ) {
					$docentry = str_replace( '::', '/', $classname );
					$docurl = $wgOpenStackManagerPuppetDocBase . $docentry . '.html';
					#  FIXME:  This probably doesn't handle modules properly.
					$doclink = Html::element( 'a', array('href' => $docurl ),
						$this->msg( 'openstackmanager-puppetdoclink' ) );
					$classlabel = "$classname $doclink";
				}
				$classes[$classlabel] = $classname;
				if ( $puppetinfo && in_array( $classname, $puppetinfo['puppetclass'] ) ) {
					$defaults[$classname] = $classname;
				}
			}
			$instanceInfo[$puppetgroupname] = array(
				'type' => 'info',
				'section' => $section,
				'label-raw' => Html::element( 'h3', array(), "$puppetgroupname:" ),
			);
			$instanceInfo["${puppetgroupname}-puppetclasses"] = array(
				'type' => 'multiselect',
				'section' => $section,
				'options' => $classes,
				'default' => $defaults,
				'name' => "${puppetgroupname}-puppetclasses",
			);
			foreach ( $puppetGroup->getVars() as $variable ) {
				$variablename = $variable["name"];
				$default = '';
				if ( $puppetinfo && array_key_exists( $variablename, $puppetinfo['puppetvar'] ) ) {
					$default = $puppetinfo['puppetvar'][$variablename];
				}
				$instanceInfo["${puppetgroupname}-${variablename}"] = array(
					'type' => 'text',
					'section' => $section,
					'label' => $variablename,
					'default' => $default,
					'name' => "${puppetgroupname}-${variablename}",
				);
			}
		}
	}

	#### End of Puppet related methods ################################
}

<?php

/**
 * Special page from nova address
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaAddress extends SpecialNova {

	var $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaAddress' );
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();
		if ( !$this->userLDAP->exists() ) {
			$this->noCredentials();
			return;
		}
		$this->checkTwoFactor();
		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $project );
		$this->userNova->setRegion( $region );

		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "allocate" ) {
			$this->allocateAddress();
		} elseif ( $action === "release" ) {
			$this->releaseAddress();
		} elseif ( $action === "associate" ) {
			$this->associateAddress();
		} elseif ( $action === "disassociate" ) {
			$this->disassociateAddress();
		} elseif ( $action === "addhost" ) {
			$this->addHost();
		} elseif ( $action === "removehost" ) {
			$this->removehost();
		} else {
			$this->listAddresses();
		}
	}

	/**
	 * @return bool
	 */
	function allocateAddress() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-allocateaddress' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		if ( !$this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-allocateaddress-confirm', $project );
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$addressInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'allocate',
			'name' => 'action',
		);

		$addressForm = new HTMLForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-allocateaddresssubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryAllocateSubmit' ) );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function releaseAddress() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-releaseaddress' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$id = $this->getRequest()->getText( 'id' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$address = $this->userNova->getAddress( $id );
			$ip = $address->getPublicIP();
			$this->getOutput()->addWikiMsg( 'openstackmanager-releaseaddress-confirm', $ip );
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$addressInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$addressInfo['id'] = array(
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'release',
			'name' => 'action',
		);
		$addressForm = new HTMLForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-releaseaddresssubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryReleaseSubmit' ) );
		$addressForm->setSubmitText( 'confirm' );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function associateAddress() {

		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-associateaddress' ) );

		$id = $this->getRequest()->getText( 'id' );
		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$instances = $this->userNova->getInstances();
		$instance_keys = array();
		foreach ( $instances as $instance ) {
			if ( $instance->getProject() === $project ) {
				$instancename = $instance->getInstanceName();
				$instanceid = $instance->getInstanceOSId();
				$instance_keys[$instancename] = $instanceid;
			}
		}
		# Have it nicely sorted:
		ksort( $instance_keys );

		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$addressInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$addressInfo['id'] = array(
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		);
		$addressInfo['instanceid'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-instancename',
			'options' => $instance_keys,
			'name' => 'instanceid',
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'associate',
			'name' => 'action',
		);
		$addressForm = new HTMLForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-releaseaddresssubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryAssociateSubmit' ) );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function disassociateAddress() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-disassociateaddress' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$id = $this->getRequest()->getText( 'id' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$address = $this->userNova->getAddress( $id );
			$ip = $address->getPublicIP();
			$this->getOutput()->addWikiMsg( 'openstackmanager-disassociateaddress-confirm', $ip );
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$addressInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$addressInfo['id'] = array(
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'disassociate',
			'name' => 'action',
		);
		$addressForm = new HTMLForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-disassociateaddresssubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryDisassociateSubmit' ) );
		$addressForm->setSubmitText( 'confirm' );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function addHost() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-addhost' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$id = $this->getRequest()->getText( 'id' );
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$addressInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$addressInfo['id'] = array(
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		);
		$addressInfo['hostname'] = array(
			'type' => 'text',
			'default' => '',
			'validation-callback' => array( $this, 'validateDomain' ),
			'label-message' => 'openstackmanager-hostname',
			'name' => 'hostname',
		);
		$domains = OpenStackNovaDomain::getAllDomains( 'public' );
		$domain_keys = array();
		foreach ( $domains as $domain ) {
			$domainname = $domain->getDomainName();
			$domain_keys[$domainname] = $domainname;
		}
		$addressInfo['domain'] = array(
			'type' => 'select',
			'options' => $domain_keys,
			'label-message' => 'openstackmanager-dnsdomain',
			'name' => 'domain',
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addhost',
			'name' => 'action',
		);
		$addressForm = new HTMLForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-addhostsubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryAddHostSubmit' ) );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function removeHost() {

		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-removehost' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$id = $this->getRequest()->getText( 'id' );
		$domain = $this->getRequest()->getText( 'domain' );
		$hostname = $this->getRequest()->getText( 'hostname' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$address = $this->userNova->getAddress( $id );
			$ip = $address->getPublicIP();
			$this->getOutput()->addWikiMsg( 'openstackmanager-removehost-confirm', $hostname, $ip );
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$addressInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$addressInfo['id'] = array(
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		);
		$addressInfo['domain'] = array(
			'type' => 'hidden',
			'default' => $domain,
			'name' => 'domain',
		);
		$addressInfo['hostname'] = array(
			'type' => 'hidden',
			'default' => $hostname,
			'name' => 'hostname',
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'removehost',
			'name' => 'action',
		);
		$addressForm = new HTMLForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-removehostsubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryRemoveHostSubmit' ) );
		$addressForm->setSubmitText( 'confirm' );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function listAddresses() {
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.openstack' );
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-addresslist' ) );

		if ( $this->getUser()->isAllowed( 'listall' ) ) {
			$projects = OpenStackNovaProject::getAllProjects();
		} else {
			$projects = OpenStackNovaProject::getProjectsByName( $this->userLDAP->getProjects() );
		}
		$projectfilter = $this->getProjectFilter();
		if ( !$projectfilter ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-setprojectfilter' );
			$this->showProjectFilter( $projects, true );
			return null;
		}
		$this->showProjectFilter( $projects );

		$out = '';

		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			if ( !in_array( $projectName, $projectfilter ) ) {
				continue;
			}
			$projectactions = Array( 'netadmin' => Array() );
			$regions = '';
			$this->userNova->setProject( $projectName );
			foreach ( $this->userNova->getRegions( 'compute' ) as $region ) {
				$regionactions = Array( 'netadmin' => Array( $this->createActionLink( 'openstackmanager-allocateaddress', array( 'action' => 'allocate', 'project' => $projectName, 'region' => $region ) ) ) );
				$addresses = $this->getAddresses( $projectName, $region );
				$regions .= $this->createRegionSection( $region, $projectName, $regionactions, $addresses );
			}
			$out .= $this->createProjectSection( $projectName, $projectactions, $regions );
		}
		$this->getOutput()->addHTML( $out );

		return true;
	}

	function getAddresses( $projectName, $region ) {
		$this->userNova->setRegion( $region );
		$headers = Array( 'openstackmanager-address', 'openstackmanager-instanceid', 'openstackmanager-instancename',
			'openstackmanager-hostnames', 'openstackmanager-actions' );
		$addresses = $this->userNova->getAddresses();
		$instances = $this->userNova->getInstances();
		$addressRows = Array();
		/**
		 * @var $address OpenStackNovaAddress
		 */
		foreach ( $addresses as $address ) {
			$addressRow = array();
			$ip = $address->getPublicIP();
			$id = $address->getAddressId();
			$instanceosid = $address->getInstanceId();
			$this->pushResourceColumn( $addressRow, $ip );
			if ( $instanceosid ) {
				$instancename = $instances[$instanceosid]->getInstanceName();
				$instanceid = $instances[$instanceosid]->getInstanceId();
				$this->pushRawResourceColumn( $addressRow, $this->createResourceLink( $instanceid ) );
				$this->pushResourceColumn( $addressRow, $instancename );
			} else {
				$this->pushResourceColumn( $addressRow, '' );
				$this->pushResourceColumn( $addressRow, '' );
			}
			$hosts = OpenStackNovaHost::getHostsByIP( $ip );
			if ( $hosts ) {
				$hostArr = Array();
				foreach ( $hosts as $host ) {
					$domain = $host->getDomain();
					$fqdns = $host->getAssociatedDomains();
					foreach ( $fqdns as $fqdn ) {
						$hostname = explode( '.', $fqdn );
						$hostname = $hostname[0];
						$link = $this->createActionLink( 'openstackmanager-removehost-action', array( 'action' => 'removehost', 'id' => $id, 'project' => $projectName, 'region' => $region, 'domain' => $domain->getDomainName(), 'hostname' => $hostname ) );
						array_push( $hostArr, htmlentities( $fqdn ) . ' ' . $link );
					}
				}
				$this->pushRawResourceColumn( $addressRow, $this->createResourceList( $hostArr ) );
			} else {
				$this->pushResourceColumn( $addressRow, '' );
			}
			$actions = Array();
			if ( $instanceosid ) {
				array_push( $actions, $this->createActionLink( 'openstackmanager-reassociateaddress', array( 'action' => 'associate', 'id' => $id, 'project' => $projectName, 'region' => $region ) ) );
				array_push( $actions, $this->createActionLink( 'openstackmanager-disassociateaddress', array( 'action' => 'disassociate', 'id' => $id, 'project' => $projectName, 'region' => $region ) ) );
			} else {
				array_push( $actions, $this->createActionLink( 'openstackmanager-releaseaddress', array( 'action' => 'release', 'id' => $id, 'project' => $projectName, 'region' => $region ) ) );
				array_push( $actions, $this->createActionLink( 'openstackmanager-associateaddress', array( 'action' => 'associate', 'id' => $id, 'project' => $projectName, 'region' => $region ) ) );
			}
			array_push( $actions, $this->createActionLink( 'openstackmanager-addhost', array( 'action' => 'addhost', 'id' => $id, 'project' => $projectName, 'region' => $region ) ) );
			$this->pushRawResourceColumn( $addressRow, $this->createResourceList( $actions ) );
			array_push( $addressRows, $addressRow );
		}
		if ( $addressRows ) {
			return $this->createResourceTable( $headers, $addressRows );
		} else {
			return '';
		}
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAllocateSubmit( $formData, $entryPoint = 'internal' ) {
		$address = $this->userNova->allocateAddress();
		if ( ! $address ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-allocateaddressfailed' );
			return true;
		}
		$ip = $address->getPublicIP();
		$this->getOutput()->addWikiMsg( 'openstackmanager-allocatedaddress', $ip );
		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backaddresslist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryReleaseSubmit( $formData, $entryPoint = 'internal' ) {
		$id = $formData['id'];
		#TODO: Instead of throwing an error when host exist or the IP
		# is associated, remove all host entries and disassociate the IP
		# then release the address
		$outputPage = $this->getOutput();
		$address = $this->userNova->getAddress( $id );
		$ip = $address->getPublicIp();
		if ( $address->getInstanceId() ) {
			$outputPage->addWikiMsg( 'openstackmanager-cannotreleaseaddress', $ip );
			return true;
		}
		$hosts = OpenStackNovaHost::getHostsByIP( $ip );
		if ( $hosts ) {
			$outputPage->addWikiMsg( 'openstackmanager-cannotreleaseaddress', $ip );
			return true;
		}
		$success = $this->userNova->releaseAddress( $id );
		if ( $success ) {
			$outputPage->addWikiMsg( 'openstackmanager-releasedaddress', $ip );
		} else {
			$outputPage->addWikiMsg( 'openstackmanager-cannotreleaseaddress', $ip );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backaddresslist' ) );
		$outputPage->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAssociateSubmit( $formData, $entryPoint = 'internal' ) {
		$instanceid = $formData['instanceid'];
		$id = $formData['id'];
		$address = $this->userNova->getAddress( $id );
		$ip = $address->getPublicIp();
		if ( $address ) {
			if ( $address->getInstanceId() ) {
				$address = $this->userNova->disassociateAddress( $address->getInstanceId(), $ip );
				if ( $address ) {
					$address = $this->userNova->associateAddress( $instanceid, $ip );
				}
			} else {
				$address = $this->userNova->associateAddress( $instanceid, $ip );
			}
		}
		$outputPage = $this->getOutput();
		if ( $address ) {
			$outputPage->addWikiMsg( 'openstackmanager-associatedaddress', $ip, $instanceid );
		} else {
			$outputPage->addWikiMsg( 'openstackmanager-associateaddressfailed', $ip, $instanceid );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backaddresslist' ) );
		$outputPage->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDisassociateSubmit( $formData, $entryPoint = 'internal' ) {
		$id = $formData['id'];
		$address = $this->userNova->getAddress( $id );
		$ip = $address->getPublicIp();
		$instanceid = $address->getInstanceId();
		$address = $this->userNova->disassociateAddress( $instanceid, $ip );
		$outputPage = $this->getOutput();
		if ( $address ) {
			$outputPage->addWikiMsg( 'openstackmanager-disassociatedaddress', $ip );
		} else {
			$outputPage->addWikiMsg( 'openstackmanager-disassociateaddressfailed', $ip );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backaddresslist' ) );
		$outputPage->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddHostSubmit( $formData, $entryPoint = 'internal' ) {
		$id = $formData['id'];
		$address = $this->userNova->getAddress( $id );
		$ip = $address->getPublicIp();
		$outputPage = $this->getOutput();
		if ( ! $address ) {
			$outputPage->addWikiMsg( 'openstackmanager-invalidaddress', $ip );
			return true;
		}
		$hostname = $formData['hostname'];
		$domain = $formData['domain'];
		$domain = OpenStackNovaDomain::getDomainByName( $domain );
		$hostbyname = OpenStackNovaHost::getHostByName( $hostname, $domain );
		$hostbyip = OpenStackNovaHost::getHostByIP( $ip );

		if ( $hostbyname ) {
			# We need to add an arecord, if the arecord doesn't already exist
			$success = $hostbyname->addARecord( $ip );
			if ( $success ) {
				$outputPage->addWikiMsg( 'openstackmanager-addedhost', $hostname, $ip );
			} else {
				$outputPage->addWikiMsg( 'openstackmanager-addhostfailed', $hostname, $ip );
			}
		} elseif ( $hostbyip ) {
			# We need to add an associateddomain, if the associateddomain doesn't already exist
			$success = $hostbyip->addAssociatedDomain( $hostname . '.' . $domain->getFullyQualifiedDomainName() );
			if ( $success ) {
				$outputPage->addWikiMsg( 'openstackmanager-addedhost', $hostname, $ip );
			} else {
				$outputPage->addWikiMsg( 'openstackmanager-addhostfailed', $hostname, $ip );
			}
		} else {
			# This is a new host entry
			$host = OpenStackNovaHost::addPublicHost( $hostname, $ip, $domain );
			if ( $host ) {
				$outputPage->addWikiMsg( 'openstackmanager-addedhost', $hostname, $ip );
			} else {
				$outputPage->addWikiMsg( 'openstackmanager-addhostfailed', $hostname, $ip );
			}
		}
$this->getOutput();
		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backaddresslist' ) );
		$outputPage->addHTML( $out );
		return true;
	}

	/**
	 * @param $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryRemoveHostSubmit( $formData, $entryPoint = 'internal' ) {
		$id = $formData['id'];
		$address = $this->userNova->getAddress( $id );
		$outputPage = $this->getOutput();
		if ( ! $address ) {
			$outputPage->addWikiMsg( 'openstackmanager-invalidaddress', $id );
			return true;
		}
		$ip = $address->getPublicIp();
		$hostname = $formData['hostname'];
		$domain = $formData['domain'];
		$domain = OpenStackNovaDomain::getDomainByName( $domain );
		$host = OpenStackNovaHost::getHostByName( $hostname, $domain );
		if ( $host ) {
			$fqdn = $hostname . '.' . $domain->getFullyQualifiedDomainName();
			$records = $host->getAssociatedDomains();
			if ( count( $records ) > 1 ) {
				# We need to keep the host, but remove the fqdn
				$success = $host->deleteAssociatedDomain( $fqdn );
				if ( $success ) {
					$outputPage->addWikiMsg( 'openstackmanager-removedhost', $hostname, $ip );
				} else {
					$outputPage->addWikiMsg( 'openstackmanager-removehostfailed', $ip, $hostname );
				}
			} else {
				# We need to remove the host entry
				$success = OpenStackNovaHost::deleteHost( $hostname, $domain );
				if ( $success ) {
					$outputPage->addWikiMsg( 'openstackmanager-removedhost', $hostname, $ip );
				} else {
					$outputPage->addWikiMsg( 'openstackmanager-removehostfailed', $ip, $hostname );
				}
			}
		} else {
			$outputPage->addWikiMsg( 'openstackmanager-nonexistenthost' );
		}
		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backaddresslist' ) );
		$outputPage->addHTML( $out );
		return true;
	}
}

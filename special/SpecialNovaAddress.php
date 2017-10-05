<?php

/**
 * Special page from nova address
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaAddress extends SpecialNova {
	public $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	public $userLDAP;

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
			$this->removeHost();
		} else {
			$this->listAddresses();
		}
	}

	/**
	 * @return bool
	 */
	function allocateAddress() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-allocateaddress' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		if ( !$this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-allocateaddress-confirm', $project );
		}
		$addressInfo = [];
		$addressInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$addressInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$addressInfo['action'] = [
			'type' => 'hidden',
			'default' => 'allocate',
			'name' => 'action',
		];

		$addressForm = new HTMLForm(
			$addressInfo,
			$this->getContext(),
			'openstackmanager-novaaddress'
		);
		$addressForm->setSubmitID( 'novaaddress-form-allocateaddresssubmit' );
		$addressForm->setSubmitCallback( [ $this, 'tryAllocateSubmit' ] );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function releaseAddress() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-releaseaddress' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$id = $this->getRequest()->getText( 'id' );
		if ( !$this->getRequest()->wasPosted() ) {
			$address = $this->userNova->getAddress( $id );
			$ip = $address->getPublicIP();
			$this->getOutput()->addWikiMsg( 'openstackmanager-releaseaddress-confirm', $ip );
		}
		$addressInfo = [];
		$addressInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$addressInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$addressInfo['id'] = [
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		];
		$addressInfo['action'] = [
			'type' => 'hidden',
			'default' => 'release',
			'name' => 'action',
		];
		$addressForm = new HTMLForm(
			$addressInfo,
			$this->getContext(),
			'openstackmanager-novaaddress'
		);
		$addressForm->setSubmitID( 'novaaddress-form-releaseaddresssubmit' );
		$addressForm->setSubmitCallback( [ $this, 'tryReleaseSubmit' ] );
		$addressForm->setSubmitText( 'confirm' );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function associateAddress() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-associateaddress' ) );

		$id = $this->getRequest()->getText( 'id' );
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
				$instanceid = $instance->getInstanceOSId();
				$instance_keys[$instancename] = $instanceid;
			}
		}
		# Have it nicely sorted:
		ksort( $instance_keys );

		$addressInfo = [];
		$addressInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$addressInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$addressInfo['id'] = [
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		];
		$addressInfo['instanceid'] = [
			'type' => 'select',
			'label-message' => 'openstackmanager-instancename',
			'options' => $instance_keys,
			'name' => 'instanceid',
		];
		$addressInfo['action'] = [
			'type' => 'hidden',
			'default' => 'associate',
			'name' => 'action',
		];
		$addressForm = new HTMLForm(
			$addressInfo,
			$this->getContext(),
			'openstackmanager-novaaddress'
		);
		$addressForm->setSubmitID( 'novaaddress-form-releaseaddresssubmit' );
		$addressForm->setSubmitCallback( [ $this, 'tryAssociateSubmit' ] );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function disassociateAddress() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-disassociateaddress' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$id = $this->getRequest()->getText( 'id' );
		if ( !$this->getRequest()->wasPosted() ) {
			$address = $this->userNova->getAddress( $id );
			$ip = $address->getPublicIP();
			$this->getOutput()->addWikiMsg( 'openstackmanager-disassociateaddress-confirm', $ip );
		}
		$addressInfo = [];
		$addressInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$addressInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$addressInfo['id'] = [
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		];
		$addressInfo['action'] = [
			'type' => 'hidden',
			'default' => 'disassociate',
			'name' => 'action',
		];
		$addressForm = new HTMLForm(
			$addressInfo,
			$this->getContext(),
			'openstackmanager-novaaddress'
		);
		$addressForm->setSubmitID( 'novaaddress-form-disassociateaddresssubmit' );
		$addressForm->setSubmitCallback( [ $this, 'tryDisassociateSubmit' ] );
		$addressForm->setSubmitText( 'confirm' );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function addHost() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-addhost' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$id = $this->getRequest()->getText( 'id' );
		$addressInfo = [];
		$addressInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$addressInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$addressInfo['id'] = [
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		];
		$addressInfo['hostname'] = [
			'type' => 'text',
			'default' => '',
			'validation-callback' => [ $this, 'validateDomain' ],
			'label-message' => 'openstackmanager-hostname',
			'name' => 'hostname',
		];
		$domains = OpenStackNovaDomain::getAllDomains( 'public' );
		$domain_keys = [];
		foreach ( $domains as $domain ) {
			$domain_keys[$domain->getFullyQualifiedDomainName()] = $domain->getDomainName();
		}
		$addressInfo['domain'] = [
			'type' => 'select',
			'options' => $domain_keys,
			'label-message' => 'openstackmanager-dnsdomain',
			'name' => 'domain',
		];
		$addressInfo['action'] = [
			'type' => 'hidden',
			'default' => 'addhost',
			'name' => 'action',
		];
		$addressForm = new HTMLForm(
			$addressInfo,
			$this->getContext(),
			'openstackmanager-novaaddress'
		);
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-addhostsubmit' );
		$addressForm->setSubmitCallback( [ $this, 'tryAddHostSubmit' ] );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function removeHost() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-removehost' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin', $project );
			return false;
		}
		$id = $this->getRequest()->getText( 'id' );
		$fqdn = $this->getRequest()->getText( 'fqdn' );
		$hostname = $this->getRequest()->getText( 'hostname' );
		if ( !$this->getRequest()->wasPosted() ) {
			$address = $this->userNova->getAddress( $id );
			$ip = $address->getPublicIP();
			$this->getOutput()->addWikiMsg( 'openstackmanager-removehost-confirm', $fqdn, $ip );
		}
		$addressInfo = [];
		$addressInfo['project'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		];
		$addressInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$addressInfo['id'] = [
			'type' => 'hidden',
			'default' => $id,
			'name' => 'id',
		];
		$addressInfo['fqdn'] = [
			'type' => 'hidden',
			'default' => $fqdn,
			'name' => 'fqdn',
		];
		$addressInfo['hostname'] = [
			'type' => 'hidden',
			'default' => $hostname,
			'name' => 'hostname',
		];
		$addressInfo['action'] = [
			'type' => 'hidden',
			'default' => 'removehost',
			'name' => 'action',
		];
		$addressForm = new HTMLForm(
			$addressInfo,
			$this->getContext(),
			'openstackmanager-novaaddress'
		);
		$addressForm->setSubmitID( 'novaaddress-form-removehostsubmit' );
		$addressForm->setSubmitCallback( [ $this, 'tryRemoveHostSubmit' ] );
		$addressForm->setSubmitText( 'confirm' );
		$addressForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function listAddresses() {
		global $wgOpenStackManagerReadOnlyRegions;

		$this->setHeaders();
		$this->getOutput()->addModules( 'ext.openstack.Address' );
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-addresslist' ) );

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
				if ( in_array( $region, $wgOpenStackManagerReadOnlyRegions ) ) {
					$regionactions = [
						'projectadmin' => [
							$this->msg( 'openstackmanager-creationdisabled' )
						]
					];
				} else {
					$regionactions = [
						'projectadmin' => [
							$this->createActionLink(
								'openstackmanager-allocateaddress',
								[
									'action' => 'allocate',
									'project' => $projectName,
									'region' => $region
								]
							)
						]
					];
				}
				$addresses = $this->getAddresses( $projectName, $region );
				$regions .= $this->createRegionSection(
					$region, $projectName, $regionactions, $addresses
				);
			}
			$out .= $this->createProjectSection( $projectName, $projectactions, $regions );
		}
		$this->getOutput()->addHTML( $out );

		return true;
	}

	function getAddresses( $projectName, $region ) {
		$this->userNova->setRegion( $region );
		$headers = [
			'openstackmanager-address',
			'openstackmanager-instanceid',
			'openstackmanager-instancename',
			'openstackmanager-hostnames',
			'openstackmanager-actions'
		];
		$addresses = $this->userNova->getAddresses();
		$instances = $this->userNova->getInstances();
		$addressRows = [];
		/**
		 * @var $address OpenStackNovaAddress
		 */
		foreach ( $addresses as $address ) {
			$addressRow = [];
			$hostArr = [];
			$ip = $address->getPublicIP();
			$id = $address->getAddressId();
			$instanceosid = $address->getInstanceId();
			$this->pushResourceColumn( $addressRow, $ip );
			if ( $instanceosid ) {
				$instancename = $instances[$instanceosid]->getInstanceName();
				$instanceid = $instances[$instanceosid]->getInstanceId();
				$host = $instances[$instanceosid]->getHost();
				if ( $host ) {
					$this->pushRawResourceColumn(
						$addressRow,
						$this->createResourceLink( $host->getFullyQualifiedHostName() ),
						[
							'class' => 'instance-id'
						]
					);
				} else {
					$this->pushResourceColumn( $addressRow, $instanceid, [
						'class' => 'instance-id'
					] );
				}
				$this->pushResourceColumn( $addressRow, $instancename, [
					'class' => 'instance-name'
				] );
			} else {
				$this->pushResourceColumn( $addressRow, '' );
				$this->pushResourceColumn( $addressRow, '' );
			}
			$host = OpenStackNovaHost::getHostByPublicIP( $ip );
			if ( $host ) {
				$fqdns = $host->getAssociatedDomains();
				foreach ( $fqdns as $fqdn ) {
					$hostname = explode( '.', $fqdn );
					$hostname = $hostname[0];
					$link = $this->createActionLink(
						'openstackmanager-removehost-action',
						[
							'action' => 'removehost',
							'id' => $id, 'project' => $projectName,
							'region' => $region,
							'fqdn' => $fqdn,
							'hostname' => $hostname
						]
					);
					$hostArr[] = htmlentities( $fqdn ) . ' ' . $link;
				}
				$this->pushRawResourceColumn( $addressRow, $this->createResourceList( $hostArr ) );
			} else {
				$this->pushResourceColumn( $addressRow, '' );
			}
			$actions = [];

			$addressDataAttributes = [
				'data-ip' => $ip,
				'data-id' => $id,
				'data-project' => $projectName,
				'data-region' => $region,
				'class' => 'novaaddressaction disassociate-link',
			];

			if ( $instanceosid ) {
				$actions[] = $this->createActionLink(
					'openstackmanager-reassociateaddress',
					[
						'action' => 'associate',
						'id' => $id,
						'project' => $projectName,
						'region' => $region
					],
					null,
					[
						'class' => 'reassociate-link'
					]
				);
				$actions[] = $this->createActionLink(
					'openstackmanager-disassociateaddress',
					[
						'action' => 'disassociate',
						'id' => $id,
						'project' => $projectName,
						'region' => $region
					],
					null,
					$addressDataAttributes
				);
			} else {
				$actions[] = $this->createActionLink(
					'openstackmanager-releaseaddress',
					[
						'action' => 'release',
						'id' => $id,
						'project' => $projectName,
						'region' => $region
					]
				);
				$actions[] = $this->createActionLink(
					'openstackmanager-associateaddress',
					[
						'action' => 'associate',
						'id' => $id,
						'project' => $projectName,
						'region' => $region
					]
				);
			}
			$actions[] = $this->createActionLink(
				'openstackmanager-addhost',
				[
					'action' => 'addhost',
					'id' => $id,
					'project' => $projectName,
					'region' => $region
				]
			);
			$this->pushRawResourceColumn( $addressRow, $this->createResourceList( $actions ) );
			$addressRows[] = $addressRow;
		}
		if ( $addressRows ) {
			return $this->createResourceTable( $headers, $addressRows );
		} else {
			return '';
		}
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAllocateSubmit( $formData, $entryPoint = 'internal' ) {
		$address = $this->userNova->allocateAddress();
		if ( !$address ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-allocateaddressfailed' );
			return true;
		}
		$ip = $address->getPublicIP();
		$this->getOutput()->addWikiMsg( 'openstackmanager-allocatedaddress', $ip );
		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backaddresslist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryReleaseSubmit( $formData, $entryPoint = 'internal' ) {
		$id = $formData['id'];
		# TODO: Instead of throwing an error when host exist or the IP
		# is associated, remove all host entries and disassociate the IP
		# then release the address
		$outputPage = $this->getOutput();
		$address = $this->userNova->getAddress( $id );
		$ip = $address->getPublicIp();
		if ( $address->getInstanceId() ) {
			$outputPage->addWikiMsg( 'openstackmanager-cannotreleaseaddress', $ip );
			return true;
		}
		$host = OpenStackNovaHost::getHostByPublicIP( $ip );
		if ( $host ) {
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
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backaddresslist' )->escaped()
		);
		$outputPage->addHTML( $out );

		return true;
	}

	/**
	 * @param array $formData
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
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backaddresslist' )->escaped()
		);
		$outputPage->addHTML( $out );

		return true;
	}

	/**
	 * @param array $formData
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
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backaddresslist' )->escaped()
		);
		$outputPage->addHTML( $out );

		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddHostSubmit( $formData, $entryPoint = 'internal' ) {
		$id = $formData['id'];
		$address = $this->userNova->getAddress( $id );
		$ip = $address->getPublicIp();
		$outputPage = $this->getOutput();
		if ( !$address ) {
			$outputPage->addWikiMsg( 'openstackmanager-invalidaddress', $ip );
			return true;
		}
		$hostname = $formData['hostname'];
		$domain = $formData['domain'];
		$domain = OpenStackNovaDomain::getDomainByName( $domain );
		$hostbyip = OpenStackNovaHost::getHostByPublicIP( $ip );

		$hostnameText = $hostname . '.' . $domain->getFullyQualifiedDomainName();
		if ( $hostbyip ) {
			# We need to add an associateddomain, if the associateddomain doesn't already exist
			$success = $hostbyip->addAssociatedDomain( $hostnameText );
			if ( $success ) {
				$outputPage->addWikiMsg( 'openstackmanager-addedhost', $hostnameText, $ip );
			} else {
				$outputPage->addWikiMsg( 'openstackmanager-addhostfailed', $hostnameText, $ip );
			}
		} else {
			# This is a new host entry
			$host = OpenStackNovaHost::addPublicHost( $hostname, $ip, $domain );
			if ( $host ) {
				$outputPage->addWikiMsg( 'openstackmanager-addedhost', $hostnameText, $ip );
			} else {
				$outputPage->addWikiMsg( 'openstackmanager-addhostfailed', $hostnameText, $ip );
			}
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backaddresslist' )->escaped()
		);
		$outputPage->addHTML( $out );
		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryRemoveHostSubmit( $formData, $entryPoint = 'internal' ) {
		$id = $formData['id'];
		$address = $this->userNova->getAddress( $id );
		$outputPage = $this->getOutput();
		if ( !$address ) {
			$outputPage->addWikiMsg( 'openstackmanager-invalidaddress', $id );
			return true;
		}
		$ip = $address->getPublicIp();
		$hostname = $formData['hostname'];
		$fqdn = $formData['fqdn'];
		$host = OpenStackNovaHost::getHostByPublicIP( $ip );
		if ( $host ) {
			$records = $host->getAssociatedDomains();
			if ( count( $records ) > 1 ) {
				# We need to keep the host, but remove the fqdn
				$success = $host->deleteAssociatedDomain( $fqdn );
				if ( $success ) {
					$outputPage->addWikiMsg( 'openstackmanager-removedhost', $fqdn, $ip );
				} else {
					$outputPage->addWikiMsg( 'openstackmanager-removehostfailed', $ip, $fqdn );
				}
			} else {
				# We need to remove the host entry
				$success = $host->deleteHost();
				if ( $success ) {
					$outputPage->addWikiMsg( 'openstackmanager-removedhost', $fqdn, $ip );
				} else {
					$outputPage->addWikiMsg( 'openstackmanager-removehostfailed', $ip, $fqdn );
				}
			}
		} else {
			$outputPage->addWikiMsg( 'openstackmanager-nonexistenthost' );
		}
		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backaddresslist' )->escaped()
		);
		$outputPage->addHTML( $out );
		return true;
	}

	protected function getGroupName() {
		return 'nova';
	}
}

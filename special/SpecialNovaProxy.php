<?php

/**
 * Special page for managing labs instance proxies.
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaProxy extends SpecialNova {
	public $userLDAP;
	public $userNova;

	function __construct() {
		parent::__construct( 'NovaProxy' );
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

		$action = $this->getRequest()->getVal( 'action' );
		$this->projectName = $this->getRequest()->getText( 'project' );
		$this->project = OpenStackNovaProject::getProjectByName( $this->projectName );

		$region = $this->getRequest()->getVal( 'region' );
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $this->projectName );
		$this->userNova->setRegion( $region );

		if ( $action === "create" ) {
			if ( !$this->userLDAP->inProject( $this->projectName ) ) {
				$this->notInProject( $this->projectName );
				return;
			}
			$this->createProxy();
		} elseif ( $action === "delete" ) {
			if ( !$this->userLDAP->inProject( $this->projectName ) ) {
				$this->notInProject( $this->project );
				return;
			}
			$this->deleteProxy();
		} else {
			$this->listProxies();
		}
	}

	/**
	 * @return bool
	 */
	function createProxy() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-createproxy' ) );
		if ( !$this->userLDAP->inRole( 'projectadmin', $this->projectName ) ) {
			$this->notInRole( 'projectadmin', $this->projectName );
			return false;
		}
		$instance_keys = [];
		$region = $this->getRequest()->getText( 'region' );
		$this->userNova->setRegion( $region );
		$instances = $this->userNova->getInstances();
		foreach ( $instances as $instance ) {
			if ( $instance->getProject() === $this->projectName ) {
				if ( $instance->getHost() ) {
					$instancename = $instance->getHost()->getFullyQualifiedDisplayName();
					$instance_keys[$instancename] = $instancename;
				}
			}
		}
		ksort( $instance_keys );

		$domains = OpenStackNovaDomain::getAllDomains( 'public' );
		$domain_keys = [];
		foreach ( $domains as $domain ) {
			$domainname = $domain->getDomainName();
			$fqdn = $domain->getFullyQualifiedDomainName();
			$domain_keys[$fqdn] = $domainname;
		}
		ksort( $domain_keys );

		$proxyInfo = [];
		$proxyInfo['proxyname'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-proxyname',
			'default' => '',
			'section' => 'frontend',
			'name' => 'proxyname',
		];
		$proxyInfo['domain'] = [
			'type' => 'select',
			'options' => $domain_keys,
			'label-message' => 'openstackmanager-dnsdomain',
			'section' => 'frontend',
			'name' => 'domain',
			'default' => 'wmflabs'
		];
		$proxyInfo['backendhost'] = [
			'type' => 'select',
			'label-message' => 'openstackmanager-proxybackend',
			'options' => $instance_keys,
			'section' => 'backend',
			'name' => 'backendhost',
		];
		$proxyInfo['backendport'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-proxyport',
			'default' => '80',
			'section' => 'backend',
			'name' => 'backendport',
		];
		$proxyInfo['action'] = [
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		];
		$proxyInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$proxyInfo['project'] = [
			'type' => 'hidden',
			'default' => $this->projectName,
			'name' => 'project',
		];

		$proxyForm = new HTMLForm(
			$proxyInfo,
			$this->getContext(),
			'openstackmanager-novaproxy'
		);
		$proxyForm->setSubmitID( 'novaproxy-form-createproxysubmit' );
		$proxyForm->setSubmitCallback( [ $this, 'tryCreateSubmit' ] );
		$proxyForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteProxy() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-deleteproxy' ) );
		if ( !$this->userLDAP->inRole( 'projectadmin', $this->projectName ) ) {
			$this->notInRole( 'projectadmin', $this->projectName );
			return false;
		}
		$proxyfqdn = $this->getRequest()->getText( 'proxyfqdn' );
		$region = $this->getRequest()->getText( 'region' );
		if ( !$this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deleteproxy-confirm', $proxyfqdn );
		}
		$proxyInfo = [];
		$proxyInfo['proxyfqdn'] = [
			'type' => 'hidden',
			'default' => $proxyfqdn,
			'name' => 'proxyfqdn',
		];
		$proxyInfo['project'] = [
			'type' => 'hidden',
			'default' => $this->projectName,
			'name' => 'project',
		];
		$proxyInfo['action'] = [
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		];
		$proxyInfo['region'] = [
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		];
		$proxyForm = new HTMLForm(
			$proxyInfo,
			$this->getContext(),
			'openstackmanager-novaproxy'
		);
		$proxyForm->setSubmitID( 'novaproxy-form-deleteproxysubmit' );
		$proxyForm->setSubmitCallback( [ $this, 'tryDeleteSubmit' ] );
		$proxyForm->show();

		return true;
	}

	/**
	 * @return void
	 */
	function listProxies() {
		global $wgOpenStackManagerReadOnlyRegions;
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.openstack' );
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-proxylist' ) );

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
			$regions = '';
			$projectName = $project->getProjectName();
			if ( !in_array( $projectName, $projectfilter ) ) {
				continue;
			}
			$this->userNova->setProject( $projectName );
			$projectactions = [ 'projectadmin' => [] ];
			foreach ( $this->userNova->getRegions( 'proxy' ) as $region ) {
				$actions = [ 'projectadmin' => [] ];
				if ( in_array( $region, $wgOpenStackManagerReadOnlyRegions ) ) {
					$actions['projectadmin'][] = $this->msg( 'openstackmanager-creationdisabled' );
				} else {
					$actions['projectadmin'][] = $this->createActionLink(
						'openstackmanager-createproxy',
						[
							'action' => 'create',
							'project' => $projectName,
							'region' => $region
						]
					);
				}
				$regions .= $this->createRegionSection(
					$region,
					$projectName,
					$actions,
					$this->getProxies( $projectName, $region )
				);
			}
			$out .= $this->createProjectSection( $projectName, $projectactions, $regions );
		}

		$this->getOutput()->addHTML( $out );
	}

	function getProxies( $projectName, $region ) {
		$this->userNova->setProject( $projectName );
		$this->userNova->setRegion( $region );
		$proxies = $this->userNova->getProxiesForProject();
		$proxyRows = [];
		foreach ( $proxies as $proxy ) {
			$fqdn = $proxy->getProxyFQDN();
			if ( $fqdn ) {
				$proxyRow = [];
				$this->pushResourceColumn( $proxyRow, $fqdn );
				$this->pushResourceColumn( $proxyRow, $proxy->getBackend() );

				$actions = [];
				$actions[] = $this->createActionLink(
					'openstackmanager-delete',
					[
						'action' => 'delete',
						'proxyfqdn' => $fqdn,
						'project' => $projectName,
						'region' => $region
					]
				);
				$this->pushRawResourceColumn( $proxyRow, $this->createResourceList( $actions ) );

				$proxyRows[] = $proxyRow;
			}
		}
		if ( $proxyRows ) {
			$headers = [
				'openstackmanager-proxyname',
				'openstackmanager-proxybackend',
				'openstackmanager-actions'
			];
			$out = $this->createResourceTable( $headers, $proxyRows );
		} else {
			$out = '';
		}

		return $out;
	}

	function addHost( $hostName, $domain, $ip ) {
		$domain = OpenStackNovaDomain::getDomainByName( $domain );
		$hostbyip = OpenStackNovaHost::getHostByPublicIP( $ip );
		$fqdn = $hostName . '.' . $domain->getFullyQualifiedDomainName();

		if ( $hostbyip ) {
			# We need to add an associateddomain, if the associateddomain doesn't already exist
			$success = $hostbyip->addAssociatedDomain( $fqdn );
			if ( !$success ) {
				return false;
			}
		} else {
			# This is a new host entry
			$host = OpenStackNovaHost::addPublicHost( $hostName, $ip, $domain );
			if ( !$host ) {
				return false;
			}
		}

		return true;
	}

	function deleteHost( $fqdn, $ip ) {
		$host = OpenStackNovaHost::getHostByPublicIP( $ip );
		if ( $host ) {
			$records = $host->getAssociatedDomains();
			if ( count( $records ) > 1 ) {
				# We need to keep the host, but remove the fqdn
				$success = $host->deleteAssociatedDomain( $fqdn );
				if ( $success ) {
					return true;
				} else {
					return false;
				}
			} else {
				# We need to remove the host entry
				$success = $host->deleteHost();
				if ( $success ) {
					return true;
				} else {
					return false;
				}
			}
		} else {
			# No such host!
			return false;
		}
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOpenStackManagerProxyGateways;

		$outputPage = $this->getOutput();
		$fqdn = $formData['proxyfqdn'];
		$region = $formData['region'];
		$goback = '<br />';
		$goback .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backproxylist' )->escaped()
		);

		$success =  $this->userNova->deleteProxy( $fqdn );
		if ( $success ) {
			$success = $this->deleteHost( $fqdn, $wgOpenStackManagerProxyGateways[$region] );
			if ( !$success ) {
				$outputPage->addWikiMsg( 'openstackmanager-removehostfailed', $fqdn );
			}
		} else {
			$outputPage->addWikiMsg( 'openstackmanager-deleteproxyfailed', $fqdn );
			$outputPage->addHTML( $goback );
			return true;
		}

		$outputPage->addWikiMsg( 'openstackmanager-deleteproxysuccess', $fqdn );
		$outputPage->addHTML( $goback );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOpenStackManagerProxyGateways;
		$goback = '<br />';
		$goback .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backproxylist' )->escaped()
		);

		$project = $formData['project'];
		$backendPort = $formData['backendport'];
		$backendHost = $formData['backendhost'];
		$region = $formData['region'];
		$proxyName = $formData['proxyname'];
		$proxyDomain = $formData['domain'];
		$outputPage = $this->getOutput();
		$gatewayIP = $wgOpenStackManagerProxyGateways[$region];

		if ( !( array_key_exists( $region, $wgOpenStackManagerProxyGateways ) ) ) {
			$outputPage->addWikiMsg( 'openstackmanager-addhostfailed', $proxyName, $gatewayIP );
			$outputPage->addHTML( $goback );
			return true;
		}

		$domain = OpenStackNovaDomain::getDomainByName( $proxyDomain );
		$gatewayhostbyip = OpenStackNovaHost::getHostByPublicIP( $gatewayIP );
		$fqdn = $proxyName . '.' . $domain->getFullyQualifiedDomainName();
		$dnsSuccess = $this->addHost( $proxyName, $proxyDomain, $gatewayIP );
		if ( $dnsSuccess ) {
			$outputPage->addWikiMsg( 'openstackmanager-addedhost', $proxyName, $gatewayIP );
		} else {
			$outputPage->addWikiMsg( 'openstackmanager-addhostfailed', $proxyName, $gatewayIP );
			$outputPage->addHTML( $goback );
			return true;
		}

		# DNS looks good, now we can set up the proxy.
		$newProxy =  $this->userNova->createProxy( $fqdn, $backendHost, $backendPort );

		if ( $newProxy ) {
			$outputPage->addWikiMsg(
				'openstackmanager-createdproxy', $fqdn, $backendHost . ":" . $backendPort
			);
		} else {
			$outputPage->addWikiMsg( 'openstackmanager-createproxyfailed', $fqdn );
			$this->deleteHost( $fqdn, $gatewayIP );
			$outputPage->addHTML( $goback );
			return true;
		}

		$outputPage->addHTML( $goback );
		return true;
	}

	protected function getGroupName() {
		return 'nova';
	}
}

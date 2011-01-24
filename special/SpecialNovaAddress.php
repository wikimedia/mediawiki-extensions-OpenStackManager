<?php
class SpecialNovaAddress extends SpecialNova {

	var $adminNova;
	var $userNova;

	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaAddress' );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;
		global $wgOpenStackManagerNovaAdminKeys;

		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return false;
		}
		$this->userLDAP = new OpenStackNovaUser();
                if ( ! $this->userLDAP->exists() ) {
                        $this->noCredentials();
                        return true;
                }
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );

        	$action = $wgRequest->getVal( 'action' );
		if ( $action == "allocate" ) {
			$this->allocateAddress();
		} else if ( $action == "release" ) {
			$this->releaseAddress();
		} else if ( $action == "associate" ) {
			$this->associateAddress();
		} else if ( $action == "disassociate" ) {
			$this->disassociateAddress();
		} else if ( $action == "addhost" ) {
			$this->addHost();
		} else if ( $action == "removehost" ) {
			$this->removehost();
		} else {
			$this->listAddresses();
		}
	}

	/**
	 * @return bool
	 */
	function allocateAddress() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-allocateaddress' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-allocateaddress-confirm', array(), $project ) );
			$wgOut->addHTML( $out );
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'allocate',
		);

		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
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
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-releaseaddress' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		$ip = $wgRequest->getText( 'ip' );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-releaseaddress-confirm', array(), $ip ) );
			$wgOut->addHTML( $out );
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['ip'] = array(
			'type' => 'hidden',
			'default' => $ip,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'release',
		);
		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
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
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-associateaddress' ) );

		$ip = $wgRequest->getText( 'ip' );
		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		$instances = $this->userNova->getInstances();
		$instance_keys = array();
		foreach ( $instances as $instance ) {
			if ( $instance->getOwner() == $project ) {
				$instancename = $instance->getInstanceName();
				$instanceid = $instance->getInstanceId();
				$instance_keys["$instancename"] = $instanceid;
			}
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['ip'] = array(
			'type' => 'hidden',
			'default' => $ip,
		);
		$addressInfo['instanceid'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-instancename',
			'options' => $instance_keys,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'associate',
		);
		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
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
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-disassociateaddress' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		$ip = $wgRequest->getText( 'ip' );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-disassociateaddress-confirm', array(), $ip ) );
			$wgOut->addHTML( $out );
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['ip'] = array(
			'type' => 'hidden',
			'default' => $ip,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'disassociate',
		);
		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
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
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addhost' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$ip = $wgRequest->getText( 'ip' );
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['ip'] = array(
			'type' => 'hidden',
			'default' => $ip,
		);
		$addressInfo['hostname'] = array(
			'type' => 'text',
			'default' => '',
			'validation-callback' => array( $this, 'validateHostName' ),
			'label-message' => 'openstackmanager-hostname',
		);
		$domains = OpenStackNovaDomain::getAllDomains( 'public' );
		$domain_keys = array();
		foreach ( $domains as $domain ) {
			$domainname = $domain->getDomainName();
			$domain_keys["$domainname"] = $domainname;
		}
		$addressInfo['domain'] = array(
			'type' => 'select',
			'options' => $domain_keys,
			'label-message' => 'openstackmanager-dnsdomain',
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addhost',
		);
		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
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
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-removehost' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		$ip = $wgRequest->getText( 'ip' );
		$domain = $wgRequest->getText( 'domain' );
		$hostname = $wgRequest->getText( 'hostname' );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removehost-confirm', array(), $hostname, $ip ) );
			$wgOut->addHTML( $out );
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['ip'] = array(
			'type' => 'hidden',
			'default' => $ip,
		);
		$addressInfo['domain'] = array(
			'type' => 'hidden',
			'default' => $domain,
		);
		$addressInfo['hostname'] = array(
			'type' => 'hidden',
			'default' => $hostname,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'removehost',
		);
		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
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
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addresslist' ) );

		$userProjects = $this->userLDAP->getProjects();
		$out = '';
		$sk = $wgUser->getSkin();
		$header = Html::element( 'th', array(), wfMsg( 'openstackmanager-address' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instanceid' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instancename' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-hostnames' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$addresses = $this->adminNova->getAddresses();
		$projectArr = array();
		foreach ( $addresses as $address ) {
			$ip = $address->getPublicIP();
			$instanceid = $address->getInstanceId();
			$project = $address->getProject();
			$addressOut = Html::element( 'td', array(), $ip );
			if ( $instanceid ) {
				$addressOut .= Html::element( 'td', array(), $instanceid );
				$instance = $this->adminNova->getInstance( $instanceid );
				$instancename = $instance->getInstanceName();
				$addressOut .= Html::element( 'td', array(), $instancename );
			} else {
				$addressOut .= Html::element( 'td', array(), '' );
				$addressOut .= Html::element( 'td', array(), '' );
			}
			$hosts = OpenStackNovaHost::getHostsByIP( $ip );
			if ( $hosts ) {
				$hostsOut = '';
				$msg = wfMsg( 'openstackmanager-removehost-action' );
				foreach ( $hosts as $host ) {
					$domain = $host->getDomain();
					$fqdns = $host->getAssociatedDomains();
					foreach ( $fqdns as $fqdn ) {
						$hostname = explode( '.', $fqdn );
						$hostname = $hostname[0];
						$link = $sk->link( $this->getTitle(), $msg, array(),
								   array( 'action' => 'removehost', 'ip' => $ip, 'project' => $project, 'domain' => $domain->getDomainName(), 'hostname' => $hostname ), array() );
						$hostOut = htmlentities( $fqdn ) . ' ' . $link;
						$hostsOut .= Html::rawElement( 'li', array(), $hostOut );
					}
				}
				$hostsOut = Html::rawElement( 'ul', array(), $hostsOut );
				$addressOut .= Html::rawElement( 'td', array(), $hostsOut );
			} else {
				$addressOut .= Html::element( 'td', array(), '' );
			}
			$actions = '';
			if ( $instanceid ) {
				$msg = wfMsg( 'openstackmanager-reassociateaddress' );
			} else {
				$msg = wfMsg( 'openstackmanager-releaseaddress' );
				$link = $sk->link( $this->getTitle(), $msg, array(),
						   array( 'action' => 'release', 'ip' => $ip, 'project' => $project ), array() );
				$actions = Html::rawElement( 'li', array(), $link );
				$msg = wfMsg( 'openstackmanager-associateaddress' );
			}
			$link = $sk->link( $this->getTitle(), $msg, array(),
					   array( 'action' => 'associate', 'ip' => $ip, 'project' => $project ), array() );
			$actions .= Html::rawElement( 'li', array(), $link );
			if ( $instanceid ) {
				$msg = wfMsg( 'openstackmanager-disassociateaddress' );
				$link = $sk->link( $this->getTitle(), $msg, array(),
						   array( 'action' => 'disassociate', 'ip' => $ip, 'project' => $project ), array() );
				$actions .= Html::rawElement( 'li', array(), $link );
			}
			$msg = wfMsg( 'openstackmanager-addhost' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
					   array( 'action' => 'addhost', 'ip' => $ip, 'project' => $project ), array() );
			$actions .= Html::rawElement( 'li', array(), $link );
			$actions = Html::rawElement( 'ul', array(), $actions );
			$addressOut .= Html::rawElement( 'td', array(), $actions );
			$projectArr["$project"] = Html::rawElement( 'tr', array(), $addressOut );
		}
		foreach ( $userProjects as $project ) {
			$out .= Html::element( 'h2', array(), $project );
			$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-allocateaddress' ), array(), array( 'action' => 'allocate', 'project' => $project ), array() );
			if ( isset( $projectArr["$project"] ) ) {
				$projectOut = $header;
				$projectOut .= $projectArr["$project"];
				$out .= Html::rawElement( 'table',
							  array( 'id' => 'novainstancelist', 'class' => 'wikitable' ), $projectOut );
			}
		}
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAllocateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$address = $this->userNova->allocateAddress();
		if ( ! $address ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-allocateaddressfailed' ) );
			$wgOut->addHTML( $out );
			return true;
		}
		$ip = $address->getPublicIP();
		$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-allocatedaddress' , array(), $ip ) );
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryReleaseSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$ip = $formData['ip'];
		#TODO: Instead of throwing an error when host exist or the IP
		# is associated, remove all host entries and disassociate the IP
		# then release the address
		$hosts = OpenStackNovaHost::getHostsByIP( $ip );
		if ( $hosts ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-cannotreleaseaddress', array(), $ip ) );
			$wgOut->addHTML( $out );
			return true;
		}
		$address = $this->adminNova->getAddress( $ip );
		if ( $address->getInstanceId() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-cannotreleaseaddress', array(), $ip ) );
			$wgOut->addHTML( $out );
			return true;
		}
		$success = $this->userNova->releaseAddress( $ip );
		if ( $success ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-releasedaddress', array(), $ip ) );
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-releaseaddressfailed', array(), $ip ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAssociateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$instanceid = $formData['instanceid'];
		$ip = $formData['ip'];
		$address = $this->userNova->associateAddress( $instanceid, $ip );
		if ( $address ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-associatedaddress', array(), $ip, $instanceid ) );
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-associateaddressfailed', array(), $ip, $instanceid ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDisassociateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$ip = $formData['ip'];
		$address = $this->userNova->disassociateAddress( $ip );
		if ( $address ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-disassociatedaddress', array(), $ip ) );
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-disassociateaddressfailed', array(), $ip ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddHostSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$ip = $formData['ip'];
		$project = $formData['project'];
		$address = $this->adminNova->getAddress( $ip );
		if ( ! $address ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-invalidaddress', array(), $ip ) );
			$wgOut->addHTML( $out );
			return true;
		}
		if ( $address->getProject() != $project ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-invalidaddressforproject', array(), $ip ) );
			$wgOut->addHTML( $out );
			return true;
		}
		$hostname = $formData['hostname'];
		$domain = $formData['domain'];
		$domain = OpenStackNovaDomain::getDomainByName( $domain );
		$hostbyname = OpenStackNovaHost::getHostByName( $hostname, $domain );
		$hostbyip = OpenStackNovaHost::getHostByIP( $ip, $domain );

		// FIXME: Usages of $instanceid are undefined

		if ( $hostbyname ) {
			# We need to add an arecord, if the arecord doesn't already exist
			$success = $hostbyname->addARecord( $ip );
			if ( $success ) {
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-addedhost', array(), $hostname, $ip ) );
			} else {
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-addhostfailed', array(), $ip, $instanceid ) );
			}
		} else if ( $hostbyip ) {
			# We need to add an associateddomain, if the associateddomain doesn't already exist
			$success = $hostbyip->addAssociatedDomain( $hostname . '.' . $domain->getFullyQualifiedDomainName() );
			if ( $success ) {
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-addedhost', array(), $hostname, $ip ) );
			} else {
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-addhostfailed', array(), $ip, $instanceid ) );
			}
		} else {
			# This is a new host entry
			$host = OpenStackNovaHost::addPublicHost( $hostname, $ip, $domain );
			if ( $host ) {
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-addedhost', array(), $hostname, $ip ) );
			} else {
				$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-addhostfailed', array(), $ip, $instanceid ) );
			}
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryRemoveHostSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$ip = $formData['ip'];
		$project = $formData['project'];
		$address = $this->adminNova->getAddress( $ip );
		if ( ! $address ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-invalidaddress', array(), $ip ) );
			$wgOut->addHTML( $out );
			return true;
		}
		if ( $address->getProject() != $project ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-invalidaddressforproject', array(), $ip ) );
			$wgOut->addHTML( $out );
			return true;
		}
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
					$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removedhost', array(), $hostname, $ip ) );
				} else {
					$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removehostfailed', array(), $ip, $instanceid ) );
				}
			} else {
				# We need to remove the host entry
				$success = OpenStackNovaHost::deleteHost( $hostname, $domain );
				if ( $success ) {
					$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removedhost', array(), $hostname, $ip ) );
				} else {
					$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removehostfailed', array(), $ip, $instanceid ) );
				}
			}
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-nonexistenthost', array() ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $hostname
	 * @param  $alldata
	 * @return bool|string
	 */
	function validateHostName( $hostname, $alldata ) {
		if ( ! preg_match( "/^[a-z][a-z0-9\-]*$/", $hostname ) ) {
			return Xml::element( 'span', array( 'class' => 'error' ), wfMsg( 'openstackmanager-badinstancename' ) );
		} else {
			return true;
		}
	}
}

class SpecialNovaAddressForm extends HTMLForm {
}

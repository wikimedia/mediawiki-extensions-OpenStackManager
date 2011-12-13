<?php
class SpecialNovaSecurityGroup extends SpecialNova {

	/**
	 * @var OpenStackNovaController
	 */
	var $adminNova, $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaSecurityGroup' );
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
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );

		$action = $wgRequest->getVal( 'action' );

		if ( $action == "create" ) {
			$this->createSecurityGroup();
		} elseif ( $action == "delete" ) {
			$this->deleteSecurityGroup();
		} elseif ( $action == "configure" ) {
			// Currently unsupported
			#$this->configureSecurityGroup();
			$this->listSecurityGroups();
		} elseif ( $action == "addrule" ) {
			$this->addRule();
		} elseif ( $action == "removerule" ) {
			$this->removeRule();
		} else {
			$this->listSecurityGroups();
		}
	}

	/**
	 * @return bool
	 */
	function createSecurityGroup() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-createsecuritygroup' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$securityGroupInfo = array();
		$securityGroupInfo['groupname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygroupname',
			'default' => '',
			'name' => 'groupname',
		);
		$securityGroupInfo['description'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygroupdescription',
			'default' => '',
			'name' => 'description',
		);
		$securityGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);

		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$securityGroupForm = new SpecialNovaSecurityGroupForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
		$securityGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaSecurityGroup' ) );
		$securityGroupForm->setSubmitID( 'openstackmanager-novainstance-createsecuritygroupsubmit' );
		$securityGroupForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$securityGroupForm->show();

		return true;

	}

	/**
	 * @return bool
	 */
	function configureSecurityGroup() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-configuresecuritygroup' ) );

		$securitygroupname = $wgRequest->getText( 'groupname' );
		$project = $wgRequest->getText( 'project' );
		$securitygroup = $this->adminNova->getSecurityGroup( $securitygroupname, $project );
		$description = $securitygroup->getGroupDescription();
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$securityGroupInfo = array();
		$securityGroupInfo['groupname'] = array(
			'type' => 'hidden',
			'default' => $securitygroupname,
			'name' => 'groupname',
		);
		$securityGroupInfo['description'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygroupdescription',
			'default' => $description,
			'name' => 'description',
		);
		$securityGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);

		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'configure',
			'name' => 'action',
		);

		$securityGroupForm = new SpecialNovaSecurityGroupForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
		$securityGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaSecurityGroup' ) );
		$securityGroupForm->setSubmitID( 'openstackmanager-novainstance-configuresecuritygroupsubmit' );
		$securityGroupForm->setSubmitCallback( array( $this, 'tryConfigureSubmit' ) );
		$securityGroupForm->show();

		return true;

	}

	/**
	 * @return bool
	 */
	function deleteSecurityGroup() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-deletesecuritygroup' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$securitygroupname = $wgRequest->getText( 'groupname' );
		if ( ! $wgRequest->wasPosted() ) {
			$wgOut->addWikiMsg( 'openstackmanager-deletesecuritygroup-confirm', $securitygroupname );
		}
		$securityGroupInfo = array();
		$securityGroupInfo['groupname'] = array(
			'type' => 'hidden',
			'default' => $securitygroupname,
			'name' => 'groupname',
		);
		$securityGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$securityGroupForm = new SpecialNovaSecurityGroupForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
		$securityGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaSecurityGroup' ) );
		$securityGroupForm->setSubmitID( 'novainstance-form-deletesecuritygroupsubmit' );
		$securityGroupForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$securityGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function listSecurityGroups() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-securitygrouplist' ) );

		$userProjects = $this->userLDAP->getProjects();
		$sk = $wgOut->getSkin();
		$out = '';
		$groupheader = Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygroupname' ) );
		$groupheader .= Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygroupdescription' ) );
		$groupheader .= Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygrouprule' ) );
		$groupheader .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$ruleheader = Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygrouprule-fromport' ) );
		$ruleheader .= Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygrouprule-toport' ) );
		$ruleheader .= Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygrouprule-protocol' ) );
		$ruleheader .= Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygrouprule-ipranges' ) );
		$ruleheader .= Html::element( 'th', array(), wfMsg( 'openstackmanager-securitygrouprule-groups' ) );
		$ruleheader .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$projectArr = array();
		$securityGroups = $this->adminNova->getSecurityGroups();
		foreach ( $securityGroups as $group ) {
			$project = $group->getOwner();
			if ( ! in_array( $project, $userProjects ) ) {
				continue;
			}
			$groupname = $group->getGroupName();
			$groupOut = Html::element( 'td', array(), $groupname );
			$groupOut .= Html::element( 'td', array(), $group->getGroupDescription() );
			# Add rules
			$rules = $group->getRules();
			if ( $rules ) {
				$rulesOut = $ruleheader;
				foreach ( $rules as $rule ) {
					$fromport = $rule->getFromPort();
					$toport = $rule->getToPort();
					$ipprotocol = $rule->getIPProtocol();
					$ruleOut = Html::element( 'td', array(), $fromport );
					$ruleOut .= Html::element( 'td', array(), $toport );
					$ruleOut .= Html::element( 'td', array(), $ipprotocol );
					$ranges = $rule->getIPRanges();
					if ( $ranges ) {
						$rangesOut = '';
						foreach ( $ranges as $range ) {
							$rangesOut .= Html::element( 'li', array(), $range );
						}
						$rangesOut = Html::rawElement( 'ul', array(), $rangesOut );
						$ruleOut .= Html::rawElement( 'td', array(), $rangesOut );
					} else {
						$ruleOut .= Html::rawElement( 'td', array(), '' );
					}
					$sourcegroups = $rule->getGroups();
					$groupinfo = array();
					if ( $sourcegroups ) {
						$sourcegroupsOut = '';
						foreach ( $sourcegroups as $sourcegroup ) {
							$groupinfo[] = $sourcegroup['groupname'] . ':' . $sourcegroup['project'];
							$sourcegroupinfo = $sourcegroup['groupname'] . ' (' . $sourcegroup['project'] . ')';
							$sourcegroupsOut .= Html::element( 'li', array(), $sourcegroupinfo );
						}
						$sourcegroupsOut = Html::rawElement( 'ul', array(), $sourcegroupsOut );
						$ruleOut .= Html::rawElement( 'td', array(), $sourcegroupsOut );
					} else {
						$ruleOut .= Html::rawElement( 'td', array(), '' );
					}
					$msg = wfMsgHtml( 'openstackmanager-removerule-action' );
					$args = array(  'action' => 'removerule',
							'project' => $project,
							'groupname' => $groupname,
							'fromport' => $fromport,
							'toport' => $toport,
							'protocol' => $ipprotocol,
							'ranges' => implode( ',', $ranges ),
							'groups' => implode( ',', $groupinfo ) );
					$link = $sk->link( $this->getTitle(), $msg, array(), $args );
					$actions = Html::rawElement( 'li', array(), $link );
					$actions = Html::rawElement( 'ul', array(), $actions );
					$ruleOut .= Html::rawElement( 'td', array(), $actions );
					$rulesOut .= Html::rawElement( 'tr', array(), $ruleOut );
				}
				$rulesOut = Html::rawElement( 'table', array( 'id' => 'novasecuritygrouplist', 'class' => 'wikitable sortable collapsible' ), $rulesOut );
				$groupOut .= Html::rawElement( 'td', array(), $rulesOut );
			} else {
				$groupOut .= Html::rawElement( 'td', array(), '' );
			}
			$msg = wfMsgHtml( 'openstackmanager-delete' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
								  array( 'action' => 'delete',
									   'project' => $project,
									   'groupname' => $group->getGroupName() ) );
			$actions = Html::rawElement( 'li', array(), $link );
			#$msg = wfMsgHtml( 'openstackmanager-configure' );
			#$link = $sk->link( $this->getTitle(), $msg, array(),
			#					   array( 'action' => 'configure',
			#							'project' => $project,
			#							'groupname' => $group->getGroupName() ) );
			#$actions .= Html::rawElement( 'li', array(), $link );
			$msg = wfMsgHtml( 'openstackmanager-addrule-action' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
								   array( 'action' => 'addrule',
										'project' => $project,
										'groupname' => $group->getGroupName() ) );
			$actions .= Html::rawElement( 'li', array(), $link );
			$actions = Html::rawElement( 'ul', array(), $actions );
			$groupOut .= Html::rawElement( 'td', array(), $actions );
			if ( isset( $projectArr["$project"] ) ) {
				$projectArr["$project"] .= Html::rawElement( 'tr', array(), $groupOut );
			} else {
				$projectArr["$project"] = Html::rawElement( 'tr', array(), $groupOut );
			}
		}
		foreach ( $userProjects as $project ) {
			$out .= Html::element( 'h2', array(), $project );
			$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-createnewsecuritygroup' ), array(),
							   array( 'action' => 'create', 'project' => $project ) );
			if ( isset( $projectArr["$project"] ) ) {
				$projectOut = $groupheader;
				$projectOut .= $projectArr["$project"];
				$out .= Html::rawElement( 'table',
										  array( 'id' => 'novainstancelist', 'class' => 'wikitable sortable collapsible' ), $projectOut );
			}
		}

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @return bool
	 */
	function addRule() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addrule' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$group_keys = array();
		$info = array();
		$securityGroups = $this->adminNova->getSecurityGroups();
		foreach ( $securityGroups as $securityGroup ) {
			$securityGroupName = $securityGroup->getGroupName();
			$securityGroupProject = $securityGroup->getOwner();
			$info["$securityGroupProject"]["$securityGroupName"] = $securityGroupName . ':' . $securityGroupProject;
		}
		$group_keys = $info;
		$securitygroupname = $wgRequest->getText( 'groupname' );
		$securityGroupInfo = array();
		$securityGroupInfo['groupname'] = array(
			'type' => 'hidden',
			'default' => $securitygroupname,
			'name' => 'groupname',
		);
		$securityGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$securityGroupInfo['fromport'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygrouprule-fromport',
			'default' => '',
			'name' => 'fromport',
		);
		$securityGroupInfo['toport'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygrouprule-toport',
			'default' => '',
			'name' => 'toport',
		);
		$securityGroupInfo['protocol'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-securitygrouprule-protocol',
			'options' => array( '' => '', 'icmp' => 'icmp', 'tcp' => 'tcp', 'udp' => 'udp' ),
			'name' => 'protocol',
		);
		$securityGroupInfo['ranges'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygrouprule-ranges',
			'help-message' => 'openstackmanager-securitygrouprule-ranges-help',
			'default' => '',
			'name' => 'ranges',
		);
		$securityGroupInfo['groups'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-securitygrouprule-groups',
			'help-message' => 'openstackmanager-securitygrouprule-groups-help',
			'options' => $group_keys,
			'name' => 'groups',
		);
		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addrule',
			'name' => 'action',
		);
		$securityGroupForm = new SpecialNovaSecurityGroupForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
		$securityGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaSecurityGroup' ) );
		$securityGroupForm->setSubmitID( 'novainstance-form-removerulesubmit' );
		$securityGroupForm->setSubmitCallback( array( $this, 'tryAddRuleSubmit' ) );
		$securityGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function removeRule() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-removerule' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$securitygroupname = $wgRequest->getText( 'groupname' );
		if ( ! $wgRequest->wasPosted() ) {
			$wgOut->addWikiMsg( 'openstackmanager-removerule-confirm', $securitygroupname );
		}
		$securityGroupInfo = array();
		$securityGroupInfo['groupname'] = array(
			'type' => 'hidden',
			'default' => $securitygroupname,
			'name' => 'groupname',
		);
		$securityGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$securityGroupInfo['fromport'] = array(
			'type' => 'hidden',
			'default' => $wgRequest->getText( 'fromport' ),
			'name' => 'fromport',
		);
		$securityGroupInfo['toport'] = array(
			'type' => 'hidden',
			'default' => $wgRequest->getText( 'toport' ),
			'name' => 'toport',
		);
		$securityGroupInfo['protocol'] = array(
			'type' => 'hidden',
			'default' => $wgRequest->getText( 'protocol' ),
			'name' => 'protocol',
		);
		if ( $wgRequest->getText( 'ranges' ) ) {
			$securityGroupInfo['ranges'] = array(
				'type' => 'hidden',
				'default' => $wgRequest->getText( 'ranges' ),
				'name' => 'ranges',
			);
		}
		if ( $wgRequest->getText( 'groups' ) ) {
			$securityGroupInfo['groups'] = array(
				'type' => 'hidden',
				'default' => $wgRequest->getText( 'groups' ),
				'name' => 'groups',
			);
		}
		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'removerule',
			'name' => 'action',
		);
		$securityGroupForm = new SpecialNovaSecurityGroupForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
		$securityGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaSecurityGroup' ) );
		$securityGroupForm->setSubmitID( 'novainstance-form-removerulesubmit' );
		$securityGroupForm->setSubmitCallback( array( $this, 'tryRemoveRuleSubmit' ) );
		$securityGroupForm->show();

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$project = $formData['project'];
		$groupname = $formData['groupname'];
		$description = $formData['description'];
		$userCredentials = $this->userLDAP->getCredentials( $project );
		$this->userNova = new OpenStackNovaController( $userCredentials );
		$securitygroup = $this->userNova->createSecurityGroup( $groupname, $description );
		if ( $securitygroup ) {
			$wgOut->addWikiMsg( 'openstackmanager-createdsecuritygroup' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-createsecuritygroupfailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

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

		$project = $formData['project'];
		$userCredentials = $this->userLDAP->getCredentials( $project );
		$this->userNova = new OpenStackNovaController( $userCredentials );
		$securitygroup = $this->adminNova->getSecurityGroup( $formData['groupname'], $project );
		if ( !$securitygroup ) {
			$wgOut->addWikiMsg( 'openstackmanager-nonexistantsecuritygroup' );
			return true;
		}
		$groupname = $securitygroup->getGroupName();
		$success = $this->userNova->deleteSecurityGroup( $groupname );
		if ( $success ) {
			# TODO: Ensure group isn't being used
			$wgOut->addWikiMsg( 'openstackmanager-deletedsecuritygroup' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-deletesecuritygroupfailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryConfigureSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$project = $formData['project'];
		$groupname = $formData['groupname'];
		$description = $formData['description'];
		$group = $this->adminNova->getSecurityGroup( $groupname, $project );
		if ( $group ) {
			# This isn't a supported function in the API for now. Leave this action out for now
			$success = $this->userNova->modifySecurityGroup( $groupname, array( 'description' => $description ) );
			if ( $success ) {
				$wgOut->addWikiMsg( 'openstackmanager-modifiedgroup' );
			} else {
				$wgOut->addWikiMsg( 'openstackmanager-modifygroupfailed' );
			}
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-nonexistantgroup' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddRuleSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$project = $formData['project'];
		$fromport = $formData['fromport'];
		$toport = $formData['toport'];
		$protocol = $formData['protocol'];
		if ( isset( $formData['ranges'] ) && $formData['ranges'] ) {
			$ranges = explode( ',', $formData['ranges'] );
		} else {
			$ranges = array();
		}
		$groups = array();
		foreach ( $formData['groups'] as $group ) {
			$group = explode( ':', $group );
			$groups[] = array( 'groupname' => $group[0], 'project' => $group[1] );
		}
		$userCredentials = $this->userLDAP->getCredentials( $project );
		$this->userNova = new OpenStackNovaController( $userCredentials );
		$securitygroup = $this->adminNova->getSecurityGroup( $formData['groupname'], $project );
		if ( ! $securitygroup ) {
			$wgOut->addWikiMsg( 'openstackmanager-nonexistantsecuritygroup' );
			return false;
		}
		$groupname = $securitygroup->getGroupName();
		$success = $this->userNova->addSecurityGroupRule( $groupname, $fromport, $toport, $protocol, $ranges, $groups );
		if ( $success ) {
			# TODO: Ensure group isn't being used
			$wgOut->addWikiMsg( 'openstackmanager-addedrule' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-addrulefailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

		$wgOut->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryRemoveRuleSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$project = $formData['project'];
		$fromport = $formData['fromport'];
		$toport = $formData['toport'];
		$protocol = $formData['protocol'];
		if ( isset( $formData['ranges'] ) ) {
			$ranges = explode( ',', $formData['ranges'] );
		} else {
			$ranges = array();
		}
		$groups = array();
		if ( isset( $formData['groups'] ) ) {
			$rawgroups = explode( ',', $formData['groups'] );
			foreach ( $rawgroups as $rawgroup ) {
				$rawgroup = explode( ':', $rawgroup );
				$groups[] = array( 'groupname' => $rawgroup[0], 'project' => $rawgroup[1] );
			}
		}
		$userCredentials = $this->userLDAP->getCredentials( $project );
		$this->userNova = new OpenStackNovaController( $userCredentials );
		$securitygroup = $this->adminNova->getSecurityGroup( $formData['groupname'], $project );
		if ( ! $securitygroup ) {
			$wgOut->addWikiMsg( 'openstackmanager-nonexistantsecuritygroup' );
			return false;
		}
		$groupname = $securitygroup->getGroupName();
		$success = $this->userNova->removeSecurityGroupRule( $groupname, $fromport, $toport, $protocol, $ranges, $groups );
		if ( $success ) {
			# TODO: Ensure group isn't being used
			$wgOut->addWikiMsg( 'openstackmanager-removedrule' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-removerulefailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

		$wgOut->addHTML( $out );
		return true;
	}
}

class SpecialNovaSecurityGroupForm extends HTMLForm {
}

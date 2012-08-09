<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaSecurityGroup extends SpecialNova {

	/**
	 * @var OpenStackNovaController
	 */
	var $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaSecurityGroup', 'listall' );
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
		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$this->userNova->setProject( $project );
		$this->userNova->setRegion( $region );

		$action = $this->getRequest()->getVal( 'action' );

		if ( $action === "create" ) {
			$this->createSecurityGroup();
		} elseif ( $action === "delete" ) {
			$this->deleteSecurityGroup();
		} elseif ( $action === "addrule" ) {
			$this->addRule();
		} elseif ( $action === "removerule" ) {
			$this->removeRule();
		} else {
			$this->listSecurityGroups();
		}
	}

	/**
	 * @return bool
	 */
	function createSecurityGroup() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-createsecuritygroup' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
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
		$securityGroupInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$securityGroupForm = new HTMLForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
		$securityGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaSecurityGroup' ) );
		$securityGroupForm->setSubmitID( 'openstackmanager-novainstance-createsecuritygroupsubmit' );
		$securityGroupForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$securityGroupForm->show();

		return true;

	}

	/**
	 * @return bool
	 */
	function deleteSecurityGroup() {


		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-deletesecuritygroup' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$securitygroupid = $this->getRequest()->getText( 'groupid' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$securitygroup = $this->userNova->getSecurityGroup( $securitygroupid );
			if ( $securitygroup ) {
				$securitygroupname = $securitygroup->getGroupName();
				$this->getOutput()->addWikiMsg( 'openstackmanager-deletesecuritygroup-confirm', $securitygroupname );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistantsecuritygroup' );
				return false;
			}
		}
		$securityGroupInfo = array();
		$securityGroupInfo['groupid'] = array(
			'type' => 'hidden',
			'default' => $securitygroupid,
			'name' => 'groupname',
		);
		$securityGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$securityGroupInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$securityGroupForm = new HTMLForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
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
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.openstack' );
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-securitygrouplist' ) );

		if ( $this->userCanExecute( $this->getUser() ) ) {
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
				$this->userNova->setRegion( $region );
				$regionactions = Array( 'netadmin' => Array( $this->createActionLink( 'openstackmanager-createnewsecuritygroup', array( 'action' => 'create', 'project' => $projectName, 'region' => $region ) ) ) );
				$securityGroups = $this->getSecurityGroups( $projectName, $region );
				$regions .= $this->createRegionSection( $region, $projectName, $regionactions, $securityGroups );
			}
			$out .= $this->createProjectSection( $projectName, $projectactions, $regions );
		}

		$this->getOutput()->addHTML( $out );
		return true;
	}

	function getSecurityGroups( $projectName, $region ) {
		$groupHeaders = Array( 'openstackmanager-securitygroupname', 'openstackmanager-securitygroupdescription',
			'openstackmanager-securitygrouprule', 'openstackmanager-actions' );
		$ruleHeaders = Array( 'openstackmanager-securitygrouprule-fromport', 'openstackmanager-securitygrouprule-toport',
			'openstackmanager-securitygrouprule-protocol', 'openstackmanager-securitygrouprule-ipranges',
			'openstackmanager-securitygrouprule-groups', 'openstackmanager-actions' );
		$securityGroups = $this->userNova->getSecurityGroups();
		$groupRows = Array();
		foreach ( $securityGroups as $group ) {
			$groupRow = Array();
			$project = $group->getProject();
			$groupname = $group->getGroupName();
			$groupid = $group->getGroupId();
			$this->pushResourceColumn( $groupRow, $groupname );
			$this->pushResourceColumn( $groupRow, $group->getGroupDescription() );
			# Add rules
			$rules = $group->getRules();
			if ( $rules ) {
				$ruleRows = Array();
				foreach ( $rules as $rule ) {
					$ruleRow = Array();
					$fromport = $rule->getFromPort();
					$toport = $rule->getToPort();
					$ipprotocol = $rule->getIPProtocol();
					$ruleid = $rule->getId();
					$this->pushResourceColumn( $ruleRow, $fromport );
					$this->pushResourceColumn( $ruleRow, $toport );
					$this->pushResourceColumn( $ruleRow, $ipprotocol );
					$range = $rule->getIPRange();
					if ( $range ) {
						$this->pushResourceColumn( $ruleRow, $range );
					} else {
						$this->pushResourceColumn( $ruleRow, '' );
					}
					$sourcegroup = $rule->getGroup();
					$groupinfo = array();
					if ( $sourcegroup ) {
						$groupinfo = $sourcegroup['groupname'];
						$sourcegroupinfo = $sourcegroup['groupname'] . ' (' . $sourcegroup['project'] . ')';
						$this->pushResourceColumn( $ruleRow, $sourcegroupinfo );
					} else {
						$this->pushRawResourceColumn( $ruleRow, '' );
					}
					$actions = '';
					if ( $this->userLDAP->inRole( 'netadmin', $project ) ) {
						$args = array(  'action' => 'removerule',
								'project' => $project,
								'region' => $region,
								'groupid' => $groupid,
								'ruleid' => $ruleid );
						$link = $this->createActionLink( 'openstackmanager-removerule-action', $args );
						$actions = $this->createResourceList( array( $link ) );
					}
					$this->pushRawResourceColumn( $ruleRow, $actions );
					array_push( $ruleRows, $ruleRow );
				}
				$this->pushRawResourceColumn( $groupRow, $this->createResourceTable( $ruleHeaders, $ruleRows ) );
			} else {
				$this->pushRawResourceColumn( $groupRow, '' );
			}
			$actions = Array();
			if ( $this->userLDAP->inRole( 'netadmin', $project ) ) {
				array_push( $actions, $this->createActionLink( 'openstackmanager-delete', array( 'action' => 'delete', 'project' => $project, 'region' => $region, 'groupid' => $groupid ) ) );
				array_push( $actions, $this->createActionLink( 'openstackmanager-addrule-action', array( 'action' => 'addrule', 'project' => $project, 'region' => $region, 'groupid' => $groupid ) ) );
			}
			$this->pushRawResourceColumn( $groupRow, $this->createResourceList( $actions ) );
			array_push( $groupRows, $groupRow );
		}
		if ( $groupRows ) {
			return $this->createResourceTable( $groupHeaders, $groupRows );
		} else {
			return '';
		}
	}

	/**
	 * @return bool
	 */
	function addRule() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-addrule' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		$groupid = $this->getRequest()->getText( 'groupid' );
		$group = $this->getRequest()->getText( 'group' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$securitygroup = $this->userNova->getSecurityGroup( $groupid );
		if ( ! $securitygroup ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistantsecuritygroup' );
			return false;
		}

		$group_keys = array( '' => '' );
		$securityGroups = $this->userNova->getSecurityGroups();
		foreach ( $securityGroups as $securityGroup ) {
			$securityGroupName = $securityGroup->getGroupName();
			if ( $securityGroupName === $group ) {
				$sourcegroupid = $securityGroup->getGroupId();
			}
			$group_keys[$securityGroupName] = $securityGroupName;
		}
		$securityGroupInfo = array();
		$securityGroupInfo['groupid'] = array(
			'type' => 'hidden',
			'default' => $groupid,
			'name' => 'groupid',
		);
		$securityGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'section' => 'rule/singlerule',
			'name' => 'project',
		);
		$securityGroupInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$securityGroupInfo['fromport'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygrouprule-fromport',
			'default' => '',
			'section' => 'rule/singlerule',
			'name' => 'fromport',
		);
		$securityGroupInfo['toport'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygrouprule-toport',
			'default' => '',
			'section' => 'rule/singlerule',
			'name' => 'toport',
		);
		$securityGroupInfo['protocol'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-securitygrouprule-protocol',
			'options' => array( '' => '', 'icmp' => 'icmp', 'tcp' => 'tcp', 'udp' => 'udp' ),
			'section' => 'rule/singlerule',
			'name' => 'protocol',
		);
		$securityGroupInfo['range'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-securitygrouprule-ranges',
			'help-message' => 'openstackmanager-securitygrouprule-ranges-help',
			'default' => '',
			'section' => 'rule/singlerule',
			'name' => 'range',
		);
		$securityGroupInfo['group'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-securitygrouprule-groups',
			'help-message' => 'openstackmanager-securitygrouprule-groups-help',
			'options' => $group_keys,
			'section' => 'rule/group',
			'name' => 'group',
		);
		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addrule',
			'name' => 'action',
		);
		$securityGroupForm = new HTMLForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
		$securityGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaSecurityGroup' ) );
		$securityGroupForm->addHeaderText( wfMsg( 'openstackmanager-securitygrouprule-group-exclusive' ), 'rule' );
		$securityGroupForm->setSubmitID( 'novainstance-form-removerulesubmit' );
		$securityGroupForm->setSubmitCallback( array( $this, 'tryAddRuleSubmit' ) );
		$securityGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function removeRule() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-removerule' ) );

		$project = $this->getRequest()->getText( 'project' );
		$region = $this->getRequest()->getText( 'region' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
		$groupid = $this->getRequest()->getText( 'groupid' );
		$ruleid = $this->getRequest()->getText( 'ruleid' );
		#TODO: fetch group name
		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removerule-confirm', $groupid );
		}
		$securityGroupInfo = array();
		$securityGroupInfo['groupid'] = array(
			'type' => 'hidden',
			'default' => $groupid,
			'name' => 'groupid',
		);
		$securityGroupInfo['ruleid'] = array(
			'type' => 'hidden',
			'default' => $ruleid,
			'name' => 'ruleid',
		);
		$securityGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$securityGroupInfo['region'] = array(
			'type' => 'hidden',
			'default' => $region,
			'name' => 'region',
		);
		$securityGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'removerule',
			'name' => 'action',
		);
		$securityGroupForm = new HTMLForm( $securityGroupInfo, 'openstackmanager-novasecuritygroup' );
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
		$project = $formData['project'];
		$groupname = $formData['groupname'];
		$description = $formData['description'];
		$userCredentials = $this->userLDAP->getCredentials();
		$securitygroup = $this->userNova->createSecurityGroup( $groupname, $description );
		if ( $securitygroup ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createdsecuritygroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createsecuritygroupfailed' );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		$groupid = $formData['groupid'];
		$success = $this->userNova->deleteSecurityGroup( $groupid );
		if ( $success ) {
			# TODO: Ensure group isn't being used
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedsecuritygroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletesecuritygroupfailed' );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddRuleSubmit( $formData, $entryPoint = 'internal' ) {
		$project = $formData['project'];
		$fromport = $formData['fromport'];
		$toport = $formData['toport'];
		$protocol = $formData['protocol'];
		$range = $formData['range'];
		$group = $formData['group'];
		$groupid = $formData['groupid'];
		$sourcegroupid = '';
		if ( $group ) {
			$securityGroups = $this->userNova->getSecurityGroups();
			foreach ( $securityGroups as $securityGroup ) {
				if ( $group === $securityGroup->getGroupName() ) {
					$sourcegroupid = $securityGroup->getGroupId();
				}
			}
		}
		$success = $this->userNova->addSecurityGroupRule( $groupid, $fromport, $toport, $protocol, $range, $sourcegroupid );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-addedrule' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-addrulefailed' );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

		$this->getOutput()->addHTML( $out );
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryRemoveRuleSubmit( $formData, $entryPoint = 'internal' ) {
		$ruleid = $formData['ruleid'];
		$success = $this->userNova->removeSecurityGroupRule( $ruleid );
		if ( $success ) {
			# TODO: Ensure group isn't being used
			$this->getOutput()->addWikiMsg( 'openstackmanager-removedrule' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removerulefailed' );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsecuritygrouplist' ) );

		$this->getOutput()->addHTML( $out );
		return true;
	}
}

<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaPuppetGroup extends SpecialNova {
	/**
	 * @var OpenStackNovaUser
	 */
	protected $userLDAP;

	function __construct() {
		parent::__construct( 'NovaPuppetGroup', 'manageglobalpuppet' );
	}

	function execute( $par ) {
		if ( ! $this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();
		if ( ! $this->userLDAP->exists() ) {
			$this->noCredentials();
			return;
		}
		$this->checkTwoFactor();
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "create" ) {
			$this->createPuppetGroup();
		} elseif ( $action === "delete" ) {
			$this->deletePuppetGroup();
		} elseif ( $action === "addvar" ) {
			$this->addPuppetVar();
		} elseif ( $action === "deletevar" ) {
			$this->deletePuppetVar();
		} elseif ( $action === "addclass" ) {
			$this->addPuppetClass();
		} elseif ( $action === "deleteclass" ) {
			$this->deletePuppetClass();
		} elseif ( $action === "modifyclass" ) {
			$this->modifyPuppetClass();
		} elseif ( $action === "modifyvar" ) {
			$this->modifyPuppetVar();
		//} elseif ( $action === "modify" ) {
		//	$this->modifyPuppetGroup();
		} else {
			$this->listPuppetGroups();
		}
	}

	/**
	 * @return bool
	 */
	function createPuppetGroup() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-createpuppetgroup' ) );
		$project = $this->getRequest()->getText( 'project' );
		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}

		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetgroupname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-puppetgroupname',
			'validation-callback' => array( $this, 'validateText' ),
			'default' => '',
			'name' => 'puppetgroupname',
		);
		$puppetGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetgroup-form-createpuppetgroupsubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function addPuppetClass() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-addpuppetclass' ) );
		$puppetGroupId = $this->getRequest()->getInt( 'puppetgroupid' );
		$group = OpenStackNovaPuppetGroup::newFromId( $puppetGroupId );
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}
		$project = $group->getProject();
		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}

		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetclassname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-puppetclassname',
			'default' => '',
			'name' => 'puppetclassname',
		);
		$puppetGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addclass',
			'name' => 'action',
		);
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'hidden',
			'default' => $puppetGroupId,
			'name' => 'puppetgroupid',
		);

		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetgroup-form-addclasssubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryAddClassSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deletePuppetClass() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-removepuppetclass' ) );
		$puppetClassId = $this->getRequest()->getInt( 'puppetclassid' );
		$group = OpenStackNovaPuppetGroup::newFromClassId( $puppetClassId );
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}
		$project = $group->getProject();
		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}
		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removepuppetclassconfirm' );
		}
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetclassid'] = array(
			'type' => 'hidden',
			'default' => $puppetClassId,
			'name' => 'puppetclassid',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'deleteclass',
			'name' => 'action',
		);

		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetgroup-form-deletepuppetclasssubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryDeleteClassSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function addPuppetVar() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-addpuppetvar' ) );
		$puppetGroupId = $this->getRequest()->getInt( 'puppetgroupid' );
		$group = OpenStackNovaPuppetGroup::newFromId( $puppetGroupId );
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}
		$project = $group->getProject();
		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}

		$puppetGroupId = $this->getRequest()->getInt( 'puppetgroupid' );
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetvarname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-puppetvarname',
			'default' => '',
			'name' => 'puppetvarname',
		);
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'hidden',
			'default' => $puppetGroupId,
			'name' => 'puppetgroupid',
		);
		$puppetGroupInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addvar',
			'name' => 'action',
		);

		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetGroup-form-addvarsubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryAddVarSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deletePuppetVar() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-removepuppetvar' ) );
		$puppetVarId = $this->getRequest()->getText( 'puppetvarid' );
		$group = OpenStackNovaPuppetGroup::newFromVarId( $puppetVarId );
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}
		$project = $group->getProject();
		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}

		$puppetVarId = $this->getRequest()->getText( 'puppetvarid' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removepuppetvarconfirm' );
		}
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetvarid'] = array(
			'type' => 'hidden',
			'default' => $puppetVarId,
			'name' => 'puppetvarid',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'deletevar',
			'name' => 'action',
		);

		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetgroup-form-deletepuppetvarsubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryDeleteVarSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deletePuppetGroup() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-deletepuppetgroup' ) );
		$puppetGroupId = $this->getRequest()->getInt( 'puppetgroupid' );
		$group = OpenStackNovaPuppetGroup::newFromId( $puppetGroupId );
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}
		$project = $group->getProject();
		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}

		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removepuppetgroupconfirm' );
		}
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'hidden',
			'default' => $puppetGroupId,
			'name' => 'puppetgroupid',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetGroup-form-deletepuppetgroupsubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function modifyPuppetClass() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-modifypuppetclass' ) );
		$puppetClassId = $this->getRequest()->getInt( 'puppetclassid' );
		$group = OpenStackNovaPuppetGroup::newFromClassId( $puppetClassId );
		$puppetGroupId = $this->getRequest()->getInt( 'puppetgroupid' );
		// Check to ensure a user is a sysadmin in both the from and to
		// groups.
		if ( $puppetGroupId !== $group->getId() ) {
			$newgroup = OpenStackNovaPuppetGroup::newFromId( $puppetGroupId );
			if ( !$newgroup ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
				return false;
			}
			$project = $newgroup->getProject();
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		}
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}
		$project = $group->getProject();
		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}

		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetclassid'] = array(
			'type' => 'hidden',
			'default' => $puppetClassId,
			'name' => 'puppetclassid',
		);
		$groups = OpenStackNovaPuppetGroup::getGroupList( $project );
		$groupKeys = array();
		foreach ( $groups as $group ) {
			$groupname = htmlentities( $group->getName() );
			$groupKeys[$groupname] = $group->getId();
		}
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-puppetgroup',
			'options' => $groupKeys,
			'default' => $puppetGroupId,
			'name' => 'puppetgroupid',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'modifyclass',
			'name' => 'action',
		);

		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetgroup-form-modifypuppetclasssubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryModifyClassSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function modifyPuppetVar() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-modifypuppetvar' ) );
		$puppetVarId = $this->getRequest()->getInt( 'puppetvarid' );
		$group = OpenStackNovaPuppetGroup::newFromVarId( $puppetVarId );
		$puppetGroupId = $this->getRequest()->getInt( 'puppetgroupid' );
		// Check to ensure a user is a sysadmin in both the from and to
		// groups.
		if ( $puppetGroupId !== $group->getId() ) {
			$newgroup = OpenStackNovaPuppetGroup::newFromId( $puppetGroupId );
			if ( !$newgroup ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
				return false;
			}
			$project = $newgroup->getProject();
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		}
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}
		$project = $group->getProject();

		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}

		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetvarid'] = array(
			'type' => 'hidden',
			'default' => $puppetVarId,
			'name' => 'puppetvarid',
		);
		$groups = OpenStackNovaPuppetGroup::getGroupList( $project );
		$groupKeys = array();
		foreach ( $groups as $group ) {
			$groupname = htmlentities( $group->getName() );
			$groupKeys[$groupname] = $group->getId();
		}
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-puppetgroup',
			'options' => $groupKeys,
			'default' => $puppetGroupId,
			'name' => 'puppetgroupid',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'modifyvar',
			'name' => 'action',
		);

		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetgroup-form-modifypuppetvarsubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryModifyVarSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function modifyPuppetGroup() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-modifypuppetgroup' ) );
		$puppetGroupId = $this->getRequest()->getInt( 'puppetgroupid' );
		$group = OpenStackNovaPuppetGroup::newFromId( $puppetGroupId );
		if ( !$group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentresource' );
			return false;
		}
		$puppetGroupName = $group->getName();
		$project = $group->getProject();
		if ( $project ) {
			// Project specific
			if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
				$this->notInRole( 'sysadmin' );
				return false;
			}
		} else {
			// Global project - requires manageglobalpuppet
			if ( !$this->userCanExecute( $this->getUser() ) ) {
				$this->displayRestrictionError();
				return false;
			}
		}

		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'hidden',
			'default' => $puppetGroupId,
			'name' => 'puppetgroupid',
		);
		$puppetGroupInfo['puppetgroupname'] = array(
			'type' => 'hidden',
			'default' => $puppetGroupName,
			'name' => 'puppetgroupname',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'modify',
			'name' => 'action',
		);

		$puppetGroupForm = new HTMLForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
		$puppetGroupForm->setTitle( SpecialPage::getTitleFor( 'NovaPuppetGroup' ) );
		$puppetGroupForm->setSubmitID( 'novapuppetgroup-form-modifypuppetgroupsubmit' );
		$puppetGroupForm->setSubmitCallback( array( $this, 'tryModifyGroupSubmit' ) );
		$puppetGroupForm->show();

		return true;
	}

	/**
	 * @return void
	 */
	function listPuppetGroups() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-puppetgrouplist' ) );
		$this->getOutput()->addModuleStyles( 'ext.openstack' );

		if ( $this->getUser()->isAllowed( 'listall' ) ) {
			$projects = OpenStackNovaProject::getAllProjects();
		} else {
			$projects =  OpenStackNovaProject::getProjectsByName( $this->userLDAP->getProjects() );
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
			if ( $projectfilter && !in_array( $projectName, $projectfilter ) ) {
				continue;
			}
			if ( !$this->userLDAP->inRole( 'sysadmin', $projectName ) ) {
				continue;
			}
			$actions = Array( 'sysadmin' => Array() );
			$actions['sysadmin'][] = $this->createActionLink( 'openstackmanager-createpuppetgroup', array( 'action' => 'create', 'project' => $projectName ) );
			$out .= $this->createProjectSection( $projectName, $actions, $this->getPuppetGroupOutput( OpenStackNovaPuppetGroup::getGroupList( $projectName ) ) );
		}
		$action = '';
		$showlinks = $this->userCanExecute( $this->getUser() );
		if ( $showlinks ) {

			$action = $this->createActionLink( 'openstackmanager-createpuppetgroup', array( 'action' => 'create' ) );
			$action = Html::rawElement( 'span', array( 'id' => 'novaaction' ), "[$action]" );
		}
		$allProjectsMsg = Html::rawElement( 'span',
			array( 'class' => 'mw-customtoggle-allprojects', 'id' => 'novaproject' ),
			$this->msg( 'openstackmanager-puppetallprojects' )->escaped()
		);
		$out .= Html::rawElement( 'h2', array(), "$allProjectsMsg $action" );
		$groupsOut = $this->getPuppetGroupOutput( OpenStackNovaPuppetGroup::getGroupList(), $showlinks );
		$out .= Html::rawElement( 'div',
			array( 'class' => 'mw-collapsible', 'id' => 'mw-customcollapsible-allprojects' ),
			$groupsOut
		);
		$this->getOutput()->addHTML( $out );
	}

	function getPuppetGroupOutput( $puppetGroups, $showlinks=true ) {
		$out = '';
		foreach ( $puppetGroups as $puppetGroup ) {
			$puppetGroupProject = $puppetGroup->getProject();
			# $puppetGroupProject can be null
			if ( !$puppetGroupProject ) {
				$puppetGroupProject = '';
			}
			$puppetGroupId = $puppetGroup->getId();
			$puppetGroupName = $puppetGroup->getName();
			$puppetGroupName = htmlentities( $puppetGroupName );
			$specialPuppetGroupTitle = Title::newFromText( 'Special:NovaPuppetGroup' );
			if ( $showlinks ) {
				//$modify = $this->createActionLink( 'openstackmanager-modify', array( 'action' => 'modify', 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
				$delete = $this->createActionLink(
					'openstackmanager-delete',
					array(
						'action' => 'delete',
						'puppetgroupid' => $puppetGroupId,
						'returnto' => 'Special:NovaPuppetGroup' )
				);
				//$action = Html::rawElement( 'span', array( 'id' => 'novaaction' ), "[$modify, $delete]" ); 
				$action = Html::rawElement( 'span', array( 'id' => 'novaaction' ), "[$delete]" ); 
				$out .= Html::rawElement( 'h3', array(), "$puppetGroupName $action" );
			}
			$action = '';
			if ( $showlinks ) {
				$action = $this->createActionLink(
					'openstackmanager-addpuppetclass',
					array(
						'action' => 'addclass',
						'puppetgroupid' => $puppetGroupId,
						'project' => $puppetGroupProject,
						'returnto' => 'Special:NovaPuppetGroup'
					)
				);
				$action = Html::rawElement( 'span', array( 'id' => 'novaaction' ), "[$action]" );
			}
			$classesMsg = $this->msg( 'openstackmanager-puppetclasses' )->escaped();
			$out .= Html::rawElement( 'h4', array(), "$classesMsg $action" );
			$puppetGroupClasses = $puppetGroup->getClasses();
			$puppetGroupVars = $puppetGroup->getVars();
			if ( $puppetGroupClasses ) {
				$classes = Array();
				foreach ( $puppetGroupClasses as $puppetGroupClass ) {
					$classname = htmlentities( $puppetGroupClass["name"] );
					if ( $showlinks ) {
						$modify = $this->createActionLink(
							'openstackmanager-modify',
							array(
								'action' => 'modifyclass',
								'puppetclassid' => $puppetGroupClass["id"],
								'puppetgroupid' => $puppetGroupId,
								'returnto' => 'Special:NovaPuppetGroup'
							)
						);
						$delete = $this->createActionLink(
							'openstackmanager-delete',
							array(
							'action' => 'deleteclass',
								'puppetclassid' => $puppetGroupClass["id"],
								'returnto' => 'Special:NovaPuppetGroup'
							)
						);
						$classname  .= Html::rawElement( 'span', array( 'id' => 'novaaction' ), " [$modify, $delete]" ); 
					}

					array_push( $classes, $classname );
				}
				$out .= $this->createResourceList( $classes );
			}
			$action = '';
			if ( $showlinks ) {
				$action = Linker::link(
					$specialPuppetGroupTitle,
					$this->msg( 'openstackmanager-addpuppetvar' )->escaped(),
					array(),
					array(
						'action' => 'addvar',
						'puppetgroupid' => $puppetGroupId,
						'project' => $puppetGroupProject,
						'returnto' => 'Special:NovaPuppetGroup'
					)
				);
				$action = Html::rawElement( 'span', array( 'id' => 'novaaction' ), "[$action]" );
			}
			$varsMsg = $this->msg( 'openstackmanager-puppetvars' )->escaped();
			$out .= Html::rawElement( 'h4', array(), "$varsMsg $action" );
			if ( $puppetGroupVars ) {
				$vars = Array();
				foreach ( $puppetGroupVars as $puppetGroupVar ) {
					$varname = htmlentities( $puppetGroupVar["name"] );
					if ( $showlinks ) {
						$modify = $this->createActionLink(
							'openstackmanager-modify',
							array(
								'action' => 'modifyvar',
								'puppetvarid' => $puppetGroupVar["id"],
								'puppetgroupid' => $puppetGroupId,
								'returnto' => 'Special:NovaPuppetGroup'
							)
						);
						$delete = $this->createActionLink(
							'openstackmanager-delete',
							array(
								'action' => 'deletevar',
								'puppetvarid' => $puppetGroupVar["id"],
								'returnto' => 'Special:NovaPuppetGroup'
							)
						);
						// @todo FIXME: Hard coded brackets.
						$varname  .= Html::rawElement( 'span',
							array( 'id' => 'novaaction' ),
							" [$modify, $delete]"
						);
					}
					array_push( $vars, $varname );
				}
				$out .= $this->createResourceList( $vars );
			}
		}
		return $out;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::addGroup( $formData['puppetgroupname'], $formData['project'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createdpuppetgroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createpuppetgroupfailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
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
		$success = OpenStackNovaPuppetGroup::deleteGroup( $formData['puppetgroupid'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedpuppetgroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletepuppetgroupfailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddClassSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::addClass( $formData['puppetclassname'], $formData['puppetgroupid'], $formData['project'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-addedpuppetclass' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoaddpuppetclass' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteClassSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::deleteClass( $formData['puppetclassid'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedpuppetclass' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtodeletepuppetclass' );
		}
		$out = '<br />';

		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddVarSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::addVar( $formData['puppetvarname'], $formData['puppetgroupid'], $formData['project'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-addedpuppetvar' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoaddpuppetvar' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteVarSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::deleteVar( $formData['puppetvarid'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedpuppetvar' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtodeletepuppetvar' );
		}
		$out = '<br />';

		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyClassSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::updateClass( $formData['puppetclassid'], $formData['puppetgroupid'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedpuppetclass' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtomodifypuppetclass' );
		}
		$out = '<br />';

		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyVarSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::updateVar( $formData['puppetvarid'], $formData['puppetgroupid'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedpuppetvar' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtomodifypuppetvar' );
		}
		$out = '<br />';

		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyGroupSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::updateGroupName( $formData['puppetgroupid'], $formData['puppetgroupname'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedpuppetgroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtomodifypuppetgroup' );
		}
		$out = '<br />';

		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backpuppetgrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}
}

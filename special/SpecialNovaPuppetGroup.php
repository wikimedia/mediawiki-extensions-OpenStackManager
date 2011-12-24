<?php
class SpecialNovaPuppetGroup extends SpecialNova {

	function __construct() {
		parent::__construct( 'NovaPuppetGroup', 'manageproject' );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;

		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$action = $wgRequest->getVal( 'action' );
		if ( $action == "create" ) {
			$this->createPuppetGroup();
		} elseif ( $action == "delete" ) {
			$this->deletePuppetGroup();
		} elseif ( $action == "addvar" ) {
			$this->addPuppetVar();
		} elseif ( $action == "deletevar" ) {
			$this->deletePuppetVar();
		} elseif ( $action == "addclass" ) {
			$this->addPuppetClass();
		} elseif ( $action == "deleteclass" ) {
			$this->deletePuppetClass();
		} elseif ( $action == "modifyclass" ) {
			$this->modifyPuppetClass();
		} elseif ( $action == "modifyvar" ) {
			$this->modifyPuppetVar();
		} elseif ( $action == "modify" ) {
			$this->modifyPuppetGroup();
		} else {
			$this->listPuppetGroups();
		}
	}

	/**
	 * @return bool
	 */
	function createPuppetGroup() {
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-createpuppetgroup' ) );

		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetgroupname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-puppetgroupname',
			'validation-callback' => array( $this, 'validateText' ),
			'default' => '',
			'name' => 'puppetgroupname',
		);
		$puppetGroupInfo['puppetgroupposition'] = array(
			'type' => 'int',
			'label-message' => 'openstackmanager-puppetgroupposition',
			'default' => '',
			'name' => 'puppetgroupposition',
		);

		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		global $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-addpuppetclass' ) );

		$puppetGroupId = $wgRequest->getInt( 'puppetgroupid' );
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetclassname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-puppetclassname',
			'default' => '',
			'name' => 'puppetclassname',
		);
		$puppetGroupInfo['puppetclassposition'] = array(
			'type' => 'int',
			'label-message' => 'openstackmanager-puppetclassposition',
			'name' => 'puppetclassposition',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addclass',
			'name' => 'action',
		);
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'hidden',
			'default' => $puppetGroupId,
			'name' => 'puppetGroupId',
		);

		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		global $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-removepuppetclass' ) );
		if ( ! $wgRequest->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removepuppetclassconfirm' );
		}
		$puppetClassId = $wgRequest->getInt( 'puppetclassid' );
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

		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		global $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-addpuppetvar' ) );

		$puppetGroupId = $wgRequest->getInt( 'puppetgroupid' );
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetvarname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-puppetvarname',
			'default' => '',
			'name' => 'puppetvarname',
		);
		$puppetGroupInfo['puppetvarposition'] = array(
			'type' => 'int',
			'label-message' => 'openstackmanager-puppetvarposition',
			'name' => 'puppetvarposition',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addvar',
			'name' => 'action',
		);
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'hidden',
			'default' => $puppetGroupId,
			'name' => 'puppetGroupId',
		);

		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		global $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-removepuppetvar' ) );

		$puppetVarId = $wgRequest->getText( 'puppetvarid' );
		if ( ! $wgRequest->wasPosted() ) {
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

		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		global $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-deletepuppetgroup' ) );

		$puppetGroupId = $wgRequest->getInt( 'puppetgroupid' );
		if ( ! $wgRequest->wasPosted() ) {
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
		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		global $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-modifypuppetclass' ) );

		$puppetGroupId = $wgRequest->getInt( 'puppetgroupid' );
		$puppetClassId = $wgRequest->getInt( 'puppetclassid' );
		$puppetClassPosition = $wgRequest->getInt( 'puppetclassposition' );
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetclassid'] = array(
			'type' => 'hidden',
			'default' => $puppetClassId,
			'name' => 'puppetclassid',
		);
		$puppetGroupInfo['puppetclassposition'] = array(
			'type' => 'int',
			'label-message' => 'openstackmanager-puppetclassposition',
			'default' => $puppetClassPosition,
			'name' => 'puppetclassposition',
		);
		$groups = OpenStackNovaPuppetGroup::getGroupList();
		$groupKeys = array();
		foreach ( $groups as $group ) {
			$groupname = htmlentities( $group->getName() );
			$groupKeys["$groupname"] = $group->getId();
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

		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		global $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-modifypuppetvar' ) );

		$puppetGroupId = $wgRequest->getInt( 'puppetgroupid' );
		$puppetVarId = $wgRequest->getInt( 'puppetvarid' );
		$puppetVarPosition = $wgRequest->getInt( 'puppetvarposition' );
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetvarid'] = array(
			'type' => 'hidden',
			'default' => $puppetVarId,
			'name' => 'puppetvarid',
		);
		$puppetGroupInfo['puppetvarposition'] = array(
			'type' => 'int',
			'label-message' => 'openstackmanager-puppetvarposition',
			'default' => $puppetVarPosition,
			'name' => 'puppetvarposition',
		);
		$groups = OpenStackNovaPuppetGroup::getGroupList();
		$groupKeys = array();
		foreach ( $groups as $group ) {
			$groupname = htmlentities( $group->getName() );
			$groupKeys["$groupname"] = $group->getId();
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

		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		global $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-modifypuppetgroup' ) );

		$puppetGroupId = $wgRequest->getInt( 'puppetgroupid' );
		$puppetGroupPosition = $wgRequest->getInt( 'puppetgroupposition' );
		$puppetGroupInfo = array();
		$puppetGroupInfo['puppetgroupid'] = array(
			'type' => 'hidden',
			'default' => $puppetGroupId,
			'name' => 'puppetgroupid',
		);
		$puppetGroupInfo['puppetgroupposition'] = array(
			'type' => 'int',
			'label-message' => 'openstackmanager-puppetgroupposition',
			'default' => $puppetGroupPosition,
			'name' => 'puppetgroupposition',
		);
		$puppetGroupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'modify',
			'name' => 'action',
		);

		$puppetGroupForm = new SpecialNovaPuppetGroupForm( $puppetGroupInfo, 'openstackmanager-novapuppetgroup' );
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
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-puppetgrouplist' ) );
		$this->getOutput()->addModuleStyles( 'ext.openstack' );

		$out = '';

		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-createpuppetgroup' ), array(), array( 'action' => 'create' ) );
		$puppetGroups = OpenStackNovaPuppetGroup::getGroupList();
		foreach ( $puppetGroups as $puppetGroup ) {
			$puppetGroupId = $puppetGroup->getId();
			$puppetGroupPosition = $puppetGroup->getPosition();
			$puppetGroupName = $puppetGroup->getName();
			$puppetGroupName = "[$puppetGroupPosition] " . htmlentities( $puppetGroupName );
			$specialPuppetGroupTitle = Title::newFromText( 'Special:NovaPuppetGroup' );
			$modify = Linker::( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-modify' ), array(), array( 'action' => 'modify', 'puppetgroupid' => $puppetGroupId, 'puppetgroupposition' => $puppetGroupPosition, 'returnto' => 'Special:NovaPuppetGroup' ) );
			$delete = Linker::( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-delete' ), array(), array( 'action' => 'delete', 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
			$out .= Html::rawElement( 'h2', array(), "$puppetGroupName ($modify, $delete)" );
			$out .= Html::element( 'h3', array(), wfMsg( 'openstackmanager-puppetclasses' ) );
			$out .= Linker::( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-addpuppetclass' ), array(), array( 'action' => 'addclass', 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
			$puppetGroupClasses = $puppetGroup->getClasses();
			$puppetGroupVars = $puppetGroup->getVars();
			if ( $puppetGroupClasses ) {
				$classesOut = '';
				foreach ( $puppetGroupClasses as $puppetGroupClass ) {
					$modify = Linker::( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-modify' ), array(), array( 'action' => 'modifyclass', 'puppetclassid' => $puppetGroupClass["id"], 'puppetclassposition' => $puppetGroupClass["position"], 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
					$delete = Linker::( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-delete' ), array(), array( 'action' => 'deleteclass', 'puppetclassid' => $puppetGroupClass["id"], 'returnto' => 'Special:NovaPuppetGroup' ) );
					$classname = '[' . $puppetGroupClass["position"] . '] ' . htmlentities( $puppetGroupClass["name"] );
					$classesOut .= Html::rawElement( 'li', array(), "$classname ($modify, $delete)" );
				}
				$out .= Html::rawElement( 'ul', array(), $classesOut );
			}
			$out .= Html::element( 'h3', array(), wfMsg( 'openstackmanager-puppetvars' ) );
			$out .= Linker::( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-addpuppetvar' ), array(), array( 'action' => 'addvar', 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
			if ( $puppetGroupVars ) {
				$varsOut = '';
				foreach ( $puppetGroupVars as $puppetGroupVar ) {
					$modify = Linker::( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-modify' ), array(), array( 'action' => 'modifyvar', 'puppetvarid' => $puppetGroupVar["id"], 'puppetvarposition' => $puppetGroupVar["position"], 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
					$delete = Linker::( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-delete' ), array(), array( 'action' => 'deletevar', 'puppetvarid' => $puppetGroupVar["id"], 'returnto' => 'Special:NovaPuppetGroup' ) );
					$varname = '[' . $puppetGroupVar["position"] . '] ' . htmlentities( $puppetGroupVar["name"] );
					$varsOut .= Html::rawElement( 'li', array(), "$varname ($modify, $delete)" );
				}
				$out .= Html::rawElement( 'ul', array(), $varsOut );
			}
		}

		$this->getOutput()->addHTML( $out );
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::addGroup( $formData['puppetgroupname'], $formData['puppetgroupposition'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createdpuppetgroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createpuppetgroupfailed' );
		}

		$out = '<br />';
		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
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
		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddClassSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::addClass( $formData['puppetclassname'], $formData['puppetclassposition'], $formData['puppetgroupid'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-addedpuppetclass' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoaddpuppetclass' );
		}

		$out = '<br />';
		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
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

		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddVarSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::addVar( $formData['puppetvarname'], $formData['puppetvarposition'], $formData['puppetgroupid'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-addedpuppetvar' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoaddpuppetvar' );
		}

		$out = '<br />';
		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
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

		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyClassSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::updateClass( $formData['puppetclassid'], $formData['puppetgroupid'], $formData['puppetclassposition'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedpuppetclass' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtomodifypuppetclass' );
		}
		$out = '<br />';

		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyVarSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::updateVar( $formData['puppetvarid'], $formData['puppetgroupid'], $formData['puppetvarposition'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedpuppetvar' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtomodifypuppetvar' );
		}
		$out = '<br />';

		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyGroupSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaPuppetGroup::updateGroupPosition( $formData['puppetgroupid'], $formData['puppetgroupposition'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedpuppetgroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtomodifypuppetgroup' );
		}
		$out = '<br />';

		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

}

class SpecialNovaPuppetGroupForm extends HTMLForm {
}

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
		global $wgOut, $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-createpuppetgroup' ) );

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
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addpuppetclass' ) );

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
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-removepuppetclass' ) );
		if ( ! $wgRequest->wasPosted() ) {
			$wgOut->addWikiMsg( 'openstackmanager-removepuppetclassconfirm' );
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
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addpuppetvar' ) );

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
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-removepuppetvar' ) );

		$puppetVarId = $wgRequest->getText( 'puppetvarid' );
		if ( ! $wgRequest->wasPosted() ) {
			$wgOut->addWikiMsg( 'openstackmanager-removepuppetvarconfirm' );
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
		global $wgOut, $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-deletepuppetgroup' ) );

		$puppetGroupId = $wgRequest->getInt( 'puppetgroupid' );
		if ( ! $wgRequest->wasPosted() ) {
			$wgOut->addWikiMsg( 'openstackmanager-removepuppetgroupconfirm' );
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
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-modifypuppetclass' ) );

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
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-modifypuppetvar' ) );

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
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-modifypuppetgroup' ) );

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
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-puppetgrouplist' ) );
		$wgOut->addModuleStyles( 'ext.openstack' );

		$out = '';
		$sk = $wgOut->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-createpuppetgroup' ), array(), array( 'action' => 'create' ) );
		$puppetGroups = OpenStackNovaPuppetGroup::getGroupList();
		foreach ( $puppetGroups as $puppetGroup ) {
			$puppetGroupId = $puppetGroup->getId();
			$puppetGroupPosition = $puppetGroup->getPosition();
			$puppetGroupName = $puppetGroup->getName();
			$puppetGroupName = "[$puppetGroupPosition] " . htmlentities( $puppetGroupName );
			$specialPuppetGroupTitle = Title::newFromText( 'Special:NovaPuppetGroup' );
			$modify = $sk->link( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-modify' ), array(), array( 'action' => 'modify', 'puppetgroupid' => $puppetGroupId, 'puppetgroupposition' => $puppetGroupPosition, 'returnto' => 'Special:NovaPuppetGroup' ) );
			$delete = $sk->link( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-delete' ), array(), array( 'action' => 'delete', 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
			$out .= Html::rawElement( 'h2', array(), "$puppetGroupName ($modify, $delete)" );
			$out .= Html::element( 'h3', array(), wfMsg( 'openstackmanager-puppetclasses' ) );
			$out .= $sk->link( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-addpuppetclass' ), array(), array( 'action' => 'addclass', 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
			$puppetGroupClasses = $puppetGroup->getClasses();
			$puppetGroupVars = $puppetGroup->getVars();
			if ( $puppetGroupClasses ) {
				$classesOut = '';
				foreach ( $puppetGroupClasses as $puppetGroupClass ) {
					$modify = $sk->link( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-modify' ), array(), array( 'action' => 'modifyclass', 'puppetclassid' => $puppetGroupClass["id"], 'puppetclassposition' => $puppetGroupClass["position"], 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
					$delete = $sk->link( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-delete' ), array(), array( 'action' => 'deleteclass', 'puppetclassid' => $puppetGroupClass["id"], 'returnto' => 'Special:NovaPuppetGroup' ) );
					$classname = '[' . $puppetGroupClass["position"] . '] ' . htmlentities( $puppetGroupClass["name"] );
					$classesOut .= Html::rawElement( 'li', array(), "$classname ($modify, $delete)" );
				}
				$out .= Html::rawElement( 'ul', array(), $classesOut );
			}
			$out .= Html::element( 'h3', array(), wfMsg( 'openstackmanager-puppetvars' ) );
			$out .= $sk->link( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-addpuppetvar' ), array(), array( 'action' => 'addvar', 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
			if ( $puppetGroupVars ) {
				$varsOut = '';
				foreach ( $puppetGroupVars as $puppetGroupVar ) {
					$modify = $sk->link( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-modify' ), array(), array( 'action' => 'modifyvar', 'puppetvarid' => $puppetGroupVar["id"], 'puppetvarposition' => $puppetGroupVar["position"], 'puppetgroupid' => $puppetGroupId, 'returnto' => 'Special:NovaPuppetGroup' ) );
					$delete = $sk->link( $specialPuppetGroupTitle, wfMsgHtml( 'openstackmanager-delete' ), array(), array( 'action' => 'deletevar', 'puppetvarid' => $puppetGroupVar["id"], 'returnto' => 'Special:NovaPuppetGroup' ) );
					$varname = '[' . $puppetGroupVar["position"] . '] ' . htmlentities( $puppetGroupVar["name"] );
					$varsOut .= Html::rawElement( 'li', array(), "$varname ($modify, $delete)" );
				}
				$out .= Html::rawElement( 'ul', array(), $varsOut );
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
		global $wgOut;

		$success = OpenStackNovaPuppetGroup::addGroup( $formData['puppetgroupname'], $formData['puppetgroupposition'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-createdpuppetgroup' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-createpuppetgroupfailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
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

		$success = OpenStackNovaPuppetGroup::deleteGroup( $formData['puppetgroupid'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-deletedpuppetgroup' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-deletepuppetgroupfailed' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddClassSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = OpenStackNovaPuppetGroup::addClass( $formData['puppetclassname'], $formData['puppetclassposition'], $formData['puppetgroupid'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-addedpuppetclass' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-failedtoaddpuppetclass' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteClassSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = OpenStackNovaPuppetGroup::deleteClass( $formData['puppetclassid'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-deletedpuppetclass' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-failedtodeletepuppetclass' );
		}
		$out = '<br />';
		$sk = $wgOut->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddVarSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = OpenStackNovaPuppetGroup::addVar( $formData['puppetvarname'], $formData['puppetvarposition'], $formData['puppetgroupid'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-addedpuppetvar' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-failedtoaddpuppetvar' );
		}
		$sk = $wgOut->getSkin();
		$out = '<br />';
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteVarSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = OpenStackNovaPuppetGroup::deleteVar( $formData['puppetvarid'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-deletedpuppetvar' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-failedtodeletepuppetvar' );
		}
		$out = '<br />';
		$sk = $wgOut->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyClassSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = OpenStackNovaPuppetGroup::updateClass( $formData['puppetclassid'], $formData['puppetgroupid'], $formData['puppetclassposition'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-modifiedpuppetclass' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-failedtomodifypuppetclass' );
		}
		$out = '<br />';
		$sk = $wgOut->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyVarSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = OpenStackNovaPuppetGroup::updateVar( $formData['puppetvarid'], $formData['puppetgroupid'], $formData['puppetvarposition'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-modifiedpuppetvar' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-failedtomodifypuppetvar' );
		}
		$out = '<br />';
		$sk = $wgOut->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifyGroupSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = OpenStackNovaPuppetGroup::updateGroupPosition( $formData['puppetgroupid'], $formData['puppetgroupposition'] );
		if ( $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-modifiedpuppetgroup' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-failedtomodifypuppetgroup' );
		}
		$out = '<br />';
		$sk = $wgOut->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backpuppetgrouplist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

}

class SpecialNovaPuppetGroupForm extends HTMLForm {
}

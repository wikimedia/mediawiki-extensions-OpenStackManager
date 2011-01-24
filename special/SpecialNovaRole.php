<?php
class SpecialNovaRole extends SpecialNova {

	var $userNova, $adminNova;

	function __construct() {
		parent::__construct( 'NovaRole', 'manageproject' );

		global $wgOpenStackManagerNovaAdminKeys;

		$this->userLDAP = new OpenStackNovaUser();
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;

		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}

		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return false;
		}

		$action = $wgRequest->getVal( 'action' );
		if ( $action == "addmember" ) {
			$this->addMember();
		} else if ( $action == "deletemember" ) {
			$this->deleteMember();
		} else {
			$this->listGlobalRoles();
		}
	}

	/**
	 * @return bool
	 */
	function addMember() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addmember' ) );

		$roleInfo = array();
		$rolename = $wgRequest->getText( 'rolename' );
		$projectname = $wgRequest->getText( 'projectname' );
		if ( $projectname ) {
			$project = OpenStackNovaProject::getProjectByName( $projectname );
			$projectmembers = $project->getMembers();
			$role = OpenStackNovaRole::getProjectRoleByName( $rolename, $projectname );
			$rolemembers = $role->getMembers();
			$member_keys = array();
			foreach ( $projectmembers as $projectmember ) {
				if ( ! in_array( $projectmember, $rolemembers ) ) {
					$member_keys["$projectmember"] = $projectmember;
				}
			}
			if ( ! $member_keys ) {
				$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-nomemberstoadd' ) );
				$wgOut->addHTML( $out );
				return true;
			}
			$roleInfo['members'] = array(
				'type' => 'multiselect',
				'label-message' => 'openstackmanager-member',
				'options' => $member_keys,
				'section' => 'role/info',
			);
		} else {
			$roleInfo['members'] = array(
				'type' => 'text',
				'label-message' => 'openstackmanager-member',
				'default' => '',
				'section' => 'role/info',
			);
		}
		$roleInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addmember',
		);
		$roleInfo['rolename'] = array(
			'type' => 'hidden',
			'default' => $rolename,
		);
		$roleInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $projectname,
		);
		$roleInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $wgRequest->getText('returnto'),
		);

		$roleForm = new SpecialNovaRoleForm( $roleInfo, 'openstackmanager-novarole' );
		$roleForm->setTitle( SpecialPage::getTitleFor( 'NovaRole' ) );
		$roleForm->setSubmitID( 'novarole-form-addmembersubmit' );
		$roleForm->setSubmitCallback( array( $this, 'tryAddMemberSubmit' ) );
		$roleForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteMember() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-removerolemember' ) );

		$rolename = $wgRequest->getText( 'rolename' );
		$projectname = $wgRequest->getText( 'projectname' );
		if ( $projectname ) {
			$project = OpenStackNovaProject::getProjectByName( $projectname );
			$projectmembers = $project->getMembers();
			$role = OpenStackNovaRole::getProjectRoleByName( $rolename, $projectname );
			$rolemembers = $role->getMembers();
			$member_keys = array();
			foreach ( $projectmembers as $projectmember ) {
				if ( in_array( $projectmember, $rolemembers ) ) {
					$member_keys["$projectmember"] = $projectmember;
				}
			}
		} else {
			$role = OpenStackNovaRole::getGlobalRoleByName( $rolename );
			$rolemembers = $role->getMembers();
			$member_keys = array();
			foreach ( $rolemembers as $rolemember ) {
				$member_keys["$rolemember"] = $rolemember;
			}
		}
		if ( ! $member_keys ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-nomemberstoremove' ) );
			$wgOut->addHTML( $out );
			return true;
		}
		$roleInfo = array();
		$roleInfo['members'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-member',
			'options' => $member_keys,
			'section' => 'role/info',
		);
		$roleInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'deletemember',
		);
		$roleInfo['rolename'] = array(
			'type' => 'hidden',
			'default' => $rolename,
		);
		$roleInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $projectname,
		);
		$roleInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $wgRequest->getText('returnto'),
		);

		$roleForm = new SpecialNovaRoleForm( $roleInfo, 'openstackmanager-novarole' );
		$roleForm->setTitle( SpecialPage::getTitleFor( 'NovaRole' ) );
		$roleForm->setSubmitID( 'novarole-form-deletemembersubmit' );
		$roleForm->setSubmitCallback( array( $this, 'tryDeleteMemberSubmit' ) );
		$roleForm->show();

		return true;
	}

	/**
	 * @return void
	 */
	function listGlobalRoles() {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-rolelist' ) );

		$out = '';
		$sk = $wgUser->getSkin();
		$rolesOut = Html::element( 'th', array(), wfMsg( 'openstackmanager-rolename' ) );
		$rolesOut .= Html::element( 'th', array(),  wfMsg( 'openstackmanager-members' ) );
		$rolesOut .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$roles = OpenStackNovaRole::getAllGlobalRoles();
		if ( ! $roles ) {
			$rolesOut = '';
		}
		foreach ( $roles as $role ) {
			$roleName = $role->getRoleName();
			$roleOut = Html::element( 'td', array(), $roleName );
			$roleMembers = $role->getMembers();
			$memberOut = '';
			foreach ( $roleMembers as $roleMember ) {
				$memberOut .= Html::element( 'li', array(), $roleMember );
			}
			if ( $memberOut ) {
				$memberOut = Html::rawElement( 'ul', array(), $memberOut );
			}
			$roleOut .= Html::rawElement( 'td', array(), $memberOut );
			$link = $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-addrolemember' ), array(), array( 'action' => 'addmember', 'rolename' => $roleName, 'returnto' => 'Special:NovaRole' ), array() );
			$actions = Html::rawElement( 'li', array(), $link );
			$link = $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-removerolemember' ), array(), array( 'action' => 'deletemember', 'rolename' => $roleName, 'returnto' => 'Special:NovaRole' ), array() );
			$actions .= Html::rawElement( 'li', array(), $link );
			$actions = Html::rawElement( 'ul', array(), $actions );
			$roleOut .= Html::rawElement( 'td', array(), $actions );
			$rolesOut .= Html::rawElement( 'tr', array(), $roleOut );
		}
		if ( $rolesOut ) {
			$out .= Html::rawElement( 'table', array( 'class' => 'wikitable' ), $rolesOut );
		}

		$wgOut->addHTML( $out );
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddMemberSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$projectname = $formData['projectname'];
		if ( $projectname ) {
			$project = OpenStackNovaProject::getProjectByName( $projectname );
			if ( ! $project ) {
				$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-nonexistentproject' ) );
				$wgOut->addHTML( $out );
				return true;
			}
			$role = OpenStackNovaRole::getProjectRoleByName( $formData['rolename'], $project );
			$members = $formData['members'];
		} else {
			$role = OpenStackNovaRole::getGlobalRoleByName( $formData['rolename'] );
			$members = array( $formData['members'] );
		}
		if ( ! $role ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-nonexistentrole' ) );
			$wgOut->addHTML( $out );
			return true;
		}
		$out = '';
		foreach ( $members as $member ) {
			$success = $role->addMember( $member );
			if ( $success ) {
				$out .= Html::element( 'p', array(), wfMsgExt( 'openstackmanager-addedto', array(), $member, $formData['rolename'] ) );
			} else {
				$out .= Html::element( 'p', array(), wfMsgExt( 'openstackmanager-failedtoadd', array(), $member, $formData['rolename'] ) );
			}
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$returnto = Title::newFromText( $formData['returnto'] );
		$out .= $sk->link( $returnto, wfMsg( 'openstackmanager-backprojectlist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteMemberSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$projectname = $formData['projectname'];
		if ( $projectname ) {
			$project = OpenStackNovaProject::getProjectByName( $projectname );
			if ( ! $project ) {
				$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-nonexistentproject' ) );
				$wgOut->addHTML( $out );
				return true;
			}
			$role = OpenStackNovaRole::getProjectRoleByName( $formData['rolename'], $project );
		} else {
			$role = OpenStackNovaRole::getGlobalRoleByName( $formData['rolename'] );
		}
		if ( ! $role ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-nonexistentrole' ) );
			$wgOut->addHTML( $out );
			return true;
		}
		$out = '';
		foreach ( $formData['members'] as $member ) {
			$success = $role->deleteMember( $member );
			if ( $success ) {
				$out .= Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removedfrom', array(), $member, $formData['rolename'] ) );
			} else {
				$out .= Html::element( 'p', array(), wfMsgExt( 'openstackmanager-failedtoremove', array(), $member, $formData['rolename'] ) );
			}
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$returnto = Title::newFromText( $formData['returnto'] );
		$out .= $sk->link( $returnto, wfMsg( 'openstackmanager-backprojectlist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}
}

class SpecialNovaRoleForm extends HTMLForm {
}

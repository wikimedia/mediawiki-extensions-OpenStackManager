<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaRole extends SpecialNova {
	public $userLDAP;

	function __construct() {
		parent::__construct( 'NovaRole', 'manageproject' );

		$this->userLDAP = new OpenStackNovaUser();
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->checkTwoFactor();
		$this->userLDAP = new OpenStackNovaUser();
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "addmember" ) {
			$this->addMember();
		} elseif ( $action === "deletemember" ) {
			$this->deleteMember();
		} else {
			$this->displayRestrictionError();
		}
	}

	/**
	 * @return bool
	 */
	function addMember() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-addmember' ) );

		$roleInfo = array();
		$roleid = $this->getRequest()->getText( 'roleid' );
		$projectid = $this->getRequest()->getText( 'projectid' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		if ( $projectid ) {
			if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( $roleid, $projectname ) ) {
				$this->displayRestrictionError();
				return false;
			}
			$project = OpenStackNovaProject::getProjectById( $projectid );
			$projectmembers = $project->getMembers();
			natcasesort( $projectmembers );
			$role = new OpenStackNovaRole( $roleid, $project );
			$rolemembers = $role->getMembers();
			$member_keys = array();
			foreach ( $projectmembers as $projectmember ) {
				if ( ! in_array( $projectmember, $rolemembers ) ) {
					$member_keys[$projectmember] = $projectmember;
				}
			}
			if ( ! $member_keys ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nomemberstoadd' );
				return true;
			}
			$roleInfo['members'] = array(
				'type' => 'multiselect',
				'label-message' => 'openstackmanager-member',
				'options' => $member_keys,
				'section' => 'role/info',
				'name' => 'members',
			);
		} else {
			//TODO: display error
		}
		$roleInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addmember',
			'name' => 'action',
		);
		$roleInfo['roleid'] = array(
			'type' => 'hidden',
			'default' => $roleid,
			'name' => 'roleid',
		);
		$roleInfo['projectid'] = array(
			'type' => 'hidden',
			'default' => $projectid,
			'name' => 'projectid',
		);
		$roleInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $this->getRequest()->getText('returnto'),
			'name' => 'returnto',
		);

		$roleForm = new HTMLForm(
			$roleInfo,
			$this->getContext(),
			'openstackmanager-novarole'
		);
		$roleForm->setSubmitID( 'novarole-form-addmembersubmit' );
		$roleForm->setSubmitCallback( array( $this, 'tryAddMemberSubmit' ) );
		$roleForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteMember() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-removerolemember' ) );

		$roleid = $this->getRequest()->getText( 'roleid' );
		$projectid = $this->getRequest()->getText( 'projectid' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		if ( $projectid ) {
			if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( $roleid, $projectname ) ) {
				$this->displayRestrictionError();
				return false;
			}
			$project = OpenStackNovaProject::getProjectById( $projectid );
			$projectmembers = $project->getMembers();
			natcasesort( $projectmembers );
			$role = new OpenStackNovaRole( $roleid, $project );
			$rolemembers = $role->getMembers();
			$member_keys = array();
			foreach ( $projectmembers as $projectmember ) {
				if ( in_array( $projectmember, $rolemembers ) ) {
					$member_keys[$projectmember] = $projectmember;
				}
			}
		} else {
			//TODO: display error
		}
		if ( ! $member_keys ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nomemberstoremove' );
			return true;
		}
		$roleInfo = array();
		$roleInfo['members'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-member',
			'options' => $member_keys,
			'section' => 'role/info',
			'name' => 'members',
		);
		$roleInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'deletemember',
			'name' => 'action',
		);
		$roleInfo['roleid'] = array(
			'type' => 'hidden',
			'default' => $roleid,
			'name' => 'roleid',
		);
		$roleInfo['projectid'] = array(
			'type' => 'hidden',
			'default' => $projectid,
			'name' => 'projectid',
		);
		$roleInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $this->getRequest()->getText('returnto'),
			'name' => 'returnto',
		);

		$roleForm = new HTMLForm(
			$roleInfo,
			$this->getContext(),
			'openstackmanager-novarole'
		);
		$roleForm->setSubmitID( 'novarole-form-deletemembersubmit' );
		$roleForm->setSubmitCallback( array( $this, 'tryDeleteMemberSubmit' ) );
		$roleForm->show();

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddMemberSubmit( $formData, $entryPoint = 'internal' ) {
		$projectid = $formData['projectid'];
		if ( $projectid ) {
			$project = OpenStackNovaProject::getProjectById( $projectid );
			if ( ! $project ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentproject' );
				return true;
			}
			$role = new OpenStackNovaRole( $formData['roleid'], $project );
			$rolename = $role->getRoleName();
			$members = $formData['members'];
		} else {
			//TODO: display error
		}
		if ( ! $role ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentrole' );
			return true;
		}
		foreach ( $members as $member ) {
			$success = $role->addMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-addedto', $member, $rolename );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoadd', $member, $rolename );
			}
		}
		$project->editArticle();

		$out = '<br />';
		$returnto = Title::newFromText( $formData['returnto'] );
		$out .= Linker::link(
			$returnto,
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteMemberSubmit( $formData, $entryPoint = 'internal' ) {
		$projectid = $formData['projectid'];
		if ( $projectid ) {
			$project = OpenStackNovaProject::getProjectById( $projectid );
			if ( ! $project ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentproject' );
				return true;
			}
			$role = new OpenStackNovaRole( $formData['roleid'], $project );
			$rolename = $role->getRoleName();
		} else {
			//TODO: display error
		}
		if ( ! $role ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentrole' );
			return true;
		}
		foreach ( $formData['members'] as $member ) {
			$success = $role->deleteMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-removedfrom', $member, $rolename );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoremove', $member, $rolename );
			}
		}
		$project->editArticle();

		$out = '<br />';
		$returnto = Title::newFromText( $formData['returnto'] );
		$out .= Linker::link(
			$returnto,
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	protected function getGroupName() {
		return 'nova';
	}
}

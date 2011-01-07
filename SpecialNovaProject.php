<?php
class SpecialNovaProject extends SpecialPage {

	var $userNova, $adminNova;

	function __construct() {
		parent::__construct( 'NovaProject' );

		global $wgOpenStackManagerNovaAdminKeys;

		$this->userLDAP = new OpenStackNovaUser();
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );
	}

	public function isRestricted() {
		return true;
	}

#	public function userCanExecute( $user ) {
#		global $wgRequest;
#
#		#$project = $wgRequest->getVal('project');
#		#if ( $project && ! $this->userLDAP->inProject( $project ) ) {
#		#	return false;
#		#}
#		return true;
#	}

	function execute( $par ) {
		global $wgRequest, $wgUser;

		# if ( ! $wgUser->isAllowed( 'manageproject' ) ) {
		#	return false;
		# }
		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return false;
		}

		$action = $wgRequest->getVal( 'action' );
		if ( $action == "create" ) {
			$this->createProject();
		} else if ( $action == "delete" ) {
			$this->deleteProject();
		} else if ( $action == "addmember" ) {
			$this->addMember();
		} else if ( $action == "deletemember" ) {
			$this->deleteMember();
		} else {
			$this->listProjects();
		}
	}

	function notLoggedIn() {
		global $wgOut;
		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-notloggedin' ) );
		$wgOut->addWikiMsg( 'openstackmanager-mustbeloggedin' );
	}

	function noCredentials() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( "No Nova credentials found for your account" );
		$wgOut->addWikiMsg( 'openstackmanager-nonovacred-admincreate' );
	}

	function createProject() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-createproject' ) );

		$projectInfo = Array();
		$projectInfo['projectname'] = array(
			'type' => 'text',
			'label-message' => 'projectname',
			'default' => '',
			'section' => 'project/info',
		);

		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
		);

		$projectForm = new SpecialNovaProjectForm( $projectInfo, 'novaprojectform' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-createprojectsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$projectForm->show();

		return true;
	}

	function addMember() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addmember' ) );

		$project = $wgRequest->getText( 'projectname' );
		$projectInfo = Array();
		$projectInfo['member'] = array(
			'type' => 'text',
			'label-message' => 'member',
			'default' => '',
			'section' => 'project/info',
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addmember',
		);
		$projectInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $project,
		);

		$projectForm = new SpecialNovaProjectForm( $projectInfo, 'novaprojectform' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-addmembersubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryAddMemberSubmit' ) );
		$projectForm->show();

		return true;
	}

	function deleteMember() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-removemember' ) );

		$member = $wgRequest->getText( 'member' );
		$project = $wgRequest->getText( 'projectname' );
		if ( ! $wgRequest->wasPosted() ) {
			$out .= Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removememberconfirm', $member, $project ) );
			$wgOut->addHTML( $out );
		}
		$projectInfo = Array();
		$projectInfo['member'] = array(
			'type' => 'hidden',
			'default' => $member,
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'deletemember',
		);
		$projectInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $project,
		);

		$projectForm = new SpecialNovaProjectForm( $projectInfo, 'novaprojectform' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-deletemembersubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryDeleteMemberSubmit' ) );
		$projectForm->show();

		return true;
	}

	function deleteProject() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-deleteproject' ) );

		$project = $wgRequest->getText( 'projectname' );
		if ( ! $wgRequest->wasPosted() ) {
			$out .= Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removeprojectconfirm', $project ) );
			$wgOut->addHTML( $out );
		}
		$projectInfo = Array();
		$projectInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
		);
		$projectForm = new SpecialNovaProjectForm( $projectInfo, 'novaproject-form' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-deleteprojectsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$projectForm->setSubmitText( 'confirm' );
		$projectForm->show();

		return true;
	}

	function listProjects() {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-projectlist' ) );

		$out = '';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-createproject' ), array(), array( 'action' => 'create' ), array() );
				$projectsOut = Html::element( 'th', array(), 'Project name' );
				$projectsOut .= Html::element( 'th', array(), 'Members' );
				$projectsOut .= Html::element( 'th', array(), 'Action' );
		$projects = OpenStackNovaProject::getAllProjects();
		if ( ! $projects ) {
			$projectsOut = '';
		}
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			$projectOut = Html::element( 'td', array(), $projectName );
			$projectMembers = $project->getMembers();
			$memberOut = '';
			foreach ( $projectMembers as $projectMember ) {
				$link = $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-removemember' ), array(),
								   array( 'action' => 'deletemember', 'projectname' => $projectName, 'member' => $projectMember ), array() );
				$projectMemberOut = htmlentities( $projectMember ) . ' (' . $link . ')';
				$memberOut .= Html::rawElement( 'li', array(), $projectMemberOut );
			}
			if ( $memberOut ) {
				$memberOut .= '<br />';
				$memberOut = Html::rawElement( 'ul', array(), $memberOut );
			}
			$memberOut .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-addmember' ), array(),
									 array( 'action' => 'addmember', 'projectname' => $projectName ), array() );
			$projectOut .= Html::rawElement( 'td', array(), $memberOut );
			$link = $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-deleteproject' ), array(),
							   array( 'action' => 'delete', 'projectname' => $projectName ), array() );
			$projectOut .= Html::rawElement( 'td', array(), $link );
			$projectsOut .= Html::rawElement( 'tr', array(), $projectOut );
		}
		if ( $projectsOut ) {
			$out .= Html::rawElement( 'table', array( 'class' => 'wikitable' ), $projectsOut );
		}

		$wgOut->addHTML( $out );
	}

	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$success = OpenStackNovaProject::createProject( $formData['projectname'] );
		if ( ! $success ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-createprojectfailed' ) );
			$wgOut->addHTML( $out );
			return false;
		}
		$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-createdproject' ) );
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backprojectlist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$success = OpenStackNovaProject::deleteProject( $formData['projectname'] );
		if ( $success ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-deletedproject' ) );
		} else {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-deleteprojectfailed' ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backprojectlist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	function tryAddMemberSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$project = new OpenStackNovaProject( $formData['projectname'] );
		$success = $project->addMember( $formData['member'] );
		if ( $success ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-addedto', $formData['member'],
			                                              $formData['projectname'] ) );
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-failedtoadd', $formData['member'],
			                                              $formData['projectname'] ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backprojectlist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	function tryDeleteMemberSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$project = new OpenStackNovaProject( $formData['projectname'] );
		$success = $project->deleteMember( $formData['member'] );
		if ( $success ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-removedfrom', $formData['member'],
			                                              $formData['projectname'] ) );
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-failedtoremove', $formData['member'],
			                                              $formData['projectname'] ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backprojectlist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}
}

class SpecialNovaProjectForm extends HTMLForm {
}

<?php
 
abstract class SpecialNova extends SpecialPage {
	function notLoggedIn() {
			global $wgOut;

			$this->setHeaders();
			$wgOut->setPagetitle("Not logged in");
			$wgOut->addHTML('<p>You must be logged in to perform this action</p>');
	}

	function noCredentials() {
			global $wgOut;

			$this->setHeaders();
			$wgOut->setPagetitle("No Nova credentials found for your account");
			$wgOut->addHTML('<p>There were no Nova credentials found for your user account. ' .
							'Please ask a Nova administrator to create credentials for you.</p>');
	}

	function notInProject() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("Your account is not in the project requested");
		$wgOut->addHTML('<p>You can not complete the action requested as your user account is not in the project requested.</p>');
	}
}

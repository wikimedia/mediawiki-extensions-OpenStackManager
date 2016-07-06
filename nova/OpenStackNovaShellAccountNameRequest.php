<?php

use MediaWiki\Auth\AuthenticationRequest;

class OpenStackNovaShellAccountNameRequest extends AuthenticationRequest {
	public $shellaccountname;

	public function getFieldInfo() {
		return [
			'shellaccountname' => [
				'type' => 'string',
				'label' => wfMessage( 'openstackmanager-shellaccountname' ),
				'help' => wfMessage( 'openstackmanager-shellaccountnamehelp' ),
			],
		];
	}
}


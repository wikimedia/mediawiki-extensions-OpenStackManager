<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	[
		'EchoOpenStackManagerPresentationModel.php',
		'OpenStackManager.php',
		'OpenStackManagerEvent.php',
		'OpenStackManagerHooks.php',
	]
);

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'api',
		'nova',
		'special',
		'../../extensions/Echo',
		'../../extensions/LdapAuthentication',
		'../../extensions/TitleBlacklist',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'OpenStackManager.php',
		'../../extensions/Echo',
		'../../extensions/LdapAuthentication',
		'../../extensions/TitleBlacklist',
	]
);

return $cfg;

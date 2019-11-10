<?php
/**
 * OpenStackManager extension
 *
 * For more info see https://mediawiki.org/wiki/Extension:OpenStackManager
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Lane <rlane@wikimedia.org>
 * @copyright © 2010 Ryan Lane
 * @license GPL-2.0-or-later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'OpenStackManager' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['OpenStackManager'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['OpenStackManagerAlias'] = __DIR__ . '/OpenStackManager.alias.php';

	/* wfWarn(
		'Deprecated PHP entry point used for OpenStackManager extension. '.
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the OpenStackManager extension requires MediaWiki 1.29+' );
}

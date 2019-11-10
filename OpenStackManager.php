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

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This file is an extension to the MediaWiki software and cannot be used standalone.\n";
	die( 1 );
}

$wgExtensionCredits['other'][] = [
	'path' => __FILE__,
	'name' => 'OpenStackManager',
	'author' => 'Ryan Lane',
	'version' => '3.0.0',
	'url' => 'https://mediawiki.org/wiki/Extension:OpenStackManager',
	'descriptionmsg' => 'openstackmanager-desc',
	'license-name' => 'GPL-2.0-or-later',
];

define( "NS_NOVA_RESOURCE", 498 );
define( "NS_NOVA_RESOURCE_TALK", 499 );
define( 'NS_HIERA', 666 );
define( 'NS_HIERA_TALK', 667 );

$wgExtraNamespaces[NS_HIERA] = 'Hiera';
$wgExtraNamespaces[NS_HIERA_TALK] = 'Hiera_Talk';

$wgExtraNamespaces[NS_NOVA_RESOURCE] = 'Nova_Resource';
$wgExtraNamespaces[NS_NOVA_RESOURCE_TALK] = 'Nova_Resource_Talk';
$wgContentNamespaces[] = NS_NOVA_RESOURCE;

$wgHooks['getUserPermissionsErrors'][] = 'OpenStackManagerHooks::getUserPermissionsErrors';

// SSH key storage location, ldap or nova
$wgOpenStackManagerNovaKeypairStorage = 'ldap';
// LDAP Auth domain used for OSM
$wgOpenStackManagerLDAPDomain = '';
// UserDN used for reading and writing on the LDAP database
$wgOpenStackManagerLDAPUser = '';
// Actual username of the LDAP user
$wgOpenStackManagerLDAPUsername = '';
// Password used to bind
$wgOpenStackManagerLDAPUserPassword = '';

$wgOpenStackManagerIdRanges = [
	'service' => [
		'gid' => [ 'min' => 40000, 'max' => 49999 ],
	],
];

/**
 * Path to the ssh-keygen utility. Used for converting ssh key formats. False to disable its use.
 */
$wgSshKeygen = 'ssh-keygen';
/**
 * Path to the puttygen utility. Used for converting ssh key formats. False to disable its use.
 */
$wgPuttygen = 'puttygen';

$dir = __DIR__ . '/includes/';

$wgMessagesDirs['OpenStackManager'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['OpenStackManagerAlias'] = __DIR__ . '/OpenStackManager.alias.php';
$wgAutoloadClasses['OpenStackManagerHooks'] = $dir . 'OpenStackManagerHooks.php';
$wgAutoloadClasses['OpenStackNovaUser'] = $dir . 'OpenStackNovaUser.php';
$wgAutoloadClasses['OpenStackNovaShellAccountNameRequest'] =
	$dir . 'OpenStackNovaShellAccountNameRequest.php';
$wgAutoloadClasses['OpenStackNovaSecondaryAuthenticationProvider'] =
	$dir . 'OpenStackNovaSecondaryAuthenticationProvider.php';
$wgAutoloadClasses['SpecialNovaKey'] = $dir . 'SpecialNovaKey.php';
$wgSpecialPages['NovaKey'] = 'SpecialNovaKey';

$wgHooks['LDAPSetCreationValues'][] = 'OpenStackNovaUser::LDAPSetCreationValues';
$wgHooks['LDAPRetrySetCreationValues'][] = 'OpenStackNovaUser::LDAPRetrySetCreationValues';
$wgHooks['LDAPModifyUITemplate'][] = 'OpenStackNovaUser::LDAPModifyUITemplate';
$wgHooks['GetPreferences'][] = 'OpenStackNovaUser::novaUserPreferences';
$wgHooks['AuthChangeFormFields'][] = 'OpenStackNovaUser::AuthChangeFormFields';

$wgAuthManagerAutoConfig['preauth'] += [
	OpenStackNovaSecondaryAuthenticationProvider::class => [
		'class' => OpenStackNovaSecondaryAuthenticationProvider::class,
		'sort' => 0, // non-UI providers should run early
	],
];

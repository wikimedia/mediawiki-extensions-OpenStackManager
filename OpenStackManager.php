<?php
/**
 * OpenStackManager extension - lets users manage nova and swift
 *
 *
 * For more info see http://mediawiki.org/wiki/Extension:OpenStackManager
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Lane <rlane@wikimedia.org>
 * @copyright © 2010 Ryan Lane
 * @license GNU General Public Licence 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'OpenStackManager',
	'author' => 'Ryan Lane',
	'version' => '0.6',
	'url' => 'http://mediawiki.org/wiki/Extension:OpenStackManager',
	'descriptionmsg' => 'openstackmanager-desc',
);

define( "NS_VM", 498 );
define( "NS_VM_TALK", 499 );
$wgExtraNamespaces[NS_VM] = 'VM';
$wgExtraNamespaces[NS_VM_TALK] = 'VM_talk';

$wgGroupPermissions['sysop']['manageproject'] = true;
$wgAvailableRights[] = 'manageproject';

$wgOpenStackManagerNovaDisableSSL = true;
$wgOpenStackManagerNovaServerName = 'localhost';
$wgOpenStackManagerNovaPort = 8773;
$wgOpenStackManagerNovaResourcePrefix = '/services/Cloud/';
$wgOpenStackManagerNovaAdminKeys = array( 'accessKey' => '', 'secretKey' => '' );
$wgOpenStackManagerNovaKeypairStorage = 'ldap';
$wgOpenStackManagerLDAPDomain = '';
$wgOpenStackManagerLDAPUser = '';
$wgOpenStackManagerLDAPUserPassword = '';
$wgOpenStackManagerLDAPProjectBaseDN = '';
$wgOpenStackManagerLDAPGlobalRoles = array(
	'sysadmin' => '',
	'netadmin' => '',
	'cloudadmin' => '',
	);
$wgOpenStackManagerLDAPRolesIntersect = false;
$wgOpenStackManagerLDAPInstanceBaseDN = '';
$wgOpenStackManagerLDAPDefaultGid = '500';
$wgOpenStackManagerDNSServers = array( 'primary' => 'localhost', 'secondary' => 'localhost' );
$wgOpenStackManagerDNSSOA = array( 'hostmaster' => 'hostmaster@localhost.localdomain', 'refresh' => '1800', 'retry' => '3600', 'expiry' => '86400', 'minimum' => '7200' );
$wgOpenStackManagerPuppetOptions = array(
	'enabled' => false,
	'defaultclasses' => array(),
	'defaultvariables' => array(),
	'availableclasses' => array(),
	'availablevariables' => array(),
	);

$dir = dirname( __FILE__ ) . '/';

$wgExtensionMessagesFiles['OpenStackManager'] = $dir . 'OpenStackManager.i18n.php';
$wgExtensionAliasesFiles['OpenStackManager'] = $dir . 'OpenStackManager.alias.php';
$wgAutoloadClasses['OpenStackNovaInstance'] = $dir . 'OpenStackNovaInstance.php';
$wgAutoloadClasses['OpenStackNovaKeypair'] = $dir . 'OpenStackNovaKeypair.php';
$wgAutoloadClasses['OpenStackNovaController'] = $dir . 'OpenStackNovaController.php';
$wgAutoloadClasses['OpenStackNovaUser'] = $dir . 'OpenStackNovaUser.php';
$wgAutoloadClasses['OpenStackNovaDomain'] = $dir . 'OpenStackNovaDomain.php';
$wgAutoloadClasses['OpenStackNovaHost'] = $dir . 'OpenStackNovaHost.php';
$wgAutoloadClasses['OpenStackNovaAddress'] = $dir . 'OpenStackNovaAddress.php';
$wgAutoloadClasses['SpecialNovaInstance'] = $dir . 'special/SpecialNovaInstance.php';
$wgAutoloadClasses['SpecialNovaKey'] = $dir . 'special/SpecialNovaKey.php';
$wgAutoloadClasses['SpecialNovaProject'] = $dir . 'special/SpecialNovaProject.php';
$wgAutoloadClasses['SpecialNovaDomain'] = $dir . 'special/SpecialNovaDomain.php';
$wgAutoloadClasses['SpecialNovaAddress'] = $dir . 'special/SpecialNovaAddress.php';
$wgAutoloadClasses['SpecialNova'] = $dir . 'special/SpecialNova.php';
$wgAutoloadClasses['OpenStackNovaHostJob'] = $dir . 'OpenStackNovaHostJob.php';
$wgAutoloadClasses['AmazonEC2'] = $dir . 'aws-sdk/sdk.class.php';
$wgSpecialPages['NovaInstance'] = 'SpecialNovaInstance';
$wgSpecialPageGroups['NovaInstance'] = 'nova';
$wgSpecialPages['NovaKey'] = 'SpecialNovaKey';
$wgSpecialPageGroups['NovaKey'] = 'nova';
$wgSpecialPages['NovaProject'] = 'SpecialNovaProject';
$wgSpecialPageGroups['NovaProject'] = 'nova';
$wgSpecialPages['NovaDomain'] = 'SpecialNovaDomain';
$wgSpecialPageGroups['NovaDomain'] = 'nova';
$wgSpecialPages['NovaAddress'] = 'SpecialNovaAddress';
$wgSpecialPageGroups['NovaAddress'] = 'nova';
$wgJobClasses['addDNSHostToLDAP'] = 'OpenStackNovaHostJob';

$wgHooks['LDAPSetCreationValues'][] = 'OpenStackNovaUser::LDAPSetCreationValues';
$wgHooks['LDAPModifyUITemplate'][] = 'OpenStackNovaUser::LDAPModifyUITemplate';

require_once( "$IP/extensions/OpenStackManager/OpenStackNovaProject.php" );

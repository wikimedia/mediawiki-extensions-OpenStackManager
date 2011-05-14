<?php
/**
 * Aliases for OpenStackManager special pages
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	'OpenStackManager' => array( 'OpenStackManager' ),
	'OpenStackManageInstance' => array( 'Manage Instance' ),
);

/** Arabic (العربية) */
$specialPageAliases['ar'] = array(
	'OpenStackManager' => array( 'مدير_ستاك_مفتوح' ),
	'OpenStackManageInstance' => array( 'التحكم_بها' ),
);

/** Macedonian (Македонски) */
$specialPageAliases['mk'] = array(
	'OpenStackManager' => array( 'РаководителСоOpenStack' ),
	'OpenStackManageInstance' => array( 'РаководиСоПримерок' ),
);

/** Dutch (Nederlands) */
$specialPageAliases['nl'] = array(
	'OpenStackManageInstance' => array( 'InstantieBeheren' ),
);

/**
 * For backwards compatibility with MediaWiki 1.15 and earlier.
 */
$aliases =& $specialPageAliases;
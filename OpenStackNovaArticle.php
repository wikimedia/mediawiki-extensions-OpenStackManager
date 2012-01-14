<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

if ( !defined( 'MEDIAWIKI' ) ) {
        echo( "This file is a part of the OpenStackManager extension and cannot be used standalone.\n" );
        die( 1 );
}

class OpenStackNovaArticle {

	public static function canCreatePages() {
		global $wgOpenStackManagerCreateResourcePages;

		return $wgOpenStackManagerCreateResourcePages;
	}

	public static function editArticle( $titletext, $text ) {
		$title = Title::newFromText( $titletext, NS_NOVA_RESOURCE );
		$article = new Article( $title, 0 );
		$article->doEdit( $text, '' );
	}

	public static function deleteArticle( $titletext ) {
		if ( ! OpenStackNovaArticle::canCreatePages() ) {
			return;
		}
		$title = Title::newFromText( $titletext, NS_NOVA_RESOURCE );
		$article = new Article( $title, 0 );
		$article->doDeleteArticle( '' );
	}

}

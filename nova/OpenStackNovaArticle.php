<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaArticle {
	public static function canCreatePages() {
		global $wgOpenStackManagerCreateResourcePages;

		return $wgOpenStackManagerCreateResourcePages;
	}

	public static function editArticle( $titletext, $text ) {
		$title = Title::newFromText( $titletext, NS_NOVA_RESOURCE );
		$article = WikiPage::factory( $title );
		$article->doEdit( $text, '' );
	}

	public static function getText( $titletext ) {
		$title = Title::newFromText( $titletext, NS_NOVA_RESOURCE );
		$article = WikiPage::factory( $title );
		return $article->getText();
	}

	public static function deleteArticle( $titletext ) {
		if ( ! OpenStackNovaArticle::canCreatePages() ) {
			return;
		}
		$title = Title::newFromText( $titletext, NS_NOVA_RESOURCE );
		$article = WikiPage::factory( $title );
		$article->doDeleteArticle( '' );
	}
}

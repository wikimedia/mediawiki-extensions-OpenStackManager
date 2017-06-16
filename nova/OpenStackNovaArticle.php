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

	public static function editArticle( $titletext, $text, $namespace = NS_NOVA_RESOURCE ) {
		$title = Title::newFromText( $titletext, $namespace );
		$article = WikiPage::factory( $title );
		$article->doEditContent( ContentHandler::makeContent( $text, $title ), '' );
	}

	public static function getText( $titletext, $namespace = NS_NOVA_RESOURCE ) {
		$title = Title::newFromText( $titletext, $namespace );
		$article = WikiPage::factory( $title );
		$content = $article->getContent();
		$text = ContentHandler::getContentText( $content );
		return $text;
	}

	public static function deleteArticle( $titletext, $namespace = NS_NOVA_RESOURCE ) {
		if ( !OpenStackNovaArticle::canCreatePages() ) {
			return;
		}
		$title = Title::newFromText( $titletext, $namespace );
		$article = WikiPage::factory( $title );
		$article->doDeleteArticle( '' );
	}
}

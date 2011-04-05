<?php

class OpenStackNovaArticle {

	public static function canCreatePages() {
                global $wgOpenStackManagerCreateResourcePages;

                if ( $wgOpenStackManagerCreateResourcePages ) {
                        return true;
                } else {
			return false;
		}
	}

	public static function editArticle( $titletext, $text ) {
		$title = Title::newFromText( $titletext, NS_NOVA_RESOURCE );
		$article = new Article( $title );
		$article->doEdit( $text, '' );
	}

	public static function deleteArticle( $titletext ) {
		if ( ! OpenStackNovaArticle::canCreatePages() ) {
			return;
		}
                $title = Title::newFromText( $titletext, NS_NOVA_RESOURCE );
                $article = new Article( $title );
                $article->doDeleteArticle( '' );
	}

}

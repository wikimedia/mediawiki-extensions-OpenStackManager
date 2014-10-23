<?php
/**
 * YAML Schema Content Handler
 *
 * @file
 *
 * @author Ori Livneh <ori@wikimedia.org>
 * @author Kunal Mehta <legoktm@gmail.com>
 * @author Yuvi Panda <yuvipanda@gmail.com>
 */

class YamlContentHandler extends CodeContentHandler {

	public function __construct( $modelId = CONTENT_MODEL_YAML ) {
		parent::__construct( $modelId, array( CONTENT_FORMAT_YAML ) );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return 'YamlContent';
	}
}

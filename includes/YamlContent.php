<?php
/**
 * YAML Content Model
 *
 * @file
 *
 * @author Ori Livneh <ori@wikimedia.org>
 * @author Kunal Mehta <legoktm@gmail.com>
 * @author Yuvi Panda <yuvipanda@gmail.com>
 */

/**
 * Represents the content of a YAML content.
 */
class YamlContent extends TextContent {

	public function __construct( $text, $modelId = CONTENT_MODEL_YAML ) {
		parent::__construct( $text, $modelId );
	}

	/**
	 * Decodes the YAML into a PHP associative array.
	 * @return array
	 */
	public function getYamlData() {
		return Spyc::YAMLLoadString( $this->getNativeData(), true );
	}

	/**
	 * @return bool Whether content is valid YAML.
	 */
	public function isValid() {
		return $this->getYamlData() !== null;
	}

	/**
	 * Pretty-print YAML
	 *
	 * @return bool|null|string
	 */
	public function beautifyYAML() {
		$decoded = $this->getYamlData();
		if ( !is_array( $decoded ) ) {
			return null;
		}
		return Spyc::YAMLDump( $decoded, 4, 0 );

	}

	/**
	 * Beautifies YAML prior to save.
	 * @param Title $title Title
	 * @param User $user User
	 * @param ParserOptions $popts
	 * @return YamlContent
	 */
	public function preSaveTransform( Title $title, User $user, ParserOptions $popts ) {
		return new static( $this->beautifyYAML() );
	}

	/**
	 * Set the HTML and add the appropriate styles
	 *
	 *
	 * @param Title $title
	 * @param int $revId
	 * @param ParserOptions $options
	 * @param bool $generateHtml
	 * @param ParserOutput $output
	 */
	protected function fillParserOutput( Title $title, $revId,
		ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		if ( $generateHtml ) {
			$html = Html::element( 'pre', array(
				'class' => 'mw-code mw-yaml',
				'dir' => 'ltr',
			), $this->getNativeData() );

			$output->setText( $html );
		} else {
			$output->setText( '' );
		}
	}
}

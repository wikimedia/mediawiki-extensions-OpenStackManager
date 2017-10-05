<?php

/**
 * Class that returns structured data for the osm echo events.
 * @see https://www.mediawiki.org/wiki/Echo_%28Notifications%29/New_formatter_system
 */
class EchoOpenStackManagerPresentationModel extends EchoEventPresentationModel {

	public function getIconType() {
		switch ( $this->type ) {
			case 'osm-instance-deleted':
				return 'trash';
			case 'osm-instance-build-completed':
			case 'osm-instance-reboot-completed':
			case 'osm-projectmembers-add':
			default:
				return 'placeholder';
		}
	}

	public function canRender() {
		return (bool)$this->event->getTitle();
	}

	public function getPrimaryLink() {
		return [
			'url' => $this->event->getTitle()->getFullUrl(),
			'label' => $this->msg( 'echo-notification-goto-project' )
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSecondaryLinks() {
		return [ $this->getAgentLink() ];
	}

	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		$truncatedInstanceName = $this->language->embedBidi( $this->language->truncate(
			$this->event->getExtraParam( 'instanceName' ),
			self::PAGE_NAME_RECOMMENDED_LENGTH,
			'...',
			false
		) );

		return $msg->params(
			$this->getTruncatedTitleText( $this->event->getTitle() ),
			$truncatedInstanceName,
			$this->getViewingUserForGender()
		);
	}
}

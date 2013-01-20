<?php
class OpenStackManagerNotificationFormatter extends EchoBasicFormatter {
	protected function processParam( $event, $param, $message, $user ) {
		if ( $param === 'instance' ) {
			$extra = $event->getExtra(); // PHP 5.3 compatability...
			$instance = $extra['instanceName'];
			if ( $instance ) {
				$message->params( $instance );
			} else {
				$message->params( '' );
			}
		} else {
			parent::processParam( $event, $param, $message, $user );
		}
	}
}

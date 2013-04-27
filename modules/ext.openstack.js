( function ( mw, $ ) {
	'use strict';

	$( '.novainstanceaction' ).on( 'click', function ( e ) {
		var action = mw.util.getParamValue( 'action', this.href ),
			$el = $( this ),
			$spinner = $.createSpinner(),
			instance = new mw.openStack.Instance( $el.data() );

		if ( action !== 'reboot' ) {
			// only reboot is supported right now.
			return;
		}

		e.preventDefault();

		$el.hide().after( $spinner );

		instance.api( action )
			.always( function () {
				$spinner.remove();
				$el.show();
			} );
	} );

} ( mediaWiki, jQuery ) );

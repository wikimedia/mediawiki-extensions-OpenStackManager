( function ( mw, $ ) {
	'use strict';

	$( '.novainstanceaction' ).click( function( event ) {
		var $actionLink = $(this),
			$href = $actionLink.prop( 'href' ).split('&'),
			$spinner = $.createSpinner( {
				size: 'small',
				type: 'inline'
			} ),
			args = {},
			argarr,
			i,
			$state,
			$instancename;
		$actionLink.hide().after( $spinner );
		for ( i = 0; i < $href.length; i++ ) {
			argarr = $href[i].split( '=' );
			args[argarr[0]] = argarr[1];
		}

		if ( args.action === 'reboot' ) {
			event.preventDefault();
			$state = $( event.target ).closest( 'tr' ).find( '.novainstancestate' );
			$instancename = $( event.target ).closest( 'tr' ).find( '.novainstancename' );
			$.ajax({
				url: mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php?',
				data: {
					'action'      : 'novainstance',
					'subaction'   : 'reboot',
					'format'      : 'json',
					'instanceid'  : args.instanceid,
					'project'     : args.project,
					'region'      : args.region
				},
				dataType: 'json',
				type: 'POST',
				success: function ( data ) {
					$spinner.remove();
					if ( data.error !== undefined ) {
						mw.notify( mw.msg( 'openstackmanager-rebootinstancefailed', $instancename.text() ) );
					} else {
						mw.notify( mw.msg( 'openstackmanager-rebootedinstance', $instancename.text() ) );
						$state.text( data.novainstance.instancestate );
					}
					$actionLink.show();
				},
				error: function () {
					$spinner.remove();
					mw.notify( mw.msg( 'openstackmanager-rebootinstancefailed', $instancename.text() ) );
					$actionLink.show();
				}
			});
		}
	} );
}( mediaWiki, jQuery ) );

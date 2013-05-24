( function ( mw, $ ) {
	'use strict';

	/**
	 * @class mw.OpenStackInterface
	 * @abstract
	 */
	function OpenStackInterface() {
	}

	var OSIP = OpenStackInterface.prototype;

	/**
	 * Issue a notification about this instance's status. Arguments are applied to mw.msg verbatim.
	 */
	OSIP.notify = function () {
		return mw.notify( mw.msg.apply( mw, arguments ) );
	};

	OSIP.notifyStatus = function ( status, action ) {
		var args = [].slice.call( arguments, 2 );

		if ( this.notifications && this.notifications[action] && this.notifications[action][status] ) {
			this.notify.apply( this, [ this.notifications[action][status] ].concat( args ) );
		}
	};

	OSIP.succeed = function () {
		this.notifyStatus.bind( this, 'success' ).apply( this, arguments );
	};

	OSIP.fail = function () {
		this.notifyStatus.bind( this, 'failure' ).apply( this, arguments );
	};

	/**
	 * Confirmation dialog for an action.
	 * @param {string} msg The text to put in the dialog (use mw.msg!)
	 * @param {Function} ok
	 * @param {Function} cancel
	 * @param {string} titlemsg Optional title for the dialog
	 */
	OSIP.confirm = function ( msg, ok, cancel, titlemsg ) {
		var $c = $( '<div>' );

		if ( titlemsg ) {
			$c.attr( 'title', titlemsg );
		}

		$c.text( msg );
		$c.dialog( {
			buttons: {
				Submit: function () {
					$( this ).dialog( 'destroy' ).remove();
					ok();
				},

				Cancel: function () {
					$( this ).dialog( 'destroy' ).remove();
					cancel();
				}
			}
		} );
	};

	mw.openStack = {};

	mw.OpenStackInterface = OpenStackInterface;
} ( mediaWiki, jQuery ) );

import './style.scss';

/**
 * Public behaviour for `<dialog class="simple-popup">` elements rendered by
 * the plugin: opens them on a trigger (first gesture, page load, or exit
 * intent), remembers the visitor's choice with a per-popup cookie, and
 * tracks conversions in `localStorage`. No dependencies.
 */
( function () {
	'use strict';

	const GESTURES = [ 'scroll', 'wheel', 'click', 'keydown' ];
	// Clicks on these elements, and key presses inside these fields, are the
	// visitor interacting with the page on purpose: they don't count as the
	// "first gesture" that opens a popup.
	const INTERACTIVE_SELECTOR =
		'a, button, input, select, textarea, label, summary, [role="button"], [contenteditable]';
	const FIELD_SELECTOR = 'input, select, textarea, [contenteditable]';
	const IMAGE_WAIT_MS = 1500;

	// Only one popup may open per page view; the first one to be triggered
	// claims it and disarms the others.
	let pageHasOpenedPopup = false;

	function getCookie( name ) {
		const escaped = name.replace( /([.$?*|{}()[\]\\/+^])/g, '\\$1' );
		const match = document.cookie.match( new RegExp( '(?:^|; )' + escaped + '=([^;]*)' ) );
		return match ? decodeURIComponent( match[ 1 ] ) : null;
	}

	function setCookie( name, value, hours ) {
		const expires = new Date();
		expires.setTime( expires.getTime() + hours * 60 * 60 * 1000 );
		document.cookie =
			name +
			'=' +
			encodeURIComponent( value ) +
			'; path=/; expires=' +
			expires.toUTCString() +
			'; SameSite=Lax' +
			( window.location.protocol === 'https:' ? '; Secure' : '' );
	}

	function isInElement( event, selector ) {
		return event.target instanceof Element ? event.target.closest( selector ) !== null : false;
	}

	function SimplePopup( dialog ) {
		this.dialog = dialog;
		this.id = dialog.id.replace( 'simple-popup-', '' );
		this.cookieName = 'simple_popup_' + this.id;
		this.trigger = dialog.dataset.trigger || 'gesture';
		this.delay = ( parseInt( dialog.dataset.delay, 10 ) || 0 ) * 1000;
		this.frequency = dialog.dataset.frequency || 'visit';
		this.frequencyValue = parseInt( dialog.dataset.frequencyValue, 10 ) || 0;
		this.conversionSelector = dialog.dataset.conversion || '';
		this.closeButton = dialog.querySelector( '.simple-popup__close' );
	}

	SimplePopup.prototype.isSeen = function () {
		return getCookie( this.cookieName ) !== null;
	};

	SimplePopup.prototype.markSeen = function () {
		if ( 'once' === this.frequency ) {
			setCookie( this.cookieName, '1', 24 * 365 * 10 );
			return;
		}

		if ( 'hours' === this.frequency ) {
			setCookie( this.cookieName, '1', this.frequencyValue || 24 );
			return;
		}

		// 'visit': sliding expiration, pushed back on every page view while
		// the cookie exists, so it behaves as "once per visit".
		setCookie( this.cookieName, '1', ( this.frequencyValue || 30 ) / 60 );
	};

	SimplePopup.prototype.isConverted = function () {
		try {
			return window.localStorage.getItem( 'simple_popup_converted_' + this.id ) === '1';
		} catch ( e ) {
			return false;
		}
	};

	SimplePopup.prototype.markConverted = function () {
		try {
			window.localStorage.setItem( 'simple_popup_converted_' + this.id, '1' );
		} catch ( e ) {
			// Storage unavailable (private browsing, quota, …): no-op.
		}
	};

	SimplePopup.prototype.canOpen = function () {
		return ! this.isSeen() && ! this.isConverted();
	};

	/**
	 * Forces lazy-loaded images to load, then calls `then()` either once
	 * they're all loaded or after `IMAGE_WAIT_MS`, whichever comes first.
	 */
	SimplePopup.prototype.preloadImages = function ( then ) {
		const images = this.dialog.querySelectorAll( 'img[loading="lazy"]' );
		let remaining = images.length;
		let done = false;

		function finish() {
			if ( done ) {
				return;
			}
			done = true;
			then();
		}

		function oneLess() {
			remaining--;
			if ( remaining <= 0 ) {
				finish();
			}
		}

		for ( let i = 0; i < images.length; i++ ) {
			images[ i ].loading = 'eager';
			if ( images[ i ].complete ) {
				remaining--;
			} else {
				images[ i ].addEventListener( 'load', oneLess );
				images[ i ].addEventListener( 'error', oneLess );
			}
		}

		if ( remaining <= 0 ) {
			finish();
			return;
		}

		window.setTimeout( finish, IMAGE_WAIT_MS );
	};

	SimplePopup.prototype.bindClose = function () {
		const self = this;

		if ( this.closeButton ) {
			this.closeButton.addEventListener( 'click', function () {
				self.dialog.close();
			} );
		}

		// A click on `::backdrop` is reported with the <dialog> itself as the
		// event target.
		this.dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === self.dialog ) {
				self.dialog.close();
			}
		} );
	};

	SimplePopup.prototype.watchConversion = function () {
		const self = this;

		if ( this.conversionSelector ) {
			const observer = new MutationObserver( function () {
				if ( self.dialog.querySelector( self.conversionSelector ) ) {
					self.markConverted();
					observer.disconnect();
				}
			} );
			observer.observe( this.dialog, { childList: true, subtree: true, attributes: true } );
		}

		// Contact Form 7 dispatches this event (bubbling) on successful submission.
		this.dialog.addEventListener( 'wpcf7mailsent', function () {
			self.markConverted();
		} );
	};

	/**
	 * Tries to open this popup; does nothing if another popup already won
	 * the race for this page view, or if this one isn't eligible.
	 */
	SimplePopup.prototype.tryOpen = function () {
		if ( pageHasOpenedPopup || ! this.canOpen() || typeof this.dialog.showModal !== 'function' ) {
			return;
		}

		pageHasOpenedPopup = true;

		const self = this;
		this.preloadImages( function () {
			self.dialog.showModal();
			// Focus the dialog itself (tabindex="-1"), not its first button,
			// so no focus ring shows until the visitor uses the keyboard.
			self.dialog.focus( { preventScroll: true } );
			self.markSeen();
			self.watchConversion();
		} );
	};

	SimplePopup.prototype.armGesture = function () {
		const self = this;
		const pageLoadedAt = window.performance.now();

		function onGesture( event ) {
			if ( 'click' === event.type && isInElement( event, INTERACTIVE_SELECTOR ) ) {
				return;
			}
			if ( 'keydown' === event.type ) {
				if ( 'Escape' === event.key ) {
					return;
				}
				if ( isInElement( event, FIELD_SELECTOR ) ) {
					return;
				}
			}
			if ( window.performance.now() - pageLoadedAt < self.delay ) {
				return;
			}
			unbind();
			self.tryOpen();
		}

		function bind() {
			GESTURES.forEach( function ( type ) {
				window.addEventListener( type, onGesture, { passive: true } );
			} );
		}

		function unbind() {
			GESTURES.forEach( function ( type ) {
				window.removeEventListener( type, onGesture, { passive: true } );
			} );
		}

		bind();
	};

	SimplePopup.prototype.armLoad = function () {
		const self = this;
		window.setTimeout( function () {
			self.tryOpen();
		}, this.delay );
	};

	SimplePopup.prototype.armExit = function () {
		const self = this;

		// Exit intent only makes sense with a precise pointer (desktop).
		if ( ! window.matchMedia || ! window.matchMedia( '(pointer: fine)' ).matches ) {
			return;
		}

		document.documentElement.addEventListener( 'mouseleave', function ( event ) {
			if ( event.clientY > 0 ) {
				return;
			}
			self.tryOpen();
		} );
	};

	SimplePopup.prototype.arm = function () {
		if ( 'visit' === this.frequency && this.isSeen() ) {
			// Already seen during this visit: push the sliding expiration back
			// without re-arming any trigger.
			this.markSeen();
			return;
		}

		if ( ! this.canOpen() ) {
			return;
		}

		this.bindClose();

		if ( 'load' === this.trigger ) {
			this.armLoad();
		} else if ( 'exit' === this.trigger ) {
			this.armExit();
		} else {
			this.armGesture();
		}
	};

	function init() {
		const dialogs = document.querySelectorAll( 'dialog.simple-popup' );
		dialogs.forEach( function ( dialog ) {
			new SimplePopup( dialog ).arm();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

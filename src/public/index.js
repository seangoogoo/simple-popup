import './style.scss';

/**
 * Public behaviour for `<dialog class="simple-popup">` elements rendered by
 * the plugin: opens them on a trigger (first gesture, page load, or exit
 * intent), remembers the visitor's choice with a per-popup cookie, and
 * tracks conversions in `localStorage`. No dependencies.
 *
 * Several popups can be active on the same page. A page-level scheduler
 * (see below) makes sure at most one is open at a time, and keeps a pause
 * between the closing of one and the opening of the next.
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
	const CLOSE_FALLBACK_MS = 600;
	const DEFAULT_GAP_SECONDS = 30;

	// Every non-preview popup on the page, in document order (the same
	// order as the admin list: menu order, then date). Used by the
	// scheduler to resolve ties and to find the next popup to open.
	const allPopups = [];

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

	/**
	 * Page-level scheduler: never two popups open at once, and keeps a pause
	 * (the `simple_popup_gap` setting, in seconds) between the closing of one
	 * and the opening of the next.
	 *
	 * - `load` triggers that can't open right away (another popup is open, or
	 *   the pause is running) are queued and opened automatically as soon as
	 *   the scheduler frees up, in document order (rule 4 "load").
	 * - `gesture`/`exit` triggers that can't open right away are simply
	 *   refused: their own listener stays bound, so a later qualifying event
	 *   gets a fresh chance once the scheduler is free (rules 4 "gesture"/"exit").
	 */
	const scheduler = ( function () {
		let current = null;
		let paused = false;

		function busy() {
			return null !== current || paused;
		}

		function openNow( popup ) {
			current = popup;
			popup.doOpen();
		}

		/**
		 * Looks for the first queued `load` popup, in document order, that
		 * can still open, and opens it. No-op while the scheduler is busy.
		 */
		function processQueue() {
			if ( busy() ) {
				return;
			}

			for ( let i = 0; i < allPopups.length; i++ ) {
				const popup = allPopups[ i ];

				if ( ! popup.pendingLoad ) {
					continue;
				}

				popup.pendingLoad = false;

				if ( popup.canOpen() ) {
					openNow( popup );
					return;
				}
			}
		}

		function getGapSeconds() {
			const config = window.simplePopupConfig;
			return config && 'number' === typeof config.gap && config.gap >= 0
				? config.gap
				: DEFAULT_GAP_SECONDS;
		}

		function onClosed( popup ) {
			if ( current === popup ) {
				current = null;
			}

			const gapSeconds = getGapSeconds();

			if ( gapSeconds <= 0 ) {
				processQueue();
				return;
			}

			paused = true;
			window.setTimeout( function () {
				paused = false;
				processQueue();
			}, gapSeconds * 1000 );
		}

		/**
		 * Tries to open `popup` now. Returns `true` if it opened. If it
		 * can't (busy, or not eligible), returns `false`; `queueOnBusy`
		 * popups (load triggers) are then queued for the next opportunity.
		 */
		function attemptOpen( popup, queueOnBusy ) {
			if ( ! popup.canOpen() ) {
				popup.pendingLoad = false;
				return false;
			}

			if ( busy() ) {
				if ( queueOnBusy ) {
					popup.pendingLoad = true;
				}
				return false;
			}

			openNow( popup );
			return true;
		}

		return { attemptOpen: attemptOpen, onClosed: onClosed };
	} )();

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
		// Preview (`?simple_popup_preview={ID}`): opened immediately, outside
		// the scheduler, without reading or writing any cookie/conversion.
		this.isPreview = '1' === dialog.dataset.preview;
		// Set by the scheduler while this `load` popup is waiting for the
		// pause to end.
		this.pendingLoad = false;
	}

	SimplePopup.prototype.isSeen = function () {
		return ! this.isPreview && getCookie( this.cookieName ) !== null;
	};

	SimplePopup.prototype.markSeen = function () {
		if ( this.isPreview ) {
			return;
		}

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
		if ( this.isPreview ) {
			return false;
		}

		try {
			return window.localStorage.getItem( 'simple_popup_converted_' + this.id ) === '1';
		} catch ( e ) {
			return false;
		}
	};

	SimplePopup.prototype.markConverted = function () {
		if ( this.isPreview ) {
			return;
		}

		try {
			window.localStorage.setItem( 'simple_popup_converted_' + this.id, '1' );
		} catch ( e ) {
			// Storage unavailable (private browsing, quota, …): no-op.
		}
	};

	SimplePopup.prototype.canOpen = function () {
		return (
			typeof this.dialog.showModal === 'function' && ! this.isSeen() && ! this.isConverted()
		);
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

	/**
	 * Plays the closing animation, then closes the dialog. Closes at once when
	 * the visitor prefers reduced motion (no animation is defined then).
	 */
	SimplePopup.prototype.close = function () {
		const dialog = this.dialog;

		if ( ! dialog.open || dialog.classList.contains( 'simple-popup--closing' ) ) {
			return;
		}

		if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
			dialog.close();
			return;
		}

		let done = false;
		function finish() {
			if ( done ) {
				return;
			}
			done = true;
			dialog.removeEventListener( 'animationend', onEnd );
			dialog.classList.remove( 'simple-popup--closing' );
			dialog.close();
		}
		function onEnd( event ) {
			if ( event.target === dialog ) {
				finish();
			}
		}

		dialog.addEventListener( 'animationend', onEnd );
		dialog.classList.add( 'simple-popup--closing' );
		// Safety net if `animationend` never fires (styles overridden by a theme).
		window.setTimeout( finish, CLOSE_FALLBACK_MS );
	};

	SimplePopup.prototype.bindClose = function () {
		const self = this;

		if ( this.closeButton ) {
			this.closeButton.addEventListener( 'click', function () {
				self.close();
			} );
		}

		// A click on `::backdrop` is reported with the <dialog> itself as the
		// event target.
		this.dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === self.dialog ) {
				self.close();
			}
		} );

		// Escape fires `cancel`: animate instead of the instant native close.
		this.dialog.addEventListener( 'cancel', function ( event ) {
			event.preventDefault();
			self.close();
		} );
	};

	/**
	 * Tells the scheduler once this popup's `<dialog>` actually closes
	 * (whatever triggered it: close button, backdrop, Escape, or script),
	 * so it can start the pause before the next popup may open.
	 */
	SimplePopup.prototype.bindScheduler = function () {
		const self = this;

		this.dialog.addEventListener( 'close', function () {
			scheduler.onClosed( self );
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
	 * Opens this popup: preloads its lazy images, shows the dialog, focuses
	 * it, and records the cookie/conversion watcher. Called by the scheduler
	 * once it has decided this popup may open now.
	 */
	SimplePopup.prototype.doOpen = function () {
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

	/**
	 * Opens a preview popup immediately, outside the scheduler: no cookie,
	 * no conversion tracking, no other popup involved (the page only
	 * contains this one `<dialog>` while previewing).
	 */
	SimplePopup.prototype.openPreview = function () {
		if ( typeof this.dialog.showModal !== 'function' ) {
			return;
		}

		this.bindClose();

		const self = this;
		this.preloadImages( function () {
			self.dialog.showModal();
			self.dialog.focus( { preventScroll: true } );
		} );
	};

	SimplePopup.prototype.armGesture = function () {
		const self = this;
		const pageLoadedAt = window.performance.now();

		function onGesture( event ) {
			if ( ! self.canOpen() ) {
				// No longer eligible (seen/converted elsewhere): stop listening.
				unbind();
				return;
			}
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
			// Only disarm once this popup actually opens. While another
			// popup is open or the pause is running, this gesture doesn't
			// count: a later one must give this popup a fresh chance
			// (rule 4, "gesture").
			if ( scheduler.attemptOpen( self, false ) ) {
				unbind();
			}
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
			// Queue this popup if the scheduler is busy: a `load` trigger
			// opens automatically as soon as it frees up (rule 4, "load").
			scheduler.attemptOpen( self, true );
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
			// Not queued: if the scheduler is busy, this exit intent doesn't
			// count, a later one must try again (rule 4, "exit").
			scheduler.attemptOpen( self, false );
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
		this.bindScheduler();

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
			const popup = new SimplePopup( dialog );

			if ( popup.isPreview ) {
				popup.openPreview();
				return;
			}

			allPopups.push( popup );
		} );

		// Armed in a second pass, in document order, once `allPopups` is
		// complete: the scheduler resolves ties (several popups ready at the
		// same moment) by that same order.
		allPopups.forEach( function ( popup ) {
			popup.arm();
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();

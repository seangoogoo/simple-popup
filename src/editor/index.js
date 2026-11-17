import { __, _n, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import {
	ToggleControl,
	SelectControl,
	TextControl,
	PanelRow,
	Notice,
	ExternalLink,
} from '@wordpress/components';
import { PluginDocumentSettingPanel as EditorPanel } from '@wordpress/editor';
import { PluginDocumentSettingPanel as EditPostPanel } from '@wordpress/edit-post';

// `@wordpress/editor` only started exporting PluginDocumentSettingPanel in
// WordPress 6.6; `@wordpress/edit-post` keeps exporting it (deprecated) for
// older sites, so fall back to it when the former isn't available yet.
const PluginDocumentSettingPanel = EditorPanel || EditPostPanel;

const POST_TYPE = 'simple_popup';

/**
 * Minimum number of seconds enforced between the closing of a popup and the
 * opening of the next one, injected before this script via
 * `wp_add_inline_script()` (see class-simple-popup-editor.php). Falls back
 * to the plugin's own default if, for any reason, it isn't there.
 */
const GAP = ( window.simplePopupEditor && Number.isFinite( window.simplePopupEditor.gap ) )
	? window.simplePopupEditor.gap
	: 30;

const TIME_BASED_TRIGGERS = [ 'load', 'gesture' ];

/**
 * Human sentence describing when and how often a popup opens. Mirrors
 * `Simple_Popup_CPT::describe()` (PHP), used by the admin list "When"
 * column and the "Diffusion plan" box, so edits made here are reflected
 * immediately, before the post is even saved.
 */
function describePopup( trigger, delay, frequency, frequencyValue ) {
	let moment;

	if ( 'load' === trigger ) {
		moment = sprintf(
			/* translators: %d: number of seconds. */
			_n( 'Opens %d second after the page loads', 'Opens %d seconds after the page loads', delay, 'simple-popup' ),
			delay
		);
	} else if ( 'exit' === trigger ) {
		moment = __( 'Opens when the mouse leaves the page (desktop only)', 'simple-popup' );
	} else {
		moment = sprintf(
			/* translators: %d: number of seconds. */
			_n( 'Opens on the first gesture after %d second', 'Opens on the first gesture after %d seconds', delay, 'simple-popup' ),
			delay
		);
	}

	let frequencyText;

	if ( 'once' === frequency ) {
		frequencyText = __( 'only once', 'simple-popup' );
	} else if ( 'hours' === frequency ) {
		frequencyText = sprintf(
			/* translators: %d: number of hours. */
			_n( 'at most once every %d hour', 'at most once every %d hours', frequencyValue, 'simple-popup' ),
			frequencyValue
		);
	} else {
		frequencyText = sprintf(
			/* translators: %d: number of minutes of inactivity. */
			__( 'once per visit (%d min. of inactivity)', 'simple-popup' ),
			frequencyValue
		);
	}

	return sprintf(
		/* translators: 1: when the popup opens, 2: how often it opens. */
		__( '%1$s, %2$s.', 'simple-popup' ),
		moment,
		frequencyText
	);
}

/**
 * Conflict notices for the currently edited popup (only meaningful while
 * it's active): same trigger and delay as another published and active
 * popup in the same language, or a delay close enough to fall inside the
 * global pause.
 *
 * @param {Object}   args
 * @param {number}   args.postId   Edited popup's post ID.
 * @param {string}   args.trigger  Edited popup's trigger.
 * @param {number}   args.delay    Edited popup's delay, in seconds.
 * @param {Object[]} args.siblings Other published and active popups in the same language.
 * @return {string[]}
 */
function getConflictMessages( { postId, trigger, delay, siblings } ) {
	if ( ! Array.isArray( siblings ) || 0 === siblings.length ) {
		return [];
	}

	const messages = [];

	siblings.forEach( ( sibling ) => {
		if ( sibling.id === postId ) {
			return;
		}

		const sameMoment = sibling.trigger === trigger && ( 'exit' === trigger || sibling.delay === delay );

		if ( sameMoment ) {
			messages.push(
				sprintf(
					/* translators: %s: title of the other popup. */
					__( 'Same moment as "%s": the first one in the list order opens first, the other one then.', 'simple-popup' ),
					sibling.title
				)
			);
			return;
		}

		if ( TIME_BASED_TRIGGERS.includes( trigger ) && TIME_BASED_TRIGGERS.includes( sibling.trigger )
			&& Math.abs( delay - sibling.delay ) < GAP
		) {
			messages.push(
				sprintf(
					/* translators: 1: title of the other popup, 2: pause in seconds. */
					__( 'Less than %2$d s apart from "%1$s": whichever opens second will wait %2$d s after the first one closes.', 'simple-popup' ),
					sibling.title,
					GAP
				)
			);
		}
	} );

	return messages;
}

function PopupSettingsPanel() {
	const [ meta, setMeta ] = useEntityProp( 'postType', POST_TYPE, 'meta' );

	const { translations, siblings, previewUrl, isPublished } = useSelect( ( select ) => {
		const post = select( 'core/editor' ).getCurrentPost();

		return {
			translations: post && Array.isArray( post.simple_popup_translations )
				? post.simple_popup_translations
				: [],
			siblings: post && Array.isArray( post.simple_popup_siblings )
				? post.simple_popup_siblings
				: [],
			previewUrl: post ? post.simple_popup_preview_url || '' : '',
			isPublished: post ? 'publish' === post.status : false,
		};
	}, [] );

	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );

	if ( ! meta ) {
		return null;
	}

	const active = !! meta._simple_popup_active;
	const trigger = meta._simple_popup_trigger || 'gesture';
	const delay = meta._simple_popup_delay ?? 4;
	const frequency = meta._simple_popup_frequency || 'visit';
	const frequencyValue = meta._simple_popup_frequency_value ?? 30;
	const conversionSelector = meta._simple_popup_conversion_selector || '';

	const updateMeta = ( changes ) => setMeta( { ...meta, ...changes } );

	const otherLanguages = translations.filter( ( item ) => ! item.is_current );
	const conflictMessages = active
		? getConflictMessages( { postId, trigger, delay, siblings } )
		: [];

	return (
		<PluginDocumentSettingPanel
			name="simple-popup-settings"
			title={ __( 'Popup settings', 'simple-popup' ) }
		>
			<ToggleControl
				label={ __( 'Active', 'simple-popup' ) }
				help={ __(
					'Only a published and active popup is displayed on the front end.',
					'simple-popup'
				) }
				checked={ active }
				onChange={ ( value ) => updateMeta( { _simple_popup_active: value } ) }
			/>

			{ isPublished && previewUrl && (
				<PanelRow>
					<ExternalLink href={ previewUrl }>
						{ __( 'Test this popup', 'simple-popup' ) }
					</ExternalLink>
				</PanelRow>
			) }

			<PanelRow>
				<strong>{ __( 'Shared by all languages', 'simple-popup' ) }</strong>
			</PanelRow>

			<p className="simple-popup-summary">
				{ describePopup( trigger, delay, frequency, frequencyValue ) }
			</p>

			{ conflictMessages.map( ( message, index ) => (
				<Notice key={ index } status="warning" isDismissible={ false }>
					{ message }
				</Notice>
			) ) }

			<SelectControl
				label={ __( 'Trigger', 'simple-popup' ) }
				value={ trigger }
				options={ [
					{ label: __( 'First gesture', 'simple-popup' ), value: 'gesture' },
					{ label: __( 'Page load', 'simple-popup' ), value: 'load' },
					{ label: __( 'Exit intent', 'simple-popup' ), value: 'exit' },
				] }
				onChange={ ( value ) => updateMeta( { _simple_popup_trigger: value } ) }
			/>

			{ 'exit' !== trigger && (
				<TextControl
					type="number"
					min="0"
					label={ __( 'Delay (seconds)', 'simple-popup' ) }
					value={ delay }
					onChange={ ( value ) =>
						updateMeta( { _simple_popup_delay: parseInt( value, 10 ) || 0 } )
					}
				/>
			) }

			<SelectControl
				label={ __( 'Frequency', 'simple-popup' ) }
				value={ frequency }
				options={ [
					{ label: __( 'Once per visit', 'simple-popup' ), value: 'visit' },
					{ label: __( 'Every N hours', 'simple-popup' ), value: 'hours' },
					{ label: __( 'Once', 'simple-popup' ), value: 'once' },
				] }
				onChange={ ( value ) => updateMeta( { _simple_popup_frequency: value } ) }
			/>

			{ 'once' !== frequency && (
				<TextControl
					type="number"
					min="1"
					label={
						'hours' === frequency
							? __( 'Hours between displays', 'simple-popup' )
							: __( 'Inactivity minutes before a new visit', 'simple-popup' )
					}
					value={ frequencyValue }
					onChange={ ( value ) =>
						updateMeta( { _simple_popup_frequency_value: parseInt( value, 10 ) || 0 } )
					}
				/>
			) }

			<TextControl
				label={ __( 'Conversion selector (optional)', 'simple-popup' ) }
				help={ __(
					'CSS selector: once it appears inside the popup, the popup is never shown again to that visitor.',
					'simple-popup'
				) }
				value={ conversionSelector }
				onChange={ ( value ) => updateMeta( { _simple_popup_conversion_selector: value } ) }
			/>

			{ otherLanguages.length > 0 && (
				<PanelRow>
					<div className="simple-popup-languages">
						<strong>{ __( 'Languages', 'simple-popup' ) }</strong>
						<ul>
							{ otherLanguages.map( ( item ) => (
								<li key={ item.slug }>
									{ item.exists ? (
										<a href={ item.url }>
											{ item.name }
											{ ': ' }
											{ item.active
												? __( 'Active', 'simple-popup' )
												: __( 'Inactive', 'simple-popup' ) }
										</a>
									) : (
										<a href={ item.url }>
											{ item.name } { __( '+ Add', 'simple-popup' ) }
										</a>
									) }
								</li>
							) ) }
						</ul>
					</div>
				</PanelRow>
			) }
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'simple-popup', { render: PopupSettingsPanel } );

import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { ToggleControl, SelectControl, TextControl, PanelRow } from '@wordpress/components';
import { PluginDocumentSettingPanel as EditorPanel } from '@wordpress/editor';
import { PluginDocumentSettingPanel as EditPostPanel } from '@wordpress/edit-post';

// `@wordpress/editor` only started exporting PluginDocumentSettingPanel in
// WordPress 6.6; `@wordpress/edit-post` keeps exporting it (deprecated) for
// older sites, so fall back to it when the former isn't available yet.
const PluginDocumentSettingPanel = EditorPanel || EditPostPanel;

const POST_TYPE = 'simple_popup';

function PopupSettingsPanel() {
	const [ meta, setMeta ] = useEntityProp( 'postType', POST_TYPE, 'meta' );

	const translations = useSelect( ( select ) => {
		const post = select( 'core/editor' ).getCurrentPost();
		return post && Array.isArray( post.simple_popup_translations )
			? post.simple_popup_translations
			: [];
	}, [] );

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

			<PanelRow>
				<strong>{ __( 'Shared by all languages', 'simple-popup' ) }</strong>
			</PanelRow>

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
					min="0"
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

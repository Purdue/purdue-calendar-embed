import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	RangeControl,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';
import icon from './icon';

const MODES = [
	{ label: __( 'RSS feed', 'purdue-calendar-embed' ), value: 'feed' },
	{ label: __( 'Localist widget embed', 'purdue-calendar-embed' ), value: 'widget' },
];

const EXPERIENCES = [
	{ label: __( 'Any', 'purdue-calendar-embed' ), value: '' },
	{ label: __( 'In person', 'purdue-calendar-embed' ), value: 'inperson' },
	{ label: __( 'Hybrid', 'purdue-calendar-embed' ), value: 'hybrid' },
	{ label: __( 'Virtual', 'purdue-calendar-embed' ), value: 'virtual' },
];

const LAYOUTS = [
	{ label: __( 'List', 'purdue-calendar-embed' ), value: 'list' },
	{ label: __( 'Cards', 'purdue-calendar-embed' ), value: 'cards' },
];

export default function Edit( { attributes, setAttributes } ) {
	const {
		mode,
		feedUrl,
		eventTypes,
		experience,
		count,
		windowDays,
		hidePast,
		layout,
		showImage,
		showDescription,
		showCategory,
		widgetSnippet,
	} = attributes;

	const blockProps = useBlockProps();
	const isWidget = mode === 'widget';

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Source', 'purdue-calendar-embed' ) }>
					<SelectControl
						label={ __( 'Mode', 'purdue-calendar-embed' ) }
						value={ mode }
						options={ MODES }
						onChange={ ( value ) => setAttributes( { mode: value } ) }
						help={ __(
							'Widget mode embeds Localist directly. Use it when you need day or result limits, which the RSS feed ignores.',
							'purdue-calendar-embed'
						) }
						__nextHasNoMarginBottom
					/>

					{ isWidget && (
						<TextareaControl
							label={ __( 'Widget embed snippet', 'purdue-calendar-embed' ) }
							value={ widgetSnippet }
							onChange={ ( value ) => setAttributes( { widgetSnippet: value } ) }
							help={ __(
								'Paste the snippet from the Localist widget builder. Only events.purdue.edu and localist.com embeds are accepted.',
								'purdue-calendar-embed'
							) }
							rows={ 6 }
							__nextHasNoMarginBottom
						/>
					) }

					{ ! isWidget && (
						<>
							<TextControl
								label={ __( 'Feed URL', 'purdue-calendar-embed' ) }
								value={ feedUrl }
								onChange={ ( value ) => setAttributes( { feedUrl: value } ) }
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={ __( 'Event type IDs', 'purdue-calendar-embed' ) }
								value={ eventTypes }
								onChange={ ( value ) => setAttributes( { eventTypes: value } ) }
								help={ __(
									'Comma-separated numeric Localist event type IDs, e.g. 39882150467792 for Sporting Events.',
									'purdue-calendar-embed'
								) }
								__nextHasNoMarginBottom
							/>
							<SelectControl
								label={ __( 'Experience', 'purdue-calendar-embed' ) }
								value={ experience }
								options={ EXPERIENCES }
								onChange={ ( value ) => setAttributes( { experience: value } ) }
								__nextHasNoMarginBottom
							/>
						</>
					) }
				</PanelBody>

				{ ! isWidget && (
					<>
						<PanelBody
							title={ __( 'Filtering', 'purdue-calendar-embed' ) }
							initialOpen={ false }
						>
							<RangeControl
								label={ __( 'Number of events', 'purdue-calendar-embed' ) }
								value={ count }
								onChange={ ( value ) => setAttributes( { count: value } ) }
								min={ 1 }
								max={ 50 }
								__nextHasNoMarginBottom
							/>
							<RangeControl
								label={ __( 'Days ahead', 'purdue-calendar-embed' ) }
								value={ windowDays }
								onChange={ ( value ) => setAttributes( { windowDays: value } ) }
								min={ 1 }
								max={ 365 }
								help={ __(
									'Applied after fetching — the Localist feed always returns roughly eleven months.',
									'purdue-calendar-embed'
								) }
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __( 'Hide past events', 'purdue-calendar-embed' ) }
								checked={ hidePast }
								onChange={ ( value ) => setAttributes( { hidePast: value } ) }
								__nextHasNoMarginBottom
							/>
						</PanelBody>

						<PanelBody
							title={ __( 'Display', 'purdue-calendar-embed' ) }
							initialOpen={ false }
						>
							<SelectControl
								label={ __( 'Layout', 'purdue-calendar-embed' ) }
								value={ layout }
								options={ LAYOUTS }
								onChange={ ( value ) => setAttributes( { layout: value } ) }
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __( 'Show image', 'purdue-calendar-embed' ) }
								checked={ showImage }
								onChange={ ( value ) => setAttributes( { showImage: value } ) }
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __( 'Show description', 'purdue-calendar-embed' ) }
								checked={ showDescription }
								onChange={ ( value ) => setAttributes( { showDescription: value } ) }
								__nextHasNoMarginBottom
							/>
							<ToggleControl
								label={ __( 'Show category', 'purdue-calendar-embed' ) }
								checked={ showCategory }
								onChange={ ( value ) => setAttributes( { showCategory: value } ) }
								__nextHasNoMarginBottom
							/>
						</PanelBody>
					</>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				{ isWidget ? (
					<Placeholder
						icon={ icon }
						label={ __( 'Localist widget', 'purdue-calendar-embed' ) }
						instructions={
							widgetSnippet
								? __(
										'The Localist widget renders on the front end. It is not previewed here so its script does not run in the editor.',
										'purdue-calendar-embed'
								  )
								: __(
										'Paste a Localist widget embed snippet in the block settings.',
										'purdue-calendar-embed'
								  )
						}
					/>
				) : (
					<ServerSideRender block={ metadata.name } attributes={ attributes } />
				) }
			</div>
		</>
	);
}

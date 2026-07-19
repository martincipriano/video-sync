(function (wp) {
	'use strict'

	var el                = wp.element.createElement
	var __                = wp.i18n.__
	var Fragment           = wp.element.Fragment
	var useSelect          = wp.data.useSelect
	var useBlockProps      = wp.blockEditor.useBlockProps
	var InspectorControls  = wp.blockEditor.InspectorControls
	var PanelBody          = wp.components.PanelBody
	var SelectControl      = wp.components.SelectControl
	var Placeholder        = wp.components.Placeholder
	var ServerSideRender   = wp.serverSideRender

	var FIELD_OPTIONS = [
		{ label: __( '— Video —', 'buoy-video-sync' ),    value: '__video_group', disabled: true },
		{ label: __( 'Thumbnail', 'buoy-video-sync' ),    value: 'thumbnail' },
		{ label: __( '— Playlist —', 'buoy-video-sync' ), value: '__playlist_group', disabled: true },
		{ label: __( 'Thumbnail', 'buoy-video-sync' ),    value: 'playlist_thumbnail' },
		{ label: __( '— Channel —', 'buoy-video-sync' ),  value: '__channel_group', disabled: true },
		{ label: __( 'Profile Photo', 'buoy-video-sync' ), value: 'profile_photo' },
		{ label: __( 'Banner Image', 'buoy-video-sync' ),  value: 'banner_image' },
	]

	var SIZE_OPTIONS = [
		{ label: __( 'Max Res (1280×720)', 'buoy-video-sync' ), value: 'maxres' },
		{ label: __( 'Standard (640×480)', 'buoy-video-sync' ), value: 'standard' },
		{ label: __( 'High (480×360)', 'buoy-video-sync' ),     value: 'high' },
		{ label: __( 'Medium (320×180)', 'buoy-video-sync' ),   value: 'medium' },
		{ label: __( 'Default (120×90)', 'buoy-video-sync' ),   value: 'default' },
	]

	function ImageEdit(props) {
		var attributes    = props.attributes
		var setAttributes = props.setAttributes
		var field         = attributes.field
		var size          = attributes.size
		var blockProps    = useBlockProps()

		var currentPostId = useSelect(function (select) {
			return select('core/editor') ? select('core/editor').getCurrentPostId() : 0
		}, [])

		var previewAttrs = Object.assign({}, attributes, { postId: attributes.postId || currentPostId })
		var isConfigured  = field && field.indexOf('__') !== 0

		return el(Fragment, null,
			el(InspectorControls, null,
				el(PanelBody, { title: __( 'Image Settings', 'buoy-video-sync' ), initialOpen: true },
					el(SelectControl, {
						label:    __( 'Image', 'buoy-video-sync' ),
						value:    field,
						options:  FIELD_OPTIONS,
						onChange: function (val) { setAttributes({ field: val }) },
					}),
					field === 'thumbnail' && el(SelectControl, {
						label:    __( 'Size', 'buoy-video-sync' ),
						value:    size,
						options:  SIZE_OPTIONS,
						onChange: function (val) { setAttributes({ size: val }) },
					})
				)
			),
			el('div', blockProps,
				isConfigured && currentPostId
					? el(ServerSideRender, { block: 'buoy-video-sync/image', attributes: previewAttrs })
					: el(Placeholder, {
						icon:         'format-image',
						label:        __( 'Video Sync Image', 'buoy-video-sync' ),
						instructions: __( 'Select an image type in the block settings panel.', 'buoy-video-sync' ),
					})
			)
		)
	}

	var blockIcon = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 -960 960 960', width: 24, height: 24, fill: 'currentColor' },
		el('path', { d: 'M200-320h400L462-500l-92 120-62-80zm-40 160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h480q33 0 56.5 23.5T720-720v180l160-160v440L720-420v180q0 33-23.5 56.5T640-160zm0-80h480v-480H160zm0 0v-480z' })
	)

	wp.blocks.registerBlockType('buoy-video-sync/image', {
		icon: blockIcon,
		edit: ImageEdit,
		save: function () { return null },
	})

})(window.wp)

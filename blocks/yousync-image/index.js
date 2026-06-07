(function (wp) {
	'use strict'

	var el               = wp.element.createElement
	var __               = wp.i18n.__
	var Fragment         = wp.element.Fragment
	var useSelect        = wp.data.useSelect
	var useBlockProps    = wp.blockEditor.useBlockProps
	var InspectorControls = wp.blockEditor.InspectorControls
	var PanelBody        = wp.components.PanelBody
	var SelectControl    = wp.components.SelectControl
	var Placeholder      = wp.components.Placeholder
	var ServerSideRender = wp.serverSideRender

	var FIELD_OPTIONS = [
		{ label: __( '— Video thumbnails —', 'yousync-pro' ), value: '__video_group', disabled: true },
		{ label: __( 'Thumbnail', 'yousync-pro' ),            value: 'thumbnail' },
		{ label: __( '— Channel images —', 'yousync-pro' ),   value: '__channel_group', disabled: true },
		{ label: __( 'Profile Photo', 'yousync-pro' ),        value: 'profile_photo' },
		{ label: __( 'Banner Image', 'yousync-pro' ),         value: 'banner_image' },
	]

	var SIZE_OPTIONS = [
		{ label: __( 'Max Res (1280×720)', 'yousync-pro' ), value: 'maxres' },
		{ label: __( 'Standard (640×480)', 'yousync-pro' ), value: 'standard' },
		{ label: __( 'High (480×360)', 'yousync-pro' ),     value: 'high' },
		{ label: __( 'Medium (320×180)', 'yousync-pro' ),   value: 'medium' },
		{ label: __( 'Default (120×90)', 'yousync-pro' ),   value: 'default' },
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
		var isConfigured = field && field.indexOf('__') !== 0

		return el(Fragment, null,
			el(InspectorControls, null,
				el(PanelBody, { title: __( 'Image Settings', 'yousync-pro' ), initialOpen: true },
					el(SelectControl, {
						label:    __( 'Image', 'yousync-pro' ),
						value:    field,
						options:  FIELD_OPTIONS,
						onChange: function (val) { setAttributes({ field: val }) },
					}),
					field === 'thumbnail' && el(SelectControl, {
						label:    __( 'Size', 'yousync-pro' ),
						value:    size,
						options:  SIZE_OPTIONS,
						onChange: function (val) { setAttributes({ size: val }) },
					})
				)
			),
			el('div', blockProps,
				isConfigured && currentPostId
					? el(ServerSideRender, { block: 'yousync/image', attributes: previewAttrs })
					: el(Placeholder, {
						icon:         'format-image',
						label:        __( 'YouSync Image', 'yousync-pro' ),
						instructions: __( 'Select an image type in the block settings panel.', 'yousync-pro' ),
					})
			)
		)
	}

	wp.blocks.registerBlockType('yousync/image', {
		edit: ImageEdit,
		save: function () { return null },
	})

})(window.wp)

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
		{ label: __( '— Select a field —', 'yousync-pro' ), value: '' },
		{ label: __( '— Video fields —', 'yousync-pro' ), value: '__video_group', disabled: true },
		{ label: __( 'Title', 'yousync-pro' ),          value: 'title' },
		{ label: __( 'Description', 'yousync-pro' ),    value: 'description' },
		{ label: __( 'Channel', 'yousync-pro' ),        value: 'channel' },
		{ label: __( 'Published Date', 'yousync-pro' ), value: 'published_date' },
		{ label: __( 'Duration', 'yousync-pro' ),       value: 'duration' },
		{ label: __( 'View Count', 'yousync-pro' ),     value: 'view_count' },
		{ label: __( 'Like Count', 'yousync-pro' ),     value: 'like_count' },
		{ label: __( 'Comment Count', 'yousync-pro' ),  value: 'comment_count' },
		{ label: __( 'Video URL', 'yousync-pro' ),      value: 'video_url' },
		{ label: __( 'Embed Code', 'yousync-pro' ),     value: 'embed_code' },
		{ label: __( '— Channel fields —', 'yousync-pro' ), value: '__channel_group', disabled: true },
		{ label: __( 'Channel Title', 'yousync-pro' ),       value: 'channel_title' },
		{ label: __( 'Channel Description', 'yousync-pro' ), value: 'channel_description' },
		{ label: __( 'Subscribers', 'yousync-pro' ),         value: 'subscriber_count' },
		{ label: __( 'Video Count', 'yousync-pro' ),         value: 'video_count' },
	]

	function FieldEdit(props) {
		var attributes    = props.attributes
		var setAttributes = props.setAttributes
		var field         = attributes.field
		var blockProps    = useBlockProps()

		var currentPostId = useSelect(function (select) {
			return select('core/editor') ? select('core/editor').getCurrentPostId() : 0
		}, [])

		var previewAttrs = Object.assign({}, attributes, { postId: attributes.postId || currentPostId })
		var isConfigured = field && field.indexOf('__') !== 0

		return el(Fragment, null,
			el(InspectorControls, null,
				el(PanelBody, { title: __( 'Field Settings', 'yousync-pro' ), initialOpen: true },
					el(SelectControl, {
						label:    __( 'Field', 'yousync-pro' ),
						value:    field,
						options:  FIELD_OPTIONS,
						onChange: function (val) { setAttributes({ field: val }) },
					})
				)
			),
			el('div', blockProps,
				isConfigured && currentPostId
					? el(ServerSideRender, { block: 'yousync/field', attributes: previewAttrs })
					: el(Placeholder, {
						icon:         'video-alt3',
						label:        __( 'YouSync Field', 'yousync-pro' ),
						instructions: __( 'Select a field in the block settings panel.', 'yousync-pro' ),
					})
			)
		)
	}

	wp.blocks.registerBlockType('yousync/field', {
		edit: FieldEdit,
		save: function () { return null },
	})

})(window.wp)

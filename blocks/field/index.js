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
		{ label: __( '— Select a field —', 'buoy-video-sync' ), value: '' },
		{ label: __( '— Common —', 'buoy-video-sync' ), value: '__common_group', disabled: true },
		{ label: __( 'Title', 'buoy-video-sync' ),       value: 'title' },
		{ label: __( 'Description', 'buoy-video-sync' ), value: 'description' },
		{ label: __( '— Video fields —', 'buoy-video-sync' ), value: '__video_group', disabled: true },
		{ label: __( 'Video ID', 'buoy-video-sync' ),        value: 'video_id' },
		{ label: __( 'Video URL', 'buoy-video-sync' ),       value: 'video_url' },
		{ label: __( 'Embed Code', 'buoy-video-sync' ),      value: 'embed_code' },
		{ label: __( 'Channel Name', 'buoy-video-sync' ),    value: 'channel' },
		{ label: __( 'Published Date', 'buoy-video-sync' ),  value: 'published_date' },
		{ label: __( 'Duration', 'buoy-video-sync' ),        value: 'duration' },
		{ label: __( 'View Count', 'buoy-video-sync' ),      value: 'view_count' },
		{ label: __( 'Like Count', 'buoy-video-sync' ),      value: 'like_count' },
		{ label: __( 'Comment Count', 'buoy-video-sync' ),   value: 'comment_count' },
		{ label: __( '— Playlist fields —', 'buoy-video-sync' ), value: '__playlist_group', disabled: true },
		{ label: __( 'Playlist ID', 'buoy-video-sync' ),        value: 'playlist_id' },
		{ label: __( 'Video Count', 'buoy-video-sync' ),        value: 'playlist_video_count' },
		{ label: __( '— Channel fields —', 'buoy-video-sync' ), value: '__channel_group', disabled: true },
		{ label: __( 'Subscriber Count', 'buoy-video-sync' ), value: 'subscriber_count' },
		{ label: __( 'Video Count', 'buoy-video-sync' ),      value: 'video_count' },
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
		var isConfigured  = field && field.indexOf('__') !== 0

		return el(Fragment, null,
			el(InspectorControls, null,
				el(PanelBody, { title: __( 'Field Settings', 'buoy-video-sync' ), initialOpen: true },
					el(SelectControl, {
						label:    __( 'Field', 'buoy-video-sync' ),
						value:    field,
						options:  FIELD_OPTIONS,
						onChange: function (val) { setAttributes({ field: val }) },
					})
				)
			),
			el('div', blockProps,
				isConfigured && currentPostId
					? el(ServerSideRender, { block: 'buoy-video-sync/field', attributes: previewAttrs })
					: el(Placeholder, {
						icon:         'video-alt3',
						label:        __( 'Video Sync Field', 'buoy-video-sync' ),
						instructions: __( 'Select a field in the block settings panel.', 'buoy-video-sync' ),
					})
			)
		)
	}

	var blockIcon = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 21 17', width: 24, height: 24, fill: 'currentColor' },
		el('path', { fillRule: 'nonzero', d: 'M15 16h-4.325q.325-.45.563-.95T11.65 14H15V2H3v3.275q-.55.15-1.05.387a6 6 0 0 0-.95.563V2q0-.824.587-1.412A1.93 1.93 0 0 1 3 0h12q.824 0 1.413.588Q17 1.175 17 2v4.5l4-4v11l-4-4V14q0 .825-.587 1.412A1.93 1.93 0 0 1 15 16m-13.537-.463Q0 14.075 0 12q0-2.075 1.463-3.537Q2.925 7 5 7t3.537 1.463T10 12t-1.463 3.537Q7.076 17 5 17t-3.537-1.463M5 10.5q.4 0 .7-.3t.3-.7-.3-.7a.96.96 0 0 0-.7-.3q-.4 0-.7.3t-.3.7.3.7.7.3m-1 5h2v-4H4z' })
	)

	wp.blocks.registerBlockType('buoy-video-sync/field', {
		icon: blockIcon,
		edit: FieldEdit,
		save: function () { return null },
	})

})(window.wp)

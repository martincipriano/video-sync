(function (wp) {
	'use strict'

	var el            = wp.element.createElement
	var __            = wp.i18n.__
	var useSelect     = wp.data.useSelect
	var useBlockProps = wp.blockEditor.useBlockProps
	var Placeholder   = wp.components.Placeholder
	var ServerSideRender = wp.serverSideRender

	function EmbedEdit(props) {
		var attributes    = props.attributes
		var blockProps    = useBlockProps()

		var currentPostId = useSelect(function (select) {
			return select('core/editor') ? select('core/editor').getCurrentPostId() : 0
		}, [])

		var previewAttrs = Object.assign({}, attributes, { postId: attributes.postId || currentPostId, isEditorPreview: true })

		return el('div', blockProps,
			currentPostId
				? el(ServerSideRender, { block: 'wby-video-sync/embed', attributes: previewAttrs })
				: el(Placeholder, {
					icon:         'video-alt3',
					label:        __( 'Video Sync Embed', 'wby-video-sync' ),
					instructions: __( 'This block embeds the current post\'s YouTube video.', 'wby-video-sync' ),
				})
		)
	}

	var blockIcon = el('svg', { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 -960 960 960', width: 24, height: 24, fill: 'currentColor' },
		el('path', { d: 'M360-320h80v-120h120v-80H440v-120h-80v120H240v80h120zM160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h480q33 0 56.5 23.5T720-720v180l160-160v440L720-420v180q0 33-23.5 56.5T640-160zm0-80h480v-480H160zm0 0v-480z' })
	)

	wp.blocks.registerBlockType('wby-video-sync/embed', {
		icon: blockIcon,
		edit: EmbedEdit,
		save: function () { return null },
	})

})(window.wp)

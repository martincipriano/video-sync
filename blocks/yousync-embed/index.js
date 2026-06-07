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

		var previewAttrs = Object.assign({}, attributes, { postId: attributes.postId || currentPostId })

		return el('div', blockProps,
			currentPostId
				? el(ServerSideRender, { block: 'yousync/embed', attributes: previewAttrs })
				: el(Placeholder, {
					icon:         'video-alt3',
					label:        __( 'YouSync Embed', 'yousync-pro' ),
					instructions: __( 'This block embeds the current post\'s YouTube video.', 'yousync-pro' ),
				})
		)
	}

	wp.blocks.registerBlockType('yousync/embed', {
		edit: EmbedEdit,
		save: function () { return null },
	})

})(window.wp)

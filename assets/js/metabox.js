/**
 * Video metabox — copy embed code button.
 */
const copyBtn = document.querySelector( '.ys-copy-embed-btn' )

if ( copyBtn ) {
	copyBtn.addEventListener( 'click', function () {
		const btn   = this
		const input = this.previousElementSibling

		navigator.clipboard.writeText( input.value ).catch( function () {
			input.select()
			document.execCommand( 'copy' )
		} )

		btn.classList.add( 'ys-copied' )
		setTimeout( function () {
			btn.classList.remove( 'ys-copied' )
		}, 1500 )
	} )
}

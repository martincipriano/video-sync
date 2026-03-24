/**
 * Video metabox — tab switching.
 */
const tabs   = document.querySelectorAll( '.yousync-mb-tab' )
const panels = document.querySelectorAll( '.yousync-mb-panel' )

tabs.forEach( function ( tab ) {
	tab.addEventListener( 'click', function ( e ) {
		e.preventDefault()
		const target = this.dataset.tab
		tabs.forEach( function ( t ) { t.classList.remove( 'nav-tab-active' ) } )
		panels.forEach( function ( p ) { p.style.display = 'none' } )
		this.classList.add( 'nav-tab-active' )
		document.getElementById( 'yousync-panel-' + target ).style.display = 'block'
	} )
} )

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

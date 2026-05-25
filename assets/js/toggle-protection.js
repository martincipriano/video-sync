/**
 * Toggle "Protect from Sync" via AJAX on the video list screen.
 *
 * Expects `ysProtect.nonce` and `ysProtect.action` to be localised
 * via wp_localize_script() from the server.
 *
 * @package YouSync
 */
document.addEventListener( 'change', function( e ) {
	var cb = e.target;
	if ( ! cb.classList.contains( 'ys-protect-checkbox' ) ) return;
	var label  = cb.closest( '.ys-protect-toggle' );
	var postId = label.dataset.postId;
	var fd     = new FormData();
	fd.append( 'action',    ysProtect.action );
	fd.append( 'post_id',   postId );
	fd.append( 'protected', cb.checked ? '1' : '0' );
	fd.append( 'nonce',     ysProtect.nonce );
	label.style.opacity = '0.5';
	fetch( ajaxurl, { method: 'POST', body: fd } )
		.then( function( r ) { return r.json(); } )
		.then( function( res ) {
			label.style.opacity = '1';
			if ( ! res.success ) { cb.checked = ! cb.checked; }
		} )
		.catch( function() {
			label.style.opacity = '1';
			cb.checked = ! cb.checked;
		} );
} );

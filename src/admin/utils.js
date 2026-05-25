/**
 * Admin POST helpers.
 */

export function postAdminAction( action, fields, nonce ) {
	const body = new FormData();
	body.append( 'action', 'pulse_press_action' );
	body.append( 'pulse_press_action', action );
	body.append( '_wpnonce', nonce );

	const tab = new URLSearchParams( window.location.search ).get( 'tab' );
	if ( tab ) {
		body.append( 'pulse_press_tab', tab );
	}

	Object.entries( fields ).forEach( ( [ key, value ] ) => {
		if ( Array.isArray( value ) ) {
			value.forEach( ( item ) => body.append( `${ key }[]`, item ) );
		} else if ( value !== undefined && value !== null ) {
			body.append( key, value );
		}
	} );

	const form = document.createElement( 'form' );
	form.method = 'POST';
	form.action = window.pulsePressAdmin?.adminPostUrl || '/wp-admin/admin-post.php';
	body.forEach( ( val, key ) => {
		const input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = key;
		input.value = val;
		form.appendChild( input );
	} );
	document.body.appendChild( form );
	form.submit();
}

export function tabUrl( tab ) {
	const url = new URL( window.location.href );
	url.searchParams.set( 'tab', tab );
	return url.toString();
}

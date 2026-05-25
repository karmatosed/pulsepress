/**
 * Pulse Press admin entry.
 */
import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

const root = document.getElementById( 'pulse-press-root' );
if ( root ) {
	createRoot( root ).render( <App /> );
}

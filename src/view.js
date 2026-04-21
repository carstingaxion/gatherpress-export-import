
/**
 * Front-end interactivity for the GatherPress Event Migration Guide block.
 * Enables collapsible/expandable sections.
 */

document.addEventListener( 'DOMContentLoaded', function () {
	const blocks = document.querySelectorAll( '.wp-block-telex-block-telex-gatherpress-migration' );

	blocks.forEach( function ( block ) {
		const sectionTitles = block.querySelectorAll( '.gp-migration-guide__section-title' );

		sectionTitles.forEach( function ( title ) {
			title.style.cursor = 'pointer';
			title.setAttribute( 'role', 'button' );
			title.setAttribute( 'tabindex', '0' );
			title.setAttribute( 'aria-expanded', 'true' );

			var indicator = document.createElement( 'span' );
			indicator.className = 'gp-migration-guide__toggle';
			indicator.textContent = '▾';
			indicator.style.marginLeft = 'auto';
			indicator.style.fontSize = '14px';
			indicator.style.transition = 'transform 0.2s ease';
			title.appendChild( indicator );

			title.addEventListener( 'click', function () {
				toggleSection( title, indicator );
			} );

			title.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					toggleSection( title, indicator );
				}
			} );
		} );
	} );

	function toggleSection( title, indicator ) {
		var section = title.parentElement;
		var content = section.querySelectorAll( ':scope > :not(.gp-migration-guide__section-title)' );
		var isExpanded = title.getAttribute( 'aria-expanded' ) === 'true';

		title.setAttribute( 'aria-expanded', String( ! isExpanded ) );
		indicator.style.transform = isExpanded ? 'rotate(-90deg)' : 'rotate(0deg)';

		content.forEach( function ( el ) {
			el.style.display = isExpanded ? 'none' : '';
		} );
	}
} );

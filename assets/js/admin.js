( function () {
	'use strict';

	const config = window.CCSVGSpriteManager;
	const form = document.getElementById( 'cc-svg-upload-form' );
	const fileInput = document.getElementById( 'cc-svg-files' );
	const submitButton = document.getElementById( 'cc-svg-upload-submit' );
	const status = document.getElementById( 'cc-svg-upload-status' );
	const toggleAllButton = document.getElementById( 'cc-svg-toggle-all' );
	const iconCheckboxes = Array.from( document.querySelectorAll( '.cc-svg-icon-grid input[type="checkbox"]' ) );

	if ( ! config || ! form || ! fileInput || ! submitButton || ! status ) {
		return;
	}

	function showStatus( message, type = 'info' ) {
		status.className = `notice notice-${ type } inline`;
		status.innerHTML = '';

		const paragraph = document.createElement( 'p' );
		paragraph.textContent = message;
		status.appendChild( paragraph );
	}

	async function isValidSvg( file ) {
		if ( ! file.name.toLowerCase().endsWith( '.svg' ) ) {
			return false;
		}

		try {
			const source = await file.text();
			const documentNode = new DOMParser().parseFromString( source, 'image/svg+xml' );
			return ! documentNode.querySelector( 'parsererror' ) &&
				'svg' === documentNode.documentElement.localName.toLowerCase();
		} catch ( error ) {
			return false;
		}
	}

	async function validateFiles( files ) {
		if ( files.length > config.maxFiles ) {
			throw new Error( config.tooManyFiles );
		}

		showStatus( config.validating );
		const results = await Promise.all( files.map( isValidSvg ) );
		const invalidIndex = results.findIndex( ( valid ) => ! valid );

		if ( -1 !== invalidIndex ) {
			throw new Error( `${ files[ invalidIndex ].name }: ${ config.invalidType }` );
		}
	}

	async function uploadBatch( files, dedupeMode ) {
		const body = new FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'dedupe_mode', dedupeMode );

		files.forEach( ( file ) => body.append( 'svg_files[]', file, file.name ) );

		const response = await fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body,
		} );
		const result = await response.json();

		if ( ! response.ok || ! result.success ) {
			throw new Error( result.data?.message || config.uploadFailed );
		}
	}

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const files = Array.from( fileInput.files );
		if ( 0 === files.length ) {
			return;
		}

		submitButton.disabled = true;

		try {
			await validateFiles( files );

			const dedupeMode = form.querySelector( 'input[name="dedupe_mode"]:checked' ).value;

			for ( let index = 0; index < files.length; index += config.batchSize ) {
				const end = Math.min( index + config.batchSize, files.length );
				showStatus( `${ config.uploading } (${ index + 1 }-${ end }/${ files.length })` );
				await uploadBatch( files.slice( index, end ), dedupeMode );
			}

			showStatus( config.uploadComplete, 'success' );
			window.setTimeout( () => window.location.reload(), 700 );
		} catch ( error ) {
			showStatus( error.message || config.uploadFailed, 'error' );
			submitButton.disabled = false;
		}
	} );

	function updateToggleAllLabel() {
		if ( ! toggleAllButton || 0 === iconCheckboxes.length ) {
			return;
		}

		const allSelected = iconCheckboxes.every( ( checkbox ) => checkbox.checked );
		toggleAllButton.textContent = allSelected ? config.deselectAll : config.selectAll;
	}

	if ( toggleAllButton && iconCheckboxes.length > 0 ) {
		toggleAllButton.addEventListener( 'click', () => {
			const shouldSelect = ! iconCheckboxes.every( ( checkbox ) => checkbox.checked );
			iconCheckboxes.forEach( ( checkbox ) => {
				checkbox.checked = shouldSelect;
			} );
			updateToggleAllLabel();
		} );

		iconCheckboxes.forEach( ( checkbox ) => {
			checkbox.addEventListener( 'change', updateToggleAllLabel );
		} );
		updateToggleAllLabel();
	}
}() );

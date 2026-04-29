/**
 * Registers a command palette entry for the ICS Event Importer.
 *
 * Uses a no-UI React component mounted via domReady() + createRoot()
 * so the command is available on all admin screens. The component
 * uses the useCommand hook for registration and useState for modal state.
 *
 * @package
 * @since   0.3.0
 */

import domReady from '@wordpress/dom-ready';
import { createRoot, useState, useCallback, useRef } from '@wordpress/element';
import { useCommand } from '@wordpress/commands';
import { __ } from '@wordpress/i18n';
import { calendar } from '@wordpress/icons';
import {
	Modal,
	Button,
	CheckboxControl,
	DropZone,
	Notice,
} from '@wordpress/components';

/* global DataTransfer */

/**
 * ICS Import Modal component.
 *
 * Renders a drag-and-drop file upload area with template block toggles
 * and submits the ICS file to the server for processing.
 *
 * @param {Object}   props
 * @param {Function} props.onClose Callback to close the modal.
 * @return {JSX.Element} The modal UI for ICS file import.
 */
function ICSImportModal( { onClose } ) {
	const [ file, setFile ] = useState( null );
	const [ publishImport, setPublishImport ] = useState( true );
	const [ includeTemplate, setIncludeTemplate ] = useState( true );
	const [ templateBefore, setTemplateBefore ] = useState( false );
	const [ isSubmitting, setIsSubmitting ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const fileInputRef = useRef( null );

	/**
	 * Handles file selection from both the input and drag-and-drop.
	 *
	 * @param {File} selectedFile The selected file.
	 */
	const handleFile = useCallback( ( selectedFile ) => {
		if (
			selectedFile &&
			( selectedFile.name.endsWith( '.ics' ) ||
				selectedFile.type === 'text/calendar' )
		) {
			setFile( selectedFile );
			setNotice( null );
		} else {
			setNotice( {
				status: 'error',
				message: __(
					'Please select a valid .ics file.',
					'gatherpress-export-import'
				),
			} );
		}
	}, [] );

	/**
	 * Handles the DropZone file drop event.
	 *
	 * @param {File[]} files Dropped files.
	 */
	const handleDrop = useCallback(
		( files ) => {
			if ( files && files.length > 0 ) {
				handleFile( files[ 0 ] );
			}
		},
		[ handleFile ]
	);

	/**
	 * Handles the file input change event.
	 *
	 * @param {Event} event The input change event.
	 */
	const handleInputChange = useCallback(
		( event ) => {
			if ( event.target.files && event.target.files.length > 0 ) {
				handleFile( event.target.files[ 0 ] );
			}
		},
		[ handleFile ]
	);

	/**
	 * Submits the ICS file to the server via a hidden form POST.
	 *
	 * We use a traditional form submission because the server-side handler
	 * expects a multipart/form-data POST with a nonce and redirects
	 * after processing. This matches the existing Tools page behaviour.
	 */
	const handleSubmit = useCallback( () => {
		if ( ! file ) {
			return;
		}

		setIsSubmitting( true );

		// Build a FormData object and submit via a hidden form.
		const form = document.createElement( 'form' );
		form.method = 'POST';
		form.enctype = 'multipart/form-data';
		form.action = window.gpeiIcsImporter?.toolsUrl || '/wp-admin/tools.php';
		form.style.display = 'none';

		// Nonce field.
		const nonceInput = document.createElement( 'input' );
		nonceInput.type = 'hidden';
		nonceInput.name = 'gpei_ics_nonce';
		nonceInput.value = window.gpeiIcsImporter?.nonce || '';
		form.appendChild( nonceInput );

		// Submit flag.
		const submitInput = document.createElement( 'input' );
		submitInput.type = 'hidden';
		submitInput.name = 'gpei_ics_submit';
		submitInput.value = '1';
		form.appendChild( submitInput );

		// Publish flag.
		if ( publishImport ) {
			const publishInput = document.createElement( 'input' );
			publishInput.type = 'hidden';
			publishInput.name = 'gpei_ics_publish';
			publishInput.value = '1';
			form.appendChild( publishInput );
		}

		// Include template.
		if ( includeTemplate ) {
			const templateInput = document.createElement( 'input' );
			templateInput.type = 'hidden';
			templateInput.name = 'gpei_ics_include_template';
			templateInput.value = '1';
			form.appendChild( templateInput );
		}

		// Template before.
		if ( includeTemplate && templateBefore ) {
			const beforeInput = document.createElement( 'input' );
			beforeInput.type = 'hidden';
			beforeInput.name = 'gpei_ics_template_before';
			beforeInput.value = '1';
			form.appendChild( beforeInput );
		}

		// File input — we need to use DataTransfer to set the file.
		const fileInput = document.createElement( 'input' );
		fileInput.type = 'file';
		fileInput.name = 'gpei_ics_file';
		const dt = new DataTransfer();
		dt.items.add( file );
		fileInput.files = dt.files;
		form.appendChild( fileInput );

		document.body.appendChild( form );
		form.submit();
	}, [ file, publishImport, includeTemplate, templateBefore ] );

	return (
		<Modal
			title={ __(
				'Import Events from ICS File',
				'gatherpress-export-import'
			) }
			onRequestClose={ onClose }
			size="medium"
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
					isDismissible
				>
					{ notice.message }
				</Notice>
			) }

			<p style={ { color: '#757575', fontSize: '13px', marginTop: 0 } }>
				{ __(
					'Upload an ICS calendar file to import events into GatherPress. All imported events will be created as drafts for review.',
					'gatherpress-export-import'
				) }
			</p>

			<div
				onClick={ () => fileInputRef.current?.click() }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						fileInputRef.current?.click();
					}
				} }
				role="button"
				tabIndex={ 0 }
				style={ {
					border: '2px dashed ' + ( file ? '#2271b1' : '#c3c4c7' ),
					borderRadius: '8px',
					padding: '32px 24px',
					textAlign: 'center',
					cursor: 'pointer',
					transition:
						'border-color 0.2s ease, background-color 0.2s ease',
					background: file ? '#f0f6fc' : '#f6f7f7',
					margin: '16px 0',
					position: 'relative',
				} }
			>
				<DropZone onFilesDrop={ handleDrop } />
				<div style={ { marginBottom: '8px' } }>
					<span
						className="dashicons dashicons-calendar-alt"
						style={ {
							fontSize: '36px',
							width: '36px',
							height: '36px',
							color: '#8c8f94',
						} }
					/>
				</div>
				<p
					style={ {
						margin: '0 0 4px',
						fontSize: '14px',
						fontWeight: 500,
						color: '#1d2327',
					} }
				>
					{ file
						? file.name
						: __(
								'Drag & drop your .ics file here',
								'gatherpress-export-import'
						  ) }
				</p>
				{ ! file && (
					<p
						style={ {
							margin: 0,
							color: '#8c8f94',
							fontSize: '13px',
						} }
					>
						{ __(
							'or click to browse',
							'gatherpress-export-import'
						) }
					</p>
				) }
				<input
					ref={ fileInputRef }
					type="file"
					accept=".ics,text/calendar"
					onChange={ handleInputChange }
					style={ { display: 'none' } }
				/>
			</div>

			<p style={ { marginTop: 0, fontSize: '12px', color: '#a7aaad' } }>
				{ __(
					'Accepted format: .ics (iCalendar). Exports from Google Calendar, Outlook, Apple Calendar, and Event Organiser are supported.',
					'gatherpress-export-import'
				) }
			</p>

			<fieldset
				style={ {
					margin: '16px 0',
					padding: '12px 16px',
					border: '1px solid #dcdcde',
					borderRadius: '4px',
					background: '#f6f7f7',
				} }
			>
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __(
						'Publish events and venues immediately',
						'gatherpress-export-import'
					) }
					checked={ publishImport }
					onChange={ setPublishImport }
					help={ __(
						'When disabled, imported events and venues are created as drafts for review.',
						'gatherpress-export-import'
					) }
				/>
			</fieldset>

			<fieldset
				style={ {
					margin: '16px 0',
					padding: '12px 16px',
					border: '1px solid #dcdcde',
					borderRadius: '4px',
					background: '#f6f7f7',
				} }
			>
				<CheckboxControl
					__nextHasNoMarginBottom
					label={ __(
						'Include registered template blocks for events and venues',
						'gatherpress-export-import'
					) }
					checked={ includeTemplate }
					onChange={ setIncludeTemplate }
					help={ __(
						'Inserts the default block template registered for gatherpress_event and gatherpress_venue post types into the created posts.',
						'gatherpress-export-import'
					) }
				/>
				{ includeTemplate && (
					<div style={ { marginTop: '12px', marginLeft: '28px' } }>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __(
								'Insert template blocks before imported content',
								'gatherpress-export-import'
							) }
							checked={ templateBefore }
							onChange={ setTemplateBefore }
							help={ __(
								'When enabled, template blocks appear before the imported description. When disabled, they appear after.',
								'gatherpress-export-import'
							) }
						/>
					</div>
				) }
			</fieldset>

			<div
				style={ {
					display: 'flex',
					justifyContent: 'flex-end',
					gap: '8px',
					marginTop: '16px',
				} }
			>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'gatherpress-export-import' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ handleSubmit }
					disabled={ ! file || isSubmitting }
					isBusy={ isSubmitting }
				>
					{ isSubmitting
						? __( 'Importing…', 'gatherpress-export-import' )
						: __( 'Import Events', 'gatherpress-export-import' ) }
				</Button>
			</div>
		</Modal>
	);
}

/**
 * No-UI React component that registers the command via useCommand
 * and manages the modal open/close state.
 *
 * Mounted via domReady() + createRoot() so the useCommand hook runs
 * inside a proper React tree with access to WordPress data stores.
 *
 * @return {JSX.Element|null} The import modal when open, or null.
 */
function GpeiIcsCommand() {
	const [ isOpen, setIsOpen ] = useState( false );

	useCommand( {
		name: 'gatherpress-export-import/import-ics',
		label: __( 'Import events from ICS file', 'gatherpress-export-import' ),
		icon: calendar,
		callback: () => setIsOpen( true ),
	} );

	if ( ! isOpen ) {
		return null;
	}

	return <ICSImportModal onClose={ () => setIsOpen( false ) } />;
}

domReady( () => {
	const container = document.createElement( 'div' );
	container.id = 'gpei-ics-command-root';
	document.body.appendChild( container );
	createRoot( container ).render( <GpeiIcsCommand /> );
} );

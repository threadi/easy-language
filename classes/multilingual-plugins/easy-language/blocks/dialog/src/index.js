import './style.scss';
import { Button, Modal } from '@wordpress/components';
import React, {useState} from 'react'

/**
 * Define the confirm dialog.
 *
 * @returns {JSX.Element}
 * @constructor
 */
function Confirm_Dialog( args ) {
	const [open, setOpen] = useState(true)

	/**
	 * Close action.
	 */
	const closeDialog = () => {
		confirm_dialog.unmount();
		confirm_dialog = null;
		setOpen( false )
	};

	/**
	 * Output rendered dialog, with title, texts and buttons configured by JSON.
	 *
	 * Prepared possible button-actions:
	 * closeDialog() => closes the dialog
	 */
	return (
		<div>
		{open &&
			<Modal
				bodyOpenClassName="easy-language-dialog-on-body"
				className="easy-language-dialog"
				isDismissible={false}
				onRequestClose={ closeDialog }
				title={args.dialog.title}
				shouldCloseOnClickOutside={false}
				shouldCloseOnEsc={false}
				__experimentalHideHeader={args.dialog.hide_title}
			>
				{args.dialog.texts && args.dialog.texts.map(function(text) {
							return (
								<div key={text} dangerouslySetInnerHTML={{__html: text}} className="easy-language-dialog-text" />
							)
						}
					)
				}
				{args.dialog.progressbar && args.dialog.progressbar.active && (
					<div
						className="easy-language-progressbar"
					>
						<progress max="100" id={args.dialog.progressbar.id} value={args.dialog.progressbar.progress}>&nbsp;</progress>
					</div>
				)}
				{args.dialog.buttons && args.dialog.buttons.map(function(button) {
						return (
							<Button key={button.text} variant={button.variant} onClick={ () => eval(button.action) }>
								{button.text}
							</Button>
						)
					}
				)
				}

			</Modal>
		}
		</div>
	);
}

/**
 * Show dialog, initiated by any event.
 *
 * If dialog already exist, it will be closed.
 *
 * @type {null}
 */
let confirm_dialog = null;
function add_dialog( dialog ) {
	if( dialog ) {
		if ( confirm_dialog ) {
			confirm_dialog.unmount();
			confirm_dialog = null;
		}
		if( ! document.getElementById('easy-language-dialog-root') ) {
			let root = document.createElement('div');
			root.id = 'easy-language-dialog-root';
			document.getElementById('wpfooter').append(root);
		}
		confirm_dialog = ReactDOM.createRoot(document.getElementById('easy-language-dialog-root'));
		confirm_dialog.render(
			<Confirm_Dialog dialog={dialog}/>
		);
	}
}

/**
 * Add events where the dialog should be fired.
 */
document.addEventListener( 'DOMContentLoaded', () => {
	// add listener which could be used to trigger the dialog with given configuration.
	document.body.addEventListener('easy-language-dialog', function(attr) {
		if( attr.detail ) {
			add_dialog(attr.detail);
		}
	});

	// on each element with the class "easy-language-dialog".
	let simplify_links = document.getElementsByClassName('easy-language-dialog');
	for( let i=0;i<simplify_links.length;i++ ) {
		simplify_links[i].onclick = function(e) {
			e.preventDefault();
			document.body.dispatchEvent(new CustomEvent("easy-language-dialog", { detail: JSON.parse(this.dataset.dialog) }));
		};
	}
})


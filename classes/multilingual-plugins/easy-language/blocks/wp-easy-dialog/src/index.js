/**
 * File to handle easy dialog for WordPress.
 *
 * @package wp-easy-dialog
 */
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

	let classNames = "wp-easy-dialog";
	if( args.dialog.className ) {
		classNames = "wp-easy-dialog " + args.dialog.className;
	}

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
				bodyOpenClassName="wp-easy-dialog-on-body"
				className={classNames}
				isDismissible={false}
				onRequestClose={ closeDialog }
				title={args.dialog.title}
				shouldCloseOnClickOutside={false}
				shouldCloseOnEsc={false}
				__experimentalHideHeader={args.dialog.hide_title}
			>
				{args.dialog.texts && args.dialog.texts.map(function(text) {
							return (
								<div key={text} dangerouslySetInnerHTML={{__html: text}} className="wp-easy-dialog-text" />
							)
						}
					)
				}
				{args.dialog.progressbar && args.dialog.progressbar.active && (
					<div
						className="wp-progressbar"
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
		if( ! top.document.getElementById('wp-easy-dialog-root') ) {
			let root = top.document.createElement('div');
			root.id = 'wp-easy-dialog-root';
			top.document.body.append(root);
		}
		confirm_dialog = ReactDOM.createRoot(document.getElementById('wp-easy-dialog-root'));
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
	document.body.addEventListener('wp-easy-dialog', function(attr) {
		if( attr.detail ) {
			add_dialog(attr.detail);
		}
	});

	// on each element with the class "wp-easy-dialog".
	let simplify_links = document.getElementsByClassName('wp-easy-dialog');
	for( let i=0;i<simplify_links.length;i++ ) {
		simplify_links[i].onclick = function(e) {
			e.preventDefault();
			document.body.dispatchEvent(new CustomEvent("wp-easy-dialog", { detail: JSON.parse(this.dataset.dialog) }));
		};
	}
})


import './style.scss'
import React from 'react'
import ReactDOM from 'react-dom'

/**
 * Generate the React driven modal.
 *
 * @param args
 * @returns {JSX.Element}
 * @constructor
 */
function Modal( args ) {
	/**
	 * Close action.
	 */
	const closeDialog = () => {
		top.document.getElementById('react-dialog-root').remove();
	};

	return (
		<>
			<div className="modal_bg" onClick={closeDialog} />
			<div className="modal">
				{args.dialog.title &&
					<h1 className="wp-easy-dialog-title">{args.dialog.title}</h1>
				}
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
							<button className={button.variant} onClick={ () => eval(button.action) }>
								{button.text}
							</button>
						)
					}
				)
				}
			</div>
		</>
	);
};
export default Modal;

/**
 * Show dialog, initiated by any event.
 *
 * If dialog already exist, it will be closed.
 *
 * @type {null}
 */
function add_react_dialog( dialog ) {
	if( dialog ) {
		if( ! top.document.getElementById('react-dialog-root') ) {
			if( top.document.getElementById('wpadminbar') ) {
				let root = top.document.createElement('div');
				root.id = 'react-dialog-root';
				top.document.body.append(root);
			}
		}
		// we use ReactDom.render as Divi comes with React 16 and not React 18.
		ReactDOM.render(<Modal dialog={dialog}/>, top.document.getElementById('react-dialog-root'));
	}
}

/**
 * Add events where the dialog should be fired.
 */
document.addEventListener( 'DOMContentLoaded', () => {
	// add listener which could be used to trigger the dialog with given configuration.
	document.body.addEventListener('react-dialog', function(attr) {
		if( attr.detail ) {
			add_react_dialog(attr.detail);
		}
	});
})


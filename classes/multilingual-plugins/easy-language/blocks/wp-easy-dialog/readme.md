# WP Easy Dialog

## Requirements

* npm to compile the scripts.
* WordPress-plugin, theme or Code Snippet-plugin to embed them in your project.

## Installation

1. Place the files of this directory in your project.
2. Run ``npm i`` to install dependencies.
3. Run ``npm run start`` to compile the scripts.
4. Add the codes from `embed.php` to your WordPress-projekt (plugin or theme).

## Configuration

Each dialog is configured with the following options as array:

* className
  * string with names the modal should become to set individual styles
* title
  * represents the title as single text
* hide_title
  * value set to `true` to hide the title
* texts
  * array of texts for the dialog
  * each entry contains a single string
* buttons
  * array of buttons for the dialog
  * each entry is an array with following settings:
    * action
      * string of JavaScript to run on click
    * variant
      * string to define button-styling
      * possible values:
        * primary
        * secondary
      * this setting is optional
    * text
      * string for the button-text

## Usage

### PHP

```
$dialog = array(
	'title' => 'My title',
	'texts' => array(
		'<p>My text</p>'
	),
	'buttons' => array(
		array(
			'action' => 'alert("ok");',
			'variant' => 'primary',
			'text' => 'Click here'
		),
	)
);
echo '<a href="#" class="wp-easy-dialog" data-dialog="'.esc_attr(wp_json_encode($dialog)).'">Some link</a>';
```

### JavaScript

```
let dialog = array(
	'title' => 'My title',
	'texts' => array(
		'<p>My text</p>'
	),
	'buttons' => array(
		array(
			'action' => 'alert("ok");',
			'variant' => 'primary',
			'text' => 'Click here'
		),
	)
);
document.body.dispatchEvent(new CustomEvent("wp-easy-dialog", config));
```
## Custom styles

You can customize the output of the dialog with your custom css.

E.g.:

```
body.wp-easy-dialog-on-body.wp-core-ui .components-modal__frame.wp-easy-dialog {
 background-color: red;
}
```

## FAQ

### Is it possible to create multiple dialogs on one screen?

No, this is not possible.

### How to open a new dialog after click on dialog-button?

Call your own function as callback for the button.

Example:
```
'action' => 'open_new_dialog()',
```

```
function open_new_dialog() {
 /* define your new dialog */
}
```

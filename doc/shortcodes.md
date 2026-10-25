# Shortcode

The following shortcodes are provided by the plugin.

## Language switcher

Structure: `[easy-language-switcher show_icons="yes"]`

Possible attributes:

* display
	* How the switcher is displayed: "text", "icons" or "toggle".
	* Default: empty, then "show_icons" decides between text and icons.
* show_icons
	* If the value is "yes", icons are displayed instead of text.
	* Default: "no"
* hide_actual_language
	* If the value is "yes", the current language is not displayed (not used by the toggle).
	* Default: "no"
* icon_size
	* Size of the icons as CSS value, e.g. "32px", "2em" or "1.5rem". A number without unit is used as pixel value.
	* Default: empty, then the default size from the plugin settings (Icons tab) is used.
* toggle_language
	* Language code of the simplified language used by the toggle, e.g. "de_LS".
	* Default: empty, then the first active language is used.
* toggle_disabled
	* If the value is "yes", the toggle is shown greyed out on pages without simplification. Otherwise it is hidden there.
	* Default: "no"
* toggle_color_on, toggle_color_off, toggle_color_thumb
	* Colors of the toggle if it is on or off and of its thumb, e.g. "#2271b1", "rgba(0,0,0,0.5)" or "var(--my-color)".
	* Default: empty, then the default colors are used.
* toggle_size
	* Height of the toggle as CSS value, e.g. "24px" or "2em". The width is calculated.
	* Default: empty
* toggle_radius
	* Rounding of the toggle as CSS value, e.g. "4px". Use "0" for a squared toggle.
	* Default: empty (round)
* visibility
	* "all" shows the switcher everywhere, "include" only on the objects in "show_on", "exclude" everywhere except on these objects.
	* Default: "all"
* show_on
	* Comma-separated list of pages, posts and terms as "post:ID" or "term:ID", e.g. "post:12,term:5". Simplifications of these objects are considered automatically.
	* Default: empty
* include_children
	* If the value is "yes", child pages and child terms of the objects in "show_on" are also considered.
	* Default: "no"
* include_assigned
	* If the value is "yes", contents which are assigned to the terms in "show_on" are also considered.
	* Default: "no"
* fallback
	* Behavior on pages without own language versions (archives, search results, blog homepage, 404): "auto" (like before version 4.0.0), "home" (link to the homepages) or "hide".
	* Default: "auto"

Example: show a toggle only in the area "Accessibility" (page with ID 12) and its child pages:

`[easy-language-switcher display="toggle" visibility="include" show_on="post:12" include_children="yes"]`

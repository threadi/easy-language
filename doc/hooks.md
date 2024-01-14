# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `easy_language_update_icon`

*Hook for further infos for the icon.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this` | `array` | The language icon object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-language-icon.php](class-language-icon.php), [line 122](class-language-icon.php#L122-L129)

### `easy_language_capito_automatic_interval`

*Hook for capito automatic interval settings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$intervals` | `array` | The possible intervals.
`$foreign_translation_plugin_with_api_support` | `bool` | Whether we support third-party-plugins.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/capito/class-capito.php](apis/capito/class-capito.php), [line 725](apis/capito/class-capito.php#L725-L733)

### `easy_language_chatgpt_automatic_interval`

*Hook for ChatGpt automatic interval settings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$intervals` | `array` | The possible intervals.
`$foreign_translation_plugin_with_api_support` | `bool` | Whether we support third-party-plugins.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 521](apis/chatgpt/class-chatgpt.php#L521-L529)

### `easy_language_replace_texts`

*Hook for alternatives to replace texts with its simplified forms.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$this` | `\easyLanguage\Multilingual_plugins\Easy_Language\Text` | The text icon object.
`$target_language` | `string` | The target language.
`$object_id` | `int` | The ID of the object.
`$simplification_objects` | `array` | List of simplification objects.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-text.php](multilingual-plugins/easy-language/class-text.php), [line 302](multilingual-plugins/easy-language/class-text.php#L302-L312)

### `easy_language_add_settings_after_post_types`


Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1066](multilingual-plugins/easy-language/class-init.php#L1066-L1066)

## Filters

### `easy_language_icon_path`

*Get path where the icon files are located.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$path` | `string` | The path.
`$file` | `string` | The actual icon file.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-language-icon.php](class-language-icon.php), [line 52](class-language-icon.php#L52-L60)

### `easy_language_summ_ai_request_object`

*Filter the SUMM AI request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Apis\Summ_Ai\Request` | The SUMM AI request object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/summ-ai/class-simplifications.php](apis/summ-ai/class-simplifications.php), [line 90](apis/summ-ai/class-simplifications.php#L90-L97)

### `easy_language_simplified_text`

*Filter the simplified text.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$simplified_text` | `string` | The simplified text.
`$response_array` | `array` | The complete response array from the API.
`$this` | `\easyLanguage\Apis\Summ_Ai\Simplifications` | The simplification object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/summ-ai/class-simplifications.php](apis/summ-ai/class-simplifications.php), [line 111](apis/summ-ai/class-simplifications.php#L111-L120)

### `easy_language_quota_percent`

*Hook for minimal quota percent.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$min_percent` | `float` | Minimal percent for quota warning.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 159](apis/summ-ai/class-summ-ai.php#L159-L166)

### `easy_language_summ_ai_source_languages`

*Filter SUMM AI source languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$source_languages` | `array` | List of source languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 258](apis/summ-ai/class-summ-ai.php#L258-L265)

### `easy_language_summ_ai_target_languages`

*Filter SUMM AI target languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$target_languages` | `array` | List of target languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 298](apis/summ-ai/class-summ-ai.php#L298-L305)

### `easy_language_summ_ai_mapping_languages`

*Filter SUMM AI mappings of languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$languages_mapping` | `array` | List of mappings.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 324](apis/summ-ai/class-summ-ai.php#L324-L331)

### `easy_language_quota_percent`

*Hook for minimal quota percent.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$min_percent` | `float` | Minimal percent for quota warning.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 1040](apis/summ-ai/class-summ-ai.php#L1040-L1047)

### `easy_language_capito_source_languages`

*Filter capito source languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$source_languages` | `array` | List of source languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/capito/class-capito.php](apis/capito/class-capito.php), [line 224](apis/capito/class-capito.php#L224-L231)

### `easy_language_capito_target_languages`

*Filter capito target languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$target_languages` | `array` | List of target languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/capito/class-capito.php](apis/capito/class-capito.php), [line 274](apis/capito/class-capito.php#L274-L281)

### `easy_language_capito_mapping_languages`

*Filter mapping of capito languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$language_mappings` | `array` | List of mappings.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/capito/class-capito.php](apis/capito/class-capito.php), [line 300](apis/capito/class-capito.php#L300-L307)

### `easy_language_quota_percent`

*Hook for minimal quota percent.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$min_percent` | `float` | Minimal percent for quota warning.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/capito/class-capito.php](apis/capito/class-capito.php), [line 988](apis/capito/class-capito.php#L988-L995)

### `easy_language_capito_request_object`

*Filter the capito request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Apis\Summ_Ai\Request` | The capito request object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/capito/class-simplifications.php](apis/capito/class-simplifications.php), [line 92](apis/capito/class-simplifications.php#L92-L99)

### `easy_language_simplified_text`

*Filter the simplified text.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$simplified_text` | `string` | The simplified text.
`$request_array` | `array` | The complete response array from the API.
`$this` | `\easyLanguage\Apis\Capito\Simplifications` | The simplification object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/capito/class-simplifications.php](apis/capito/class-simplifications.php), [line 113](apis/capito/class-simplifications.php#L113-L122)

### `easy_language_chatgpt_source_languages`

*Filter ChatGpt source languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list_of_languages` | `array` | List of source languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 199](apis/chatgpt/class-chatgpt.php#L199-L206)

### `easy_language_chatgpt_target_languages`

*Filter ChatGpt target languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$target_languages` | `array` | List of target languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 239](apis/chatgpt/class-chatgpt.php#L239-L246)

### `easy_language_chatgpt_mapping_languages`

*Filter ChatGpt language mappings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$language_mappings` | `array` | List of mappings.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 290](apis/chatgpt/class-chatgpt.php#L290-L297)

### `easy_language_chatgpt_models`

*Filter the available ChatGpt-models.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$models` | `array` | List of ChatGpt models.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 434](apis/chatgpt/class-chatgpt.php#L434-L441)

### `easy_language_chatgpt_request_object`

*Filter the ChatGpt request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Apis\Summ_Ai\Request` | The ChatGpt request object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/chatgpt/class-simplifications.php](apis/chatgpt/class-simplifications.php), [line 90](apis/chatgpt/class-simplifications.php#L90-L97)

### `easy_language_simplified_text`

*Filter the simplified text.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$simplified_text` | `string` | The simplified text.
`$request_array` | `array` | The complete response array from the API.
`$this` | `\easyLanguage\Apis\ChatGpt\Simplifications` | The simplification object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/chatgpt/class-simplifications.php](apis/chatgpt/class-simplifications.php), [line 113](apis/chatgpt/class-simplifications.php#L113-L122)

### `easy_language_possible_source_languages`

*Filter general source languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$source_languages` | `array` | List of source languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-languages.php](class-languages.php), [line 118](class-languages.php#L118-L125)

### `easy_language_supported_target_languages`

*Filter general target languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$target_languages` | `array` | List of target languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-languages.php](class-languages.php), [line 155](class-languages.php#L155-L162)

### `easy_language_register_plugin`

*Filter the available plugins.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$plugin_list` | `array` | List of plugins.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-multilingual-plugins.php](class-multilingual-plugins.php), [line 60](class-multilingual-plugins.php#L60-L67)

### `easy_language_get_object`

*Filter the object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | Return false as default.
`$object_id` | `int` | The ID of the object.
`$object_type` | `string` | The type of the object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-helper.php](class-helper.php), [line 476](class-helper.php#L476-L485)

### `easy_language_post_type_names`

*Filter the post type names.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_type_names` | `array` | List of post type names.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-init.php](class-init.php), [line 206](class-init.php#L206-L213)

### `easy_language_post_type_settings`

*Filter the post type names.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_type_settings` | `array` | List of post type settings.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-init.php](class-init.php), [line 243](class-init.php#L243-L250)

### `easy_language_capito_request_object`

*Filter the capito request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Apis\Capito\Request` | The capito request object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/translatepress/class-translatepress-capito-machine-translator.php](multilingual-plugins/translatepress/class-translatepress-capito-machine-translator.php), [line 49](multilingual-plugins/translatepress/class-translatepress-capito-machine-translator.php#L49-L56)

### `easy_language_summ_ai_request_object`

*Filter the SUMM AI request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Apis\Summ_Ai\Request` | The SUMM AI request object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/translatepress/class-translatepress-summ-ai-machine-translator.php](multilingual-plugins/translatepress/class-translatepress-summ-ai-machine-translator.php), [line 57](multilingual-plugins/translatepress/class-translatepress-summ-ai-machine-translator.php#L57-L64)

### `easy_language_simplification_table_to_simplify`

*Filter the column name.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$column_name` | `string` | The column name.
`$item` | `\easyLanguage\Multilingual_plugins\Easy_Language\Text` | The item object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-texts-to-simplify-table.php](multilingual-plugins/easy-language/class-texts-to-simplify-table.php), [line 116](multilingual-plugins/easy-language/class-texts-to-simplify-table.php#L116-L124)

### `easy_language_simplification_to_simplify_table_options`

*Filter additional options.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$options` | `array` | List of options.
`$item_id` | `int` | The ID of the object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-texts-to-simplify-table.php](multilingual-plugins/easy-language/class-texts-to-simplify-table.php), [line 171](multilingual-plugins/easy-language/class-texts-to-simplify-table.php#L171-L179)

### `easy_language_simplification_table_used_in`

*Filter the column name.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$column_name` | `string` | The column name.
`$item` | `\easyLanguage\Multilingual_plugins\Easy_Language\Text` | The object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-texts-in-use-table.php](multilingual-plugins/easy-language/class-texts-in-use-table.php), [line 132](multilingual-plugins/easy-language/class-texts-in-use-table.php#L132-L140)

### `easy_language_simplification_table_options`

*Filter additional options.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$options` | `array` | List of options.
`$item_id` | `int` | The ID of the object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-texts-in-use-table.php](multilingual-plugins/easy-language/class-texts-in-use-table.php), [line 194](multilingual-plugins/easy-language/class-texts-in-use-table.php#L194-L202)

### `easy_language_first_simplify_dialog`

*Filter the dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array` | The dialog configuration.
`$api_obj` | `\easyLanguage\Api_Base` | The used API as object.
`$post_object` | `\easyLanguage\Multilingual_plugins\Easy_Language\Post_Object` | The Post as object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 427](multilingual-plugins/easy-language/class-init.php#L427-L436)

### `easy_language_possible_post_types`

*Filter possible post types.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_types` | `array` | The list of possible post types.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1029](multilingual-plugins/easy-language/class-init.php#L1029-L1036)

### `easy_language_first_simplify_dialog`

*Filter the dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array` | The dialog configuration.
`$api_obj` | `\easyLanguage\Api_Base` | The used API as object.
`$post_object` | `\easyLanguage\Multilingual_plugins\Easy_Language\Post_Object` | The Post as object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1375](multilingual-plugins/easy-language/class-init.php#L1375-L1384)

### `easy_language_get_object_by_wp_object`

*Filter the resulting easy language object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$object` | `object` | The easy language object to filter (e.g. WP_Post).
`$wp_object` | `object` | The original WP object.
`$id` | `int` | The ID of the object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1411](multilingual-plugins/easy-language/class-init.php#L1411-L1420)

### `easy_language_quota_percent`

*Hook for minimal quota percent.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$min_percent` | `float` | Minimal percent for quota warning.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1948](multilingual-plugins/easy-language/class-init.php#L1948-L1955)

### `easy_language_divi_text_widgets`

*Filter the possible Divi shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-divi.php](multilingual-plugins/easy-language/parser/class-divi.php), [line 75](multilingual-plugins/easy-language/parser/class-divi.php#L75-L82)

### `easy_language_divi_html_widgets`

*Filter the possible Divi widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-divi.php](multilingual-plugins/easy-language/parser/class-divi.php), [line 97](multilingual-plugins/easy-language/parser/class-divi.php#L97-L104)

### `easy_language_gutenberg_blocks`

*Filter the possible Gutenberg Blocks.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$blocks` | `array` | List of Blocks.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-gutenberg.php](multilingual-plugins/easy-language/parser/class-gutenberg.php), [line 78](multilingual-plugins/easy-language/parser/class-gutenberg.php#L78-L85)

### `easy_language_avada_text_widgets`

*Filter the possible Avada shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-avada.php](multilingual-plugins/easy-language/parser/class-avada.php), [line 71](multilingual-plugins/easy-language/parser/class-avada.php#L71-L78)

### `easy_language_avada_html_widgets`

*Filter the possible Divi widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-avada.php](multilingual-plugins/easy-language/parser/class-avada.php), [line 93](multilingual-plugins/easy-language/parser/class-avada.php#L93-L100)

### `easy_language_themify_text_widgets`

*Filter the possible themify widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-themify.php](multilingual-plugins/easy-language/parser/class-themify.php), [line 83](multilingual-plugins/easy-language/parser/class-themify.php#L83-L90)

### `easy_language_themify_html_widgets`

*Filter the possible themify widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-themify.php](multilingual-plugins/easy-language/parser/class-themify.php), [line 105](multilingual-plugins/easy-language/parser/class-themify.php#L105-L112)

### `easy_language_wpbakery_text_widgets`

*Filter the shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-wpbakery.php](multilingual-plugins/easy-language/parser/class-wpbakery.php), [line 80](multilingual-plugins/easy-language/parser/class-wpbakery.php#L80-L87)

### `easy_language_wpbakery_html_widgets`

*Filter the possible WP Bakery widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-wpbakery.php](multilingual-plugins/easy-language/parser/class-wpbakery.php), [line 102](multilingual-plugins/easy-language/parser/class-wpbakery.php#L102-L109)

### `easy_language_elementor_text_widgets`

*Filter the possible Elementor widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-elementor.php](multilingual-plugins/easy-language/parser/class-elementor.php), [line 91](multilingual-plugins/easy-language/parser/class-elementor.php#L91-L98)

### `easy_language_elementor_html_widgets`

*Filter the possible Elementor widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-elementor.php](multilingual-plugins/easy-language/parser/class-elementor.php), [line 113](multilingual-plugins/easy-language/parser/class-elementor.php#L113-L120)

### `easy_language_pagebuilder`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array()` |  | 

Source: [classes/multilingual-plugins/easy-language/class-post-object.php](multilingual-plugins/easy-language/class-post-object.php), [line 291](multilingual-plugins/easy-language/class-post-object.php#L291-L291)

### `easy_language_quota_percent`

*Hook for minimal quota percent.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$min_percent` | `float` | Minimal percent for quota warning.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/class-parser-base.php](multilingual-plugins/easy-language/class-parser-base.php), [line 264](multilingual-plugins/easy-language/class-parser-base.php#L264-L271)

### `easy_language_register_api`

*Filter the list of APIs.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$apis` | `array` | List of APIs

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-apis.php](class-apis.php), [line 60](class-apis.php#L60-L67)

### `easy_language_get_transients_for_display`

*Filter the list of transients.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$transients` | `array` | List of transients.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/class-transients.php](class-transients.php), [line 152](class-transients.php#L152-L159)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


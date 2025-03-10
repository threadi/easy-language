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

Source: [classes/class-language-icon.php](class-language-icon.php), [line 136](class-language-icon.php#L136-L143)

### `easy_language_admin_show_pro_hint`

*Show the possible target languages with its additional settings as table.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$attr['pro_hint']` |  | 

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 1581](apis/summ-ai/class-summ-ai.php#L1581-L1694)

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

Source: [classes/apis/capito/class-capito.php](apis/capito/class-capito.php), [line 729](apis/capito/class-capito.php#L729-L737)

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

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 526](apis/chatgpt/class-chatgpt.php#L526-L534)

### `easy_language_uninstaller`

*Run additional tasks for uninstallation via WP CLI.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$options` | `array` | Options used to call this command.

**Changelog**

Version | Description
------- | -----------
`2.3.0` | Available since 2.3.0.

Source: [classes/class-cli.php](class-cli.php), [line 35](class-cli.php#L35-L42)

### `easy_language_installer`

*Run additional tasks for installation via WP CLI.*


**Changelog**

Version | Description
------- | -----------
`2.3.0` | Available since 2.3.0.

Source: [classes/class-cli.php](class-cli.php), [line 47](class-cli.php#L47-L52)

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

Source: [classes/multilingual-plugins/easy-language/class-text.php](multilingual-plugins/easy-language/class-text.php), [line 304](multilingual-plugins/easy-language/class-text.php#L304-L314)

### `easy_language_add_settings_after_post_types`

*Add settings for this plugin.*


Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1030](multilingual-plugins/easy-language/class-init.php#L1030-L1095)

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

Source: [classes/class-language-icon.php](class-language-icon.php), [line 61](class-language-icon.php#L61-L69)

### `easy_language_summ_ai_request_object`

*Filter the SUMM AI request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Apis\Summ_Ai\Request` | The SUMM AI request object.
`$is_html` | `bool` | Whether to use HTML or not.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/summ-ai/class-simplifications.php](apis/summ-ai/class-simplifications.php), [line 108](apis/summ-ai/class-simplifications.php#L108-L116)

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

Source: [classes/apis/summ-ai/class-simplifications.php](apis/summ-ai/class-simplifications.php), [line 130](apis/summ-ai/class-simplifications.php#L130-L139)

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

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 165](apis/summ-ai/class-summ-ai.php#L165-L172)

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

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 269](apis/summ-ai/class-summ-ai.php#L269-L276)

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

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 316](apis/summ-ai/class-summ-ai.php#L316-L323)

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

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 342](apis/summ-ai/class-summ-ai.php#L342-L349)

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

Source: [classes/apis/summ-ai/class-summ-ai.php](apis/summ-ai/class-summ-ai.php), [line 1159](apis/summ-ai/class-summ-ai.php#L1159-L1166)

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

### `easy_language_capito_request_object`

*Filter the capito request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Apis\Summ_Ai\Request` | The capito request object.
`$is_html` | `bool` | Whether to use HTML or not.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/capito/class-simplifications.php](apis/capito/class-simplifications.php), [line 92](apis/capito/class-simplifications.php#L92-L100)

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

Source: [classes/apis/capito/class-simplifications.php](apis/capito/class-simplifications.php), [line 114](apis/capito/class-simplifications.php#L114-L123)

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

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 200](apis/chatgpt/class-chatgpt.php#L200-L207)

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

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 240](apis/chatgpt/class-chatgpt.php#L240-L247)

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

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 294](apis/chatgpt/class-chatgpt.php#L294-L301)

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

Source: [classes/apis/chatgpt/class-chatgpt.php](apis/chatgpt/class-chatgpt.php), [line 439](apis/chatgpt/class-chatgpt.php#L439-L446)

### `easy_language_chatgpt_request_object`

*Filter the ChatGpt request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Apis\Summ_Ai\Request` | The ChatGpt request object.
`$is_html` | `bool` | Whether to use HTML or not.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/apis/chatgpt/class-simplifications.php](apis/chatgpt/class-simplifications.php), [line 91](apis/chatgpt/class-simplifications.php#L91-L99)

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

Source: [classes/apis/chatgpt/class-simplifications.php](apis/chatgpt/class-simplifications.php), [line 115](apis/chatgpt/class-simplifications.php#L115-L124)

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

Source: [classes/class-languages.php](class-languages.php), [line 114](class-languages.php#L114-L121)

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

Source: [classes/class-languages.php](class-languages.php), [line 153](class-languages.php#L153-L160)

### `easy_language_transient_hide_on`

*Filter where a single transient should be hidden.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$hide_on` | `array` | List of absolute URLs.
`$this` | `\easyLanguage\Transient` | The actual transient object.

**Changelog**

Version | Description
------- | -----------
`2.2.0` | Available since 2.2.0.

Source: [classes/class-transient.php](class-transient.php), [line 362](class-transient.php#L362-L370)

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

Source: [classes/class-multilingual-plugins.php](class-multilingual-plugins.php), [line 57](class-multilingual-plugins.php#L57-L64)

### `easy_language_setup`

*Filter the configured setup for this plugin.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$setup` | `array` | The setup-configuration.

**Changelog**

Version | Description
------- | -----------
`2.2.0` | Available since 2.2.0.

Source: [classes/class-setup.php](class-setup.php), [line 179](class-setup.php#L179-L186)

### `easy_language_transient_title`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`Helper::get_plugin_name()` |  | 

Source: [classes/class-setup.php](class-setup.php), [line 196](class-setup.php#L196-L196)

### `easy_language_setup_config`

*Filter the setup configuration.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$config` | `array` | List of configuration for the setup.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [classes/class-setup.php](class-setup.php), [line 250](class-setup.php#L250-L256)

### `easy_language_setup_process_completed_text`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$completed_text` |  | 
`$config_name` |  | 

Source: [classes/class-setup.php](class-setup.php), [line 383](class-setup.php#L383-L383)

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

Source: [classes/class-helper.php](class-helper.php), [line 498](class-helper.php#L498-L507)

### `easy_language_file_version`

*Filter the used file version (for JS- and CSS-files which get enqueued).*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$plugin_version` | `string` | The plugin-version.
`$filepath` | `string` | The absolute path to the requested file.

**Changelog**

Version | Description
------- | -----------
`2.3.0` | Available since 2.3.0.

Source: [classes/class-helper.php](class-helper.php), [line 719](class-helper.php#L719-L727)

### `easy_language_post_meta_keys_to_ignore`

*Filter the list of post meta keys we ignore during creating new object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$keys` | `array` | List of keys to ignore.

**Changelog**

Version | Description
------- | -----------
`2.4.0` | Available since 2.4.0.

Source: [classes/class-helper.php](class-helper.php), [line 738](class-helper.php#L738-L744)

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

Source: [classes/class-init.php](class-init.php), [line 214](class-init.php#L214-L221)

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

Source: [classes/class-init.php](class-init.php), [line 251](class-init.php#L251-L258)

### `easy_language_plugin_row_meta`

*Filter the links in row meta of our plugin in plugin list.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$row_meta` | `array` | List of links.

**Changelog**

Version | Description
------- | -----------
`2.6.0` | Available since 2.6.0.

Source: [classes/class-init.php](class-init.php), [line 434](class-init.php#L434-L440)

### `easy_language_status_list`

*Filter the list of possible states in log table.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` |  | 

**Changelog**

Version | Description
------- | -----------
`2.3.0` | Available since 2.3.0

Source: [classes/class-log-table.php](class-log-table.php), [line 162](class-log-table.php#L162-L167)

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

### `easy_language_prevent_simple_permalinks`

*Prevent the usage of the simple generation of permalinks.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$false` | `bool` | True to prevent the usage.

**Changelog**

Version | Description
------- | -----------
`2.3.2` | Available since 2.3.2

Source: [classes/multilingual-plugins/easy-language/class-rewrite.php](multilingual-plugins/easy-language/class-rewrite.php), [line 203](multilingual-plugins/easy-language/class-rewrite.php#L203-L211)

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

Source: [classes/multilingual-plugins/easy-language/class-texts-to-simplify-table.php](multilingual-plugins/easy-language/class-texts-to-simplify-table.php), [line 120](multilingual-plugins/easy-language/class-texts-to-simplify-table.php#L120-L128)

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

Source: [classes/multilingual-plugins/easy-language/class-texts-to-simplify-table.php](multilingual-plugins/easy-language/class-texts-to-simplify-table.php), [line 175](multilingual-plugins/easy-language/class-texts-to-simplify-table.php#L175-L183)

### `easy_language_js_top`

*Set top for JS-location if page builder which makes it necessary is actually used.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$js_top` | `string` | The top-string.

**Changelog**

Version | Description
------- | -----------
`2.2.0` | Available since 2.2.0.

Source: [classes/multilingual-plugins/easy-language/abstract-objects.php](multilingual-plugins/easy-language/abstract-objects.php), [line 153](multilingual-plugins/easy-language/abstract-objects.php#L153-L159)

### `easy_language_js_top`

*Set top for JS-location if page builder which makes it necessary is actually used.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$js_top` | `string` | The top-string.

**Changelog**

Version | Description
------- | -----------
`2.2.0` | Available since 2.2.0.

Source: [classes/multilingual-plugins/easy-language/abstract-objects.php](multilingual-plugins/easy-language/abstract-objects.php), [line 540](multilingual-plugins/easy-language/abstract-objects.php#L540-L546)

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

Source: [classes/multilingual-plugins/easy-language/class-texts-in-use-table.php](multilingual-plugins/easy-language/class-texts-in-use-table.php), [line 136](multilingual-plugins/easy-language/class-texts-in-use-table.php#L136-L144)

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

Source: [classes/multilingual-plugins/easy-language/class-texts-in-use-table.php](multilingual-plugins/easy-language/class-texts-in-use-table.php), [line 198](multilingual-plugins/easy-language/class-texts-in-use-table.php#L198-L206)

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

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 452](multilingual-plugins/easy-language/class-init.php#L452-L461)

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

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1058](multilingual-plugins/easy-language/class-init.php#L1058-L1065)

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

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1404](multilingual-plugins/easy-language/class-init.php#L1404-L1413)

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

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 1440](multilingual-plugins/easy-language/class-init.php#L1440-L1449)

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

Source: [classes/multilingual-plugins/easy-language/class-init.php](multilingual-plugins/easy-language/class-init.php), [line 2024](multilingual-plugins/easy-language/class-init.php#L2024-L2031)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-divi.php](multilingual-plugins/easy-language/parser/class-divi.php), [line 73](multilingual-plugins/easy-language/parser/class-divi.php#L73-L80)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-divi.php](multilingual-plugins/easy-language/parser/class-divi.php), [line 95](multilingual-plugins/easy-language/parser/class-divi.php#L95-L102)

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

### `easy_language_avia_text_widgets`

*Filter the possible Avia shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.6.0` | Available since 2.6.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-avia.php](multilingual-plugins/easy-language/parser/class-avia.php), [line 71](multilingual-plugins/easy-language/parser/class-avia.php#L71-L78)

### `easy_language_avia_html_widgets`

*Filter the possible Avia widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.6.0` | Available since 2.6.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-avia.php](multilingual-plugins/easy-language/parser/class-avia.php), [line 93](multilingual-plugins/easy-language/parser/class-avia.php#L93-L100)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-avada.php](multilingual-plugins/easy-language/parser/class-avada.php), [line 69](multilingual-plugins/easy-language/parser/class-avada.php#L69-L76)

### `easy_language_avada_html_widgets`

*Filter the possible Avada widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [classes/multilingual-plugins/easy-language/parser/class-avada.php](multilingual-plugins/easy-language/parser/class-avada.php), [line 91](multilingual-plugins/easy-language/parser/class-avada.php#L91-L98)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-themify.php](multilingual-plugins/easy-language/parser/class-themify.php), [line 81](multilingual-plugins/easy-language/parser/class-themify.php#L81-L88)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-themify.php](multilingual-plugins/easy-language/parser/class-themify.php), [line 103](multilingual-plugins/easy-language/parser/class-themify.php#L103-L110)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-wpbakery.php](multilingual-plugins/easy-language/parser/class-wpbakery.php), [line 78](multilingual-plugins/easy-language/parser/class-wpbakery.php#L78-L85)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-wpbakery.php](multilingual-plugins/easy-language/parser/class-wpbakery.php), [line 100](multilingual-plugins/easy-language/parser/class-wpbakery.php#L100-L107)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-elementor.php](multilingual-plugins/easy-language/parser/class-elementor.php), [line 89](multilingual-plugins/easy-language/parser/class-elementor.php#L89-L96)

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

Source: [classes/multilingual-plugins/easy-language/parser/class-elementor.php](multilingual-plugins/easy-language/parser/class-elementor.php), [line 111](multilingual-plugins/easy-language/parser/class-elementor.php#L111-L118)

### `easy_language_pagebuilder`

*Get pagebuilder of this object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array()` |  | 

Source: [classes/multilingual-plugins/easy-language/class-post-object.php](multilingual-plugins/easy-language/class-post-object.php), [line 281](multilingual-plugins/easy-language/class-post-object.php#L281-L289)

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

Source: [classes/multilingual-plugins/easy-language/class-parser-base.php](multilingual-plugins/easy-language/class-parser-base.php), [line 267](multilingual-plugins/easy-language/class-parser-base.php#L267-L274)

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

Source: [classes/class-apis.php](class-apis.php), [line 59](class-apis.php#L59-L66)

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

Source: [classes/class-transients.php](class-transients.php), [line 150](class-transients.php#L150-L157)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


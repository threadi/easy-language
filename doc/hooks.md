# Hooks

- [Actions](#actions)
- [Filters](#filters)

## Actions

### `easy_language_capito_automatic_interval`

*Add capito settings.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`array($intervals)` |  | 
`'3.0.0'` |  | 

Source: [app/Apis/Capito/Capito.php](Apis/Capito/Capito.php), [line 476](Apis/Capito/Capito.php#L476-L682)

### `easy_language_uninstaller`

*Run additional tasks for uninstallation.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$options` | `array<string,string>` | Options used to call this command.

**Changelog**

Version | Description
------- | -----------
`2.3.0` | Available since 2.3.0.

Source: [app/Plugin/Cli.php](Plugin/Cli.php), [line 34](Plugin/Cli.php#L34-L41)

### `easy_language_installer`

*Run additional tasks for installation.*


**Changelog**

Version | Description
------- | -----------
`2.3.0` | Available since 2.3.0.

Source: [app/Plugin/Cli.php](Plugin/Cli.php), [line 46](Plugin/Cli.php#L46-L51)

### `easy_language_update_icon`

*Hook for further infos for the icon.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$instance` | `\easyLanguage\Plugin\Language_Icon` | The language icon object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Language_Icon.php](Plugin/Language_Icon.php), [line 139](Plugin/Language_Icon.php#L139-L146)

### `easy_language_replace_texts`

*Hook for alternatives to replace texts with its simplified forms.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$instance` | `\easyLanguage\EasyLanguage\Text` | The text icon object.
`$target_language` | `string` | The target language.
`$object_id` | `int` | The ID of the object.
`$simplification_objects` | `array` | List of simplification objects.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/EasyLanguage/Text.php](EasyLanguage/Text.php), [line 315](EasyLanguage/Text.php#L315-L325)

## Filters

### `easy_language_capito_request_object`

*Filter the capito request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Plugin\Api_Requests` | The capito request object.
`$is_html` | `bool` | Whether to use HTML or not.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Apis/Capito/Simplifications.php](Apis/Capito/Simplifications.php), [line 82](Apis/Capito/Simplifications.php#L82-L90)

### `easy_language_simplified_text`

*Filter the simplified text.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$simplified_text` | `string` | The simplified text.
`$response_array` | `array` | The complete response array from the API.
`$instance` | `\easyLanguage\Apis\Capito\Simplifications` | The simplification object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Apis/Capito/Simplifications.php](Apis/Capito/Simplifications.php), [line 105](Apis/Capito/Simplifications.php#L105-L114)

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

Source: [app/Apis/Capito/Capito.php](Apis/Capito/Capito.php), [line 227](Apis/Capito/Capito.php#L227-L234)

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

Source: [app/Apis/Capito/Capito.php](Apis/Capito/Capito.php), [line 277](Apis/Capito/Capito.php#L277-L284)

### `easy_language_capito_mapping_languages`

*Filter mapping of capito languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$language_mappings` | `array<string,string[]>` | List of mappings.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Apis/Capito/Capito.php](Apis/Capito/Capito.php), [line 303](Apis/Capito/Capito.php#L303-L310)

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

Source: [app/Apis/ChatGpt/ChatGpt.php](Apis/ChatGpt/ChatGpt.php), [line 205](Apis/ChatGpt/ChatGpt.php#L205-L212)

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

Source: [app/Apis/ChatGpt/ChatGpt.php](Apis/ChatGpt/ChatGpt.php), [line 245](Apis/ChatGpt/ChatGpt.php#L245-L252)

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

Source: [app/Apis/ChatGpt/ChatGpt.php](Apis/ChatGpt/ChatGpt.php), [line 299](Apis/ChatGpt/ChatGpt.php#L299-L306)

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

Source: [app/Apis/ChatGpt/ChatGpt.php](Apis/ChatGpt/ChatGpt.php), [line 384](Apis/ChatGpt/ChatGpt.php#L384-L391)

### `easy_language_chatgpt_request_object`

*Filter the ChatGpt request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Plugin\Api_Requests` | The ChatGpt request object.
`$is_html` | `bool` | Whether to use HTML or not.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Apis/ChatGpt/Simplifications.php](Apis/ChatGpt/Simplifications.php), [line 81](Apis/ChatGpt/Simplifications.php#L81-L89)

### `easy_language_simplified_text`

*Filter the simplified text.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$simplified_text` | `string` | The simplified text.
`$request_array` | `array<string,mixed>` | The complete response array from the API.
`$instance` | `\easyLanguage\Apis\ChatGpt\Simplifications` | The simplification object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Apis/ChatGpt/Simplifications.php](Apis/ChatGpt/Simplifications.php), [line 106](Apis/ChatGpt/Simplifications.php#L106-L115)

### `easy_language_summ_ai_request_object`

*Filter the SUMM AI request object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$request_obj` | `\easyLanguage\Plugin\Api_Requests` | The SUMM AI request object.
`$is_html` | `bool` | Whether to use HTML or not.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Apis/Summ_Ai/Simplifications.php](Apis/Summ_Ai/Simplifications.php), [line 84](Apis/Summ_Ai/Simplifications.php#L84-L92)

### `easy_language_simplified_text`

*Filter the simplified text.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$simplified_text` | `string` | The simplified text.
`$response_array` | `array` | The complete response array from the API.
`$instance` | `\easyLanguage\Apis\Summ_Ai\Simplifications` | The simplification object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Apis/Summ_Ai/Simplifications.php](Apis/Summ_Ai/Simplifications.php), [line 107](Apis/Summ_Ai/Simplifications.php#L107-L116)

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

Source: [app/Apis/Summ_Ai/Summ_Ai.php](Apis/Summ_Ai/Summ_Ai.php), [line 170](Apis/Summ_Ai/Summ_Ai.php#L170-L177)

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

Source: [app/Apis/Summ_Ai/Summ_Ai.php](Apis/Summ_Ai/Summ_Ai.php), [line 274](Apis/Summ_Ai/Summ_Ai.php#L274-L281)

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

Source: [app/Apis/Summ_Ai/Summ_Ai.php](Apis/Summ_Ai/Summ_Ai.php), [line 321](Apis/Summ_Ai/Summ_Ai.php#L321-L328)

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

Source: [app/Apis/Summ_Ai/Summ_Ai.php](Apis/Summ_Ai/Summ_Ai.php), [line 347](Apis/Summ_Ai/Summ_Ai.php#L347-L354)

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

Source: [app/Apis/Summ_Ai/Summ_Ai.php](Apis/Summ_Ai/Summ_Ai.php), [line 1049](Apis/Summ_Ai/Summ_Ai.php#L1049-L1056)

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

Source: [app/Plugin/Languages.php](Plugin/Languages.php), [line 114](Plugin/Languages.php#L114-L121)

### `easy_language_supported_target_languages`

*Filter general target languages.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$target_languages` | `array<string,array<string,mixed>>` | List of target languages.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Languages.php](Plugin/Languages.php), [line 153](Plugin/Languages.php#L153-L160)

### `easy_language_register_api`

*Return available APIs for simplifications with this plugin.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$apis` |  | 
`'3.0.0'` |  | 

Source: [app/Plugin/Apis.php](Plugin/Apis.php), [line 52](Plugin/Apis.php#L52-L82)

### `easy_language_apis`

*Filter the list of APIs.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$apis` | `array<int,string>` | List of APIs

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Plugin/Apis.php](Plugin/Apis.php), [line 102](Plugin/Apis.php#L102-L108)

### `easy_language_third_party_plugins`

*Filter the list of third party plugins.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$plugins` | `array<int,string>` | List of plugins.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Plugin/ThirdPartySupports.php](Plugin/ThirdPartySupports.php), [line 96](Plugin/ThirdPartySupports.php#L96-L102)

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

Source: [app/Plugin/Tables/Log_Table.php](Plugin/Tables/Log_Table.php), [line 164](Plugin/Tables/Log_Table.php#L164-L169)

### `easy_language_status_list`

*Filter the list of possible states in log table.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<string,string>` | The list of possible states.

**Changelog**

Version | Description
------- | -----------
`2.8.0` | Available since 2.8.0.

Source: [app/Plugin/Tables/Log_Api_Table.php](Plugin/Tables/Log_Api_Table.php), [line 272](Plugin/Tables/Log_Api_Table.php#L272-L279)

### `easy_language_setup`

*Filter the configured setup for this plugin.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$setup` | `array<int,array<string,mixed>>` | The setup-configuration.

**Changelog**

Version | Description
------- | -----------
`2.2.0` | Available since 2.2.0.

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 177](Plugin/Setup.php#L177-L184)

### `easy_language_transient_title`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`Helper::get_plugin_name()` |  | 

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 194](Plugin/Setup.php#L194-L194)

### `easy_language_setup_config`

*Filter the setup configuration.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$config` | `array<string,mixed>` | List of configuration for the setup.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 248](Plugin/Setup.php#L248-L254)

### `easy_language_setup_process_completed_text`

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$completed_text` |  | 
`$config_name` |  | 

Source: [app/Plugin/Setup.php](Plugin/Setup.php), [line 381](Plugin/Setup.php#L381-L381)

### `easy_language_post_type_names`

*Filter the post type names.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_type_names` | `array<string,string>` | List of post type names.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Init.php](Plugin/Init.php), [line 203](Plugin/Init.php#L203-L210)

### `easy_language_post_type_settings`

*Filter the post type names.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_type_settings` | `array<string,array<string,string>>` | List of post type settings.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Plugin/Init.php](Plugin/Init.php), [line 240](Plugin/Init.php#L240-L247)

### `easy_language_plugin_row_meta`

*Filter the links in row meta of our plugin in plugin list.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$row_meta` | `array<string,string>` | List of links.

**Changelog**

Version | Description
------- | -----------
`2.6.0` | Available since 2.6.0.

Source: [app/Plugin/Init.php](Plugin/Init.php), [line 420](Plugin/Init.php#L420-L426)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 525](Plugin/Helper.php#L525-L536)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 725](Plugin/Helper.php#L725-L733)

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

Source: [app/Plugin/Helper.php](Plugin/Helper.php), [line 744](Plugin/Helper.php#L744-L750)

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

Source: [app/Plugin/Language_Icon.php](Plugin/Language_Icon.php), [line 63](Plugin/Language_Icon.php#L63-L71)

### `easy_language_parsers_list`

*Filter the list of supported parsers.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | The list of supported parsers.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/EasyLanguage/Parsers.php](EasyLanguage/Parsers.php), [line 118](EasyLanguage/Parsers.php#L118-L124)

### `easy_language_switcher_entry_classes`

*Filter the classes for single switcher entry.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$class` | `string` | The classes.
`$language_code` | `string` | The language code.
`$settings` | `array` | The language settings.

**Changelog**

Version | Description
------- | -----------
`2.9.1` | Available since 2.9.1.

Source: [app/EasyLanguage/Switcher.php](EasyLanguage/Switcher.php), [line 268](EasyLanguage/Switcher.php#L268-L276)

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

Source: [app/EasyLanguage/Rewrite.php](EasyLanguage/Rewrite.php), [line 199](EasyLanguage/Rewrite.php#L199-L207)

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

Source: [app/EasyLanguage/Objects.php](EasyLanguage/Objects.php), [line 240](EasyLanguage/Objects.php#L240-L246)

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

Source: [app/EasyLanguage/Objects.php](EasyLanguage/Objects.php), [line 638](EasyLanguage/Objects.php#L638-L644)

### `easy_language_simplification_table_to_simplify`

*Filter the column name.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$column_name` | `string` | The column name.
`$item` | `\easyLanguage\EasyLanguage\Text` | The item object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/EasyLanguage/Tables/Texts_To_Simplify_Table.php](EasyLanguage/Tables/Texts_To_Simplify_Table.php), [line 120](EasyLanguage/Tables/Texts_To_Simplify_Table.php#L120-L128)

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

Source: [app/EasyLanguage/Tables/Texts_To_Simplify_Table.php](EasyLanguage/Tables/Texts_To_Simplify_Table.php), [line 227](EasyLanguage/Tables/Texts_To_Simplify_Table.php#L227-L235)

### `easy_language_simplification_table_used_in`

*Filter the column name.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$column_name` | `string` | The column name.
`$item` | `\easyLanguage\EasyLanguage\Text` | The object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/EasyLanguage/Tables/Texts_In_Use_Table.php](EasyLanguage/Tables/Texts_In_Use_Table.php), [line 133](EasyLanguage/Tables/Texts_In_Use_Table.php#L133-L141)

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

Source: [app/EasyLanguage/Tables/Texts_In_Use_Table.php](EasyLanguage/Tables/Texts_In_Use_Table.php), [line 228](EasyLanguage/Tables/Texts_In_Use_Table.php#L228-L236)

### `easy_language_first_simplify_dialog`

*Filter the dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array<string,mixed>` | The dialog configuration.
`$api_obj` | `\easyLanguage\Plugin\Api_Base` | The used API as an object.
`$post_object` | `\easyLanguage\EasyLanguage\Post_Object` | The Post as an object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/EasyLanguage/Init.php](EasyLanguage/Init.php), [line 491](EasyLanguage/Init.php#L491-L500)

### `easy_language_possible_post_types`

*Filter possible post-types.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$post_types` | `array<string,string>` | The list of possible post-types.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/EasyLanguage/Init.php](EasyLanguage/Init.php), [line 1135](EasyLanguage/Init.php#L1135-L1142)

### `easy_language_first_simplify_dialog`

*Filter the dialog.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$dialog` | `array` | The dialog configuration.
`$api_obj` | `\easyLanguage\Plugin\Api_Base` | The used API as object.
`$post_object` | `\easyLanguage\EasyLanguage\Post_Object` | The Post as object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/EasyLanguage/Init.php](EasyLanguage/Init.php), [line 1434](EasyLanguage/Init.php#L1434-L1443)

### `easy_language_get_object_by_wp_object`

*Filter the resulting easy language object.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$object` | `\easyLanguage\EasyLanguage\Objects\|false` | The easy language object to filter (e.g. WP_Post).
`$wp_object` | `array\|\WP_Post\|\WP_Post_Type\|\WP_Term\|\WP_User\|null` | The original WP object.
`$id` | `int` | The ID of the object.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/EasyLanguage/Init.php](EasyLanguage/Init.php), [line 1479](EasyLanguage/Init.php#L1479-L1488)

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

Source: [app/EasyLanguage/Init.php](EasyLanguage/Init.php), [line 1978](EasyLanguage/Init.php#L1978-L1985)

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

Source: [app/EasyLanguage/Parser_Base.php](EasyLanguage/Parser_Base.php), [line 279](EasyLanguage/Parser_Base.php#L279-L286)

### `easy_language_page_builder_list`

*Filter the list of supported page builders.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$list` | `array<int,string>` | The list of supported page builders.

**Changelog**

Version | Description
------- | -----------
`3.0.0` | Available since 3.0.0.

Source: [app/EasyLanguage/PageBuilders.php](EasyLanguage/PageBuilders.php), [line 208](EasyLanguage/PageBuilders.php#L208-L214)

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

Source: [app/ThirdPartySupport/TranslatePress/TranslatePress_Capito_Machine_Translator.php](ThirdPartySupport/TranslatePress/TranslatePress_Capito_Machine_Translator.php), [line 47](ThirdPartySupport/TranslatePress/TranslatePress_Capito_Machine_Translator.php#L47-L54)

### `easy_language_brizy_text_widgets`

*Filter the possible Brizy widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array<string,mixed>` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/Brizy.php](Parser/Brizy.php), [line 71](Parser/Brizy.php#L71-L78)

### `easy_language_brizy_html_widgets`

*Filter the possible Brizy widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/Brizy.php](Parser/Brizy.php), [line 93](Parser/Brizy.php#L93-L100)

### `easy_language_boldbuilder_text_widgets`

*Filter the shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array<string,mixed>` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/BoldBuilder.php](Parser/BoldBuilder.php), [line 70](Parser/BoldBuilder.php#L70-L77)

### `easy_language_boldbuilder_html_widgets`

*Filter the possible Bold Builder widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array<string,mixed>` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/BoldBuilder.php](Parser/BoldBuilder.php), [line 92](Parser/BoldBuilder.php#L92-L99)

### `easy_language_beaverbuilder_text_widgets`

*Filter the possible BeaverBuilder widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array<string,mixed>` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/BeaverBuilder.php](Parser/BeaverBuilder.php), [line 78](Parser/BeaverBuilder.php#L78-L85)

### `easy_language_beaverbuilder_html_widgets`

*Filter the possible BeaverBuilder widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/BeaverBuilder.php](Parser/BeaverBuilder.php), [line 101](Parser/BeaverBuilder.php#L101-L108)

### `easy_language_themify_text_widgets`

*Filter the possible themify widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array<string,mixed>` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/Themify.php](Parser/Themify.php), [line 82](Parser/Themify.php#L82-L89)

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

Source: [app/Parser/Themify.php](Parser/Themify.php), [line 104](Parser/Themify.php#L104-L111)

### `easy_language_seedprod_text_widgets`

*Filter the possible SeedProd widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array<string,mixed>` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/SeedProd.php](Parser/SeedProd.php), [line 74](Parser/SeedProd.php#L74-L81)

### `easy_language_seedprod_html_widgets`

*Filter the possible SeedProd widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/SeedProd.php](Parser/SeedProd.php), [line 96](Parser/SeedProd.php#L96-L103)

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

Source: [app/Parser/Elementor.php](Parser/Elementor.php), [line 90](Parser/Elementor.php#L90-L97)

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

Source: [app/Parser/Elementor.php](Parser/Elementor.php), [line 112](Parser/Elementor.php#L112-L119)

### `easy_language_siteorigin_text_widgets`

*Filter the possible SiteOrigin widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array<string,mixed>` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/SiteOrigin.php](Parser/SiteOrigin.php), [line 72](Parser/SiteOrigin.php#L72-L79)

### `easy_language_siteorigin_html_widgets`

*Filter the possible Siteorigin widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/SiteOrigin.php](Parser/SiteOrigin.php), [line 94](Parser/SiteOrigin.php#L94-L101)

### `easy_language_wpbakery_text_widgets`

*Filter the shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array<string,mixed>` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/WpBakery.php](Parser/WpBakery.php), [line 79](Parser/WpBakery.php#L79-L86)

### `easy_language_wpbakery_html_widgets`

*Filter the possible WP Bakery widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array<string,mixed>` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/WpBakery.php](Parser/WpBakery.php), [line 101](Parser/WpBakery.php#L101-L108)

### `easy_language_visual_composer_text_widgets`

*Filter the possible Visual Composer widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array<string,mixed>` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/VisualComposer.php](Parser/VisualComposer.php), [line 71](Parser/VisualComposer.php#L71-L78)

### `easy_language_visual_composer_html_widgets`

*Filter the possible Visual Composer widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/VisualComposer.php](Parser/VisualComposer.php), [line 93](Parser/VisualComposer.php#L93-L100)

### `easy_language_breakdance_text_widgets`

*Filter the possible Breakdance widgets.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$widgets` | `array<string,mixed>` | List of widgets.

**Changelog**

Version | Description
------- | -----------
`2.7.0` | Available since 2.7.0.

Source: [app/Parser/Breakdance.php](Parser/Breakdance.php), [line 88](Parser/Breakdance.php#L88-L95)

### `easy_language_breakdance_html_widgets`

*Filter the possible Breakdance widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array<string,mixed>` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.7.0` | Available since 2.7.0.

Source: [app/Parser/Breakdance.php](Parser/Breakdance.php), [line 110](Parser/Breakdance.php#L110-L117)

### `easy_language_kubio_blocks`

*Filter the possible Kubio Blocks.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$blocks` | `array<string,mixed>` | List of Blocks.

**Changelog**

Version | Description
------- | -----------
`2.10.0` | Available since 2.10.0.

Source: [app/Parser/Kubio.php](Parser/Kubio.php), [line 71](Parser/Kubio.php#L71-L78)

### `easy_language_avada_text_widgets`

*Filter the possible Avada shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array<string,mixed>` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/Avada.php](Parser/Avada.php), [line 69](Parser/Avada.php#L69-L76)

### `easy_language_avada_html_widgets`

*Filter the possible Avada widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array<string,mixed>` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/Avada.php](Parser/Avada.php), [line 91](Parser/Avada.php#L91-L98)

### `easy_language_salient_wpbakery_text_widgets`

*Filter the shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array<string,mixed>` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.7.0` | Available since 2.7.0.

Source: [app/Parser/Salients_WpBakery.php](Parser/Salients_WpBakery.php), [line 79](Parser/Salients_WpBakery.php#L79-L86)

### `easy_language_salient_wpbakery_html_widgets`

*Filter the possible WP Bakery widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/Salients_WpBakery.php](Parser/Salients_WpBakery.php), [line 101](Parser/Salients_WpBakery.php#L101-L108)

### `easy_language_divi_text_widgets`

*Filter the possible Divi shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array<string,mixed>` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/Divi.php](Parser/Divi.php), [line 72](Parser/Divi.php#L72-L79)

### `easy_language_divi_html_widgets`

*Filter the possible Divi widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array<string,mixed>` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/Divi.php](Parser/Divi.php), [line 94](Parser/Divi.php#L94-L101)

### `easy_language_avia_text_widgets`

*Filter the possible Avia shortcodes.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$shortcodes` | `array<string,mixed>` | List of shortcodes.

**Changelog**

Version | Description
------- | -----------
`2.6.0` | Available since 2.6.0.

Source: [app/Parser/Avia.php](Parser/Avia.php), [line 71](Parser/Avia.php#L71-L78)

### `easy_language_avia_html_widgets`

*Filter the possible Avia widgets with HTML-support.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$html_support_widgets` | `array<string,mixed>` | List of widgets with HTML-support.

**Changelog**

Version | Description
------- | -----------
`2.6.0` | Available since 2.6.0.

Source: [app/Parser/Avia.php](Parser/Avia.php), [line 93](Parser/Avia.php#L93-L100)

### `easy_language_gutenberg_blocks`

*Filter the possible Gutenberg Blocks.*

**Arguments**

Argument | Type | Description
-------- | ---- | -----------
`$blocks` | `array<string,mixed>` | List of Blocks.

**Changelog**

Version | Description
------- | -----------
`2.0.0` | Available since 2.0.0.

Source: [app/Parser/Gutenberg.php](Parser/Gutenberg.php), [line 76](Parser/Gutenberg.php#L76-L83)


<p align="center"><a href="https://github.com/pronamic/wp-documentor"><img src="https://cdn.jsdelivr.net/gh/pronamic/wp-documentor@main/logos/pronamic-wp-documentor.svgo-min.svg" alt="Pronamic WordPress Documentor" width="32" height="32"></a><br><em>Generated by <a href="https://github.com/pronamic/wp-documentor">Pronamic WordPress Documentor</a> <code>1.2.0</code></em><p>


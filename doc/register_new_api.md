# Add new translation API in this plugin

1. Use the filter-hook _easy_language_register_api_ to add you API like:
   `add_filter( 'easy_language_register_api', 'easy_language_register_your_api');`
2. Load Api in easy_language_register_your_api like: `function easy_language_register_your_api( array $api_list ): array {
   require_once 'class-test_api.php';
   $api_list[] = Your_Api::get_instance();
   return $api_list;
   }
`
3. Create a class "Your_Api" based on the file class-your-api.php
   - This file will contain the main settings for your API.
4. Create a class "Translations" based on the file class-translations.php
   - This file will contain the translation-handling for your API.
5. Change the both files to match your API-requirements.
   - API will be called via Translations->call_api() - adapt this to call your own API.
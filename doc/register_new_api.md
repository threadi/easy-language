# Add new translation API in this plugin

1. Use the filter-hook _easy_language_register_api_ to add you API like:
   `add_filter( 'easy_language_register_api', 'easy_language_register_your_api');`
2. Load Api in easy_language_register_your_api like: `function easy_language_register_your_api( array $api_list ): array {
   require_once 'class-request.php';
   require_once 'class-simplifications.php';
   require_once 'class-your-api.php';
   $api_list[] = \easyLanguage\Apis\Your_Api\Your_Api::get_instance();
   return $api_list;
   }
`
3. Create a class "Your_Api" based on the file [api-base/class-your-api.php](api-base/class-your-api.php)
   - This file will contain the main settings for your API.
   - It extends `easyLanguage\Plugin\Base` and implements `easyLanguage\Plugin\Api_Base` (see app/Plugin/Api_Base.php).
4. Create a class "Simplifications" based on the file [api-base/class-simplifications.php](api-base/class-simplifications.php)
   - This file will contain the simplification-handling for your API.
   - It implements `easyLanguage\Plugin\Api_Simplifications` (see app/Plugin/Api_Simplifications.php).
5. Create a class "Request" based on the file [api-base/class-request.php](api-base/class-request.php)
   - This file will contain the request-handling for your API.
   - It implements `easyLanguage\Plugin\Api_Requests` (see app/Plugin/Api_Requests.php).
6. Change the files to match your API-requirements.
   - API will be called via Simplifications->call_api() - adapt this to call your own API.

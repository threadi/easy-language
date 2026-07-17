import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Define the settings panel to manage the languages.
 *
 * @returns {JSX.Element}
 * @constructor
 */
const EasyLanguagePanel = () => {
	const postId = useSelect(select =>
		select('core/editor').getCurrentPostId()
	);

	const [serverData, setServerData] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);

	/**
	 * Get the data for this object from the server.
	 */
	useEffect(() => {
		if (!postId) return;

		setIsLoading(true);
		apiFetch({
			path: `/easy-language/v1/language-options/${postId}`,
			method: 'POST',
		})
			.then(data => {
				setServerData(data);
				setIsLoading(false);
			})
			.catch(err => {
				setError(err.message);
				setIsLoading(false);
			});
	}, [postId]);

	return (
		<PluginDocumentSettingPanel name="easy-language-panel" title={__( 'Easy Language', 'easy-language' )}>
			{isLoading && <p>{ __( 'Loading', 'easy-language' )}</p>}
			{error && <p style={{ color: 'red' }}>Fehler: {error}</p>}
			{serverData && (
				<div dangerouslySetInnerHTML={{ __html: serverData.html }} />
			)}
		</PluginDocumentSettingPanel>
	);
};
registerPlugin('my-settings-panel', { render: EasyLanguagePanel });

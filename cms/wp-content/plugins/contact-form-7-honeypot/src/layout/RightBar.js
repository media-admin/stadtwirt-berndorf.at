import { __ } from '@wordpress/i18n';
import CF7AppsRecommendedPlugins from '../components/CF7AppsRecommendedPlugins';
import CF7AppsQuickAccess from '../components/CF7AppsQuickAccess';

const RightBar = () => {
	return (
		<aside className="cf7apps-right-bar" aria-label={ __( 'Dashboard sidebar', 'cf7apps' ) }>
			<div className="cf7apps-dashboard-sidebar">
				<CF7AppsRecommendedPlugins />
				<div className="cf7apps-sidebar-block">
					<CF7AppsQuickAccess />
				</div>
			</div>
		</aside>
	);
};

export default RightBar;
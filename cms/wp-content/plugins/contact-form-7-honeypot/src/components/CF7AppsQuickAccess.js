import { __ } from "@wordpress/i18n";

const QUICK_LINKS = [
	{
		id: "support",
		label: __( "Open Support Ticket", "cf7apps" ),
		url: "https://objectsws.atlassian.net/servicedesk/customer/portal/272",
		icon: "support.svg",
	},
	{
		id: "help",
		label: __( "Help Center", "cf7apps" ),
		url: "https://cf7apps.com/docs/?utm_source=plugin&utm_medium=dashboard&utm_campaign=help",
		icon: "help-center.svg",
	},
	{
		id: "review",
		label: __( "Leave Us a Review", "cf7apps" ),
		url: "https://wordpress.org/support/plugin/contact-form-7-honeypot/reviews/#new-post",
		icon: "review.svg",
	},
];

const CF7AppsQuickAccess = () => {
	const assetsBase = CF7Apps?.assetsURL || "";
	const iconUrl = ( file ) =>
		file && assetsBase
			? `${ assetsBase }/images/dashboard/quick-access/${ file }`
			: "";

	return (
		<section className="cf7apps-quick-access" aria-label={ __( "Quick Access", "cf7apps" ) }>
			<h3 className="cf7apps-sidebar-block__title">
				{ __( "Quick Access", "cf7apps" ) }
			</h3>
			<ul className="cf7apps-quick-access__list">
				{ QUICK_LINKS.map( ( link ) => (
					<li key={ link.id }>
						<a
							className="cf7apps-quick-access__item"
							href={ link.url }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ assetsBase && (
								<img
									className="cf7apps-quick-access__icon"
									src={ iconUrl( link.icon ) }
									alt=""
									width={ 14 }
									height={ 14 }
								/>
							) }
							<span>{ link.label }</span>
						</a>
					</li>
				) ) }
			</ul>
		</section>
	);
};

export default CF7AppsQuickAccess;

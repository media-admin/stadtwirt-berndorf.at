const CF7AppsAppSettingsTabs = ( { tabs, activeTab, onChange } ) => {
	const tabEntries = Object.entries( tabs || {} );

	return (
		<div className="cf7apps-app-settings-tabs" role="tablist">
			{ tabEntries.map( ( [ tabKey, label ], tabIndex ) => {
				const tabValue = `${ tabIndex + 1 }`;

				return (
					<button
						key={ tabKey }
						type="button"
						role="tab"
						className={
							activeTab === tabValue
								? 'cf7apps-app-settings-tabs__tab is-active'
								: 'cf7apps-app-settings-tabs__tab'
						}
						aria-selected={ activeTab === tabValue }
						onClick={ () => onChange( tabValue ) }
					>
						{ label }
					</button>
				);
			} ) }
		</div>
	);
};

export default CF7AppsAppSettingsTabs;

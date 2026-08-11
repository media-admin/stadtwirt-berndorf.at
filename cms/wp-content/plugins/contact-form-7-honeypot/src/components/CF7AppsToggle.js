import CF7AppsHelpText from "./CF7AppsHelpText";

const CF7AppsToggle = ( {
	label,
	isSelected,
	help,
	onChange,
	className,
	name,
	disabled,
} ) => {
	const handleToggle = () => {
		if ( disabled || ! onChange ) {
			return;
		}

		onChange( ! isSelected );
	};

	return (
		<div className="cf7apps-form-group">
			<div className="cf7apps-settings-toggle">
				<label htmlFor={ name }>{ label }</label>
				<button
					type="button"
					id={ name }
					className={
						isSelected
							? `cf7apps-app-toggle is-on ${ className || '' }`.trim()
							: `cf7apps-app-toggle ${ className || '' }`.trim()
					}
					role="switch"
					aria-checked={ isSelected }
					aria-label={ label }
					onClick={ handleToggle }
					disabled={ disabled }
				>
					<span className="cf7apps-app-toggle__thumb" />
				</button>
			</div>
			<CF7AppsHelpText description={ help } />
		</div>
	);
};

export default CF7AppsToggle;

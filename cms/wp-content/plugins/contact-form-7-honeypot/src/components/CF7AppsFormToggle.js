import CF7AppsHelpText from './CF7AppsHelpText';

const CF7AppsFormToggle = ( {
	label,
	isSelected,
	help,
	onChange,
	disabled,
} ) => {
	const handleToggle = () => {
		if ( disabled || ! onChange ) {
			return;
		}

		onChange( ! isSelected );
	};

	return (
		<div className="cf7apps-form-toggle-block">
			<div className="cf7apps-form-toggle-block__row">
				<span className="cf7apps-form-toggle-block__label">{ label }</span>
				<button
					type="button"
					className={
						isSelected
							? 'cf7apps-settings-switch is-on'
							: 'cf7apps-settings-switch'
					}
					role="switch"
					aria-checked={ isSelected }
					aria-label={ label }
					onClick={ handleToggle }
					disabled={ disabled }
				>
					<span className="cf7apps-settings-switch__track" aria-hidden="true" />
					<span className="cf7apps-settings-switch__knob" aria-hidden="true" />
				</button>
			</div>
			{ help && (
				<div className="cf7apps-form-toggle-block__help">
					<CF7AppsHelpText description={ help } />
				</div>
			) }
		</div>
	);
};

export default CF7AppsFormToggle;

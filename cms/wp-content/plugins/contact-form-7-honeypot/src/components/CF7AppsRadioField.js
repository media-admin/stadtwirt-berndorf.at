import CF7AppsSelectField from './CF7AppsSelectField';
import CF7AppsTextField from './CF7AppsTextField';

const CF7AppsRadioField = ( {
	label,
	className,
	options,
	name,
	onChange,
	value,
	subFields,
	disabled,
	variant,
} ) => {
	const isAppShell = variant === 'app';
	const wrapperClass = [
		'cf7apps-form-group',
		isAppShell ? 'cf7apps-app-radio-field' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className={ wrapperClass }>
			<div className={ isAppShell ? 'cf7apps-app-radio-field__inner' : 'cf7apps-settings-radio' }>
				{ label && (
					<label className={ isAppShell ? 'cf7apps-app-radio-field__title' : undefined }>
						{ ! isAppShell && <>{ label }</> }
						{ isAppShell && label }
					</label>
				) }
				<div className={ isAppShell ? 'cf7apps-app-radio-field__options' : `cf7apps-app-switch ${ className || '' }` }>
					{ Object.keys( options ).map( ( optionKey ) => (
						<div
							key={ optionKey }
							className={
								isAppShell
									? 'cf7apps-app-radio-field__option'
									: 'cf7apps__radio-container'
							}
						>
							<label htmlFor={ optionKey }>
								<input
									type="radio"
									id={ optionKey }
									name={ name }
									value={ optionKey }
									onChange={ onChange }
									checked={ value === optionKey }
									disabled={ disabled }
								/>
								{ options[ optionKey ] }
							</label>
						</div>
					) ) }
					{ subFields && (
						<div className={ isAppShell ? 'cf7apps-app-radio-field__subfield' : undefined } style={ ! isAppShell ? { marginTop: '5px' } : undefined }>
							{ 'select' === subFields.type && (
								<CF7AppsSelectField
									options={ subFields.options }
									selected={ subFields.selected }
									name={ value }
									onChange={ onChange }
									className={ isAppShell ? 'cf7apps-app-select-field' : 'regular-text' }
									disabled={ disabled }
									description={ subFields.help }
									required={ subFields.required }
									variant={ variant }
								/>
							) }

							{ 'text' === subFields.type && (
								<CF7AppsTextField
									name={ value }
									value={ subFields.value }
									onChange={ onChange }
									className={ isAppShell ? 'cf7apps-app-select-field' : 'regular-text' }
									placeholder={ subFields.placeholder }
									disabled={ disabled }
									description={ subFields.help }
									required={ subFields.required }
									variant={ variant }
								/>
							) }
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default CF7AppsRadioField;

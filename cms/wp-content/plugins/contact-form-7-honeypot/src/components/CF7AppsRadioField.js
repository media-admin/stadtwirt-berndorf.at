import CF7AppsSelectField from './CF7AppsSelectField';
import CF7AppsTextField from './CF7AppsTextField';

const CF7AppsRadioField = ( { label, className, options, name, onChange, value, subFields, disabled } ) => {
    return (
        <div className={ 'cf7apps-form-group' }>
            <div className={ 'cf7apps-settings-radio' }>
                <label>{ label }</label>
                <div className={ `cf7apps-app-switch ${ className }` }>
                    {
                        Object.keys( options ).map( ( optionKey ) => {

                            return (
                                <div className={ 'cf7apps__radio-container' }>
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
                            );
                        } )
                    }
                    {
                        subFields && (
                            <div style={ { marginTop: '5px' } }>
                                {
                                    'select' === subFields.type && (
                                        <>
                                            <CF7AppsSelectField
                                                options={ subFields.options }
                                                selected={ subFields.selected }
                                                name={ value }
                                                onChange={ onChange }
                                                className={ 'regular-text' }
                                                disabled={ disabled }
                                                description={ subFields.help }
                                                required={ subFields.required }
                                            />
                                        </>
                                    )
                                }

                                {
                                    'text' === subFields.type && (
                                        <CF7AppsTextField
                                            name={ value }
                                            value={ subFields.value }
                                            onChange={ onChange }
                                            className={ 'regular-text' }
                                            placeholder={ subFields.placeholder }
                                            disabled={ disabled }
                                            description={ subFields.help }
                                            required={ subFields.required }
                                        />
                                    )
                                }
                            </div>
                        )
                    }
                </div>
            </div>
        </div>
    );
}

export default CF7AppsRadioField;

import { __ } from "@wordpress/i18n";
import CF7AppsHelpText from "./CF7AppsHelpText";

const CF7AppsTextField = ({ label, value, description, onChange, className, placeholder, name, required, disabled }) => {
    const isLabelInline = (className || '').indexOf('label-inline') !== -1;

    if ( isLabelInline ) {
        return (
            <div className="cf7apps-form-group cf7apps-settings" style={ { display: 'flex', alignItems: 'center', gap: '16px' } }>
                <div style={ { minWidth: '200px' } }><label><b>{label}</b></label></div>
                <div style={ { flex: 1 } }>
                    <input
                        type="text"
                        value={value}
                        onChange={onChange}
                        name={name}
                        className={`cf7apps-form-input ${className}`}
                        placeholder={placeholder}
                        {...required ? { required: true } : {}}
                        disabled={ disabled }
                    />
                    <CF7AppsHelpText description={description} />
                </div>
            </div>
        );
    }

    return (
        <div className="cf7apps-form-group cf7apps-settings">
            <div>
                <label><b>{label}</b></label>
            </div>
            <div>
                <input 
                    type="text" 
                    value={value} 
                    onChange={onChange} 
                    name={name}
                    className={`cf7apps-form-input ${className}`} 
                    placeholder={placeholder}
                    {...required ? { required: true } : {}}
                    disabled={ disabled }
                />
            </div>
            <CF7AppsHelpText description={description} />
        </div>
    );
}

export default CF7AppsTextField;
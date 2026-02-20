import { __ } from "@wordpress/i18n";
import CF7AppsHelpText from "./CF7AppsHelpText";

const CF7AppsSelectField = ({ label, selected, description, onChange, className = '', options, name, disabled }) => {
    // if caller includes 'inline-select' in className we render the wrapper inline so multiple selects
    // can appear on the same row. This keeps the change local and opt-in from PHP settings by
    // adding 'inline-select' to the field's class value.
    const isInline = className.indexOf('inline-select') !== -1;
    const isLabelInline = (className || '').indexOf('label-inline') !== -1;
    const wrapperStyle = isInline ? { display: 'inline-block', marginRight: '14px', verticalAlign: 'top' } : {};

    // Chevron icon SVG
    const ChevronIcon = ({ isOpen = false }) => (
        <svg 
            width="12" 
            height="12" 
            viewBox="0 0 12 12" 
            fill="none" 
            xmlns="http://www.w3.org/2000/svg"
            style={{ 
                position: 'absolute',
                right: '10px',
                top: '50%',
                transform: 'translateY(-50%)',
                pointerEvents: 'none',
                transition: 'transform 0.2s ease'
            }}
        >
            <path 
                d={isOpen ? "M3 7L6 4L9 7" : "M3 5L6 8L9 5"} 
                stroke="#666" 
                strokeWidth="1.5" 
                strokeLinecap="round" 
                strokeLinejoin="round"
            />
        </svg>
    );

    const selectWrapperStyle = {
        position: 'relative',
        display: 'inline-block',
        // width: '100%'
    };

    if ( isLabelInline ) {
        return (
            <div className="cf7apps-form-group cf7apps-settings" style={ { display: 'flex', alignItems: 'center', gap: '16px', width: isInline ? 'auto' : '100%' } }>
                <div style={ { minWidth: '200px' } }><label><b>{label}</b></label></div>
                <div style={ { flex: 1 } }>
                    <div style={selectWrapperStyle}>
                        <select
                            className={`cf7apps-form-input ${className}`} 
                            name={name}
                            onChange={onChange}
                            defaultValue={selected}
                            disabled={disabled}
                            style={{
                                appearance: 'none',
                                WebkitAppearance: 'none',
                                MozAppearance: 'none',
                                paddingRight: '30px',
                                backgroundImage: 'none'
                            }}
                        >
                            {
                                Object.keys(options).map((key, index) => {
                                    return (
                                        <option 
                                            key={index} 
                                            value={key}
                                        >
                                            {options[key]}
                                        </option>
                                    )
                                })
                            }
                        </select>
                        <ChevronIcon isOpen={false} />
                    </div>
                    <CF7AppsHelpText description={description} />
                </div>
            </div>
        );
    }

    return (
        <div className="cf7apps-form-group cf7apps-settings" style={ wrapperStyle }>
            <div>
                <label><b>{label}</b></label>
            </div>
            <div>
                <div style={selectWrapperStyle}>
                    <select
                        className={`cf7apps-form-input ${className}`} 
                        name={name}
                        onChange={onChange}
                        defaultValue={selected}
                        disabled={disabled}
                        style={{
                            appearance: 'none',
                            WebkitAppearance: 'none',
                            MozAppearance: 'none',
                            paddingRight: '30px',
                            backgroundImage: 'none'
                        }}
                    >
                        {
                            Object.keys(options).map((key, index) => {
                                return (
                                    <option 
                                        key={index} 
                                        value={key}
                                    >
                                        {options[key]}
                                    </option>
                                )
                            })
                        }
                    </select>
                    <ChevronIcon isOpen={false} />
                </div>
            </div>
            <CF7AppsHelpText description={description} />
        </div>
    );
}

export default CF7AppsSelectField;
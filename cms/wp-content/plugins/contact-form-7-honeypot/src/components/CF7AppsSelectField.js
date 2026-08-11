import CF7AppsHelpText from "./CF7AppsHelpText";

const CF7AppsSelectField = ({ label, selected, description, onChange, className = '', options, name, disabled, variant }) => {
    const isAppShell = variant === 'app';
    const optionKeys = Object.keys(options || {});
    const selectValue =
        selected !== undefined && selected !== null && String(selected) !== ''
            ? String(selected)
            : (optionKeys[0] ?? '');
    // if caller includes 'inline-select' in className we render the wrapper inline so multiple selects
    // can appear on the same row. This keeps the change local and opt-in from PHP settings by
    // adding 'inline-select' to the field's class value.
    const isInline = className.indexOf('inline-select') !== -1;
    const isLabelInline = (className || '').indexOf('label-inline') !== -1;
    const wrapperStyle = isInline ? { display: 'inline-block', marginRight: '14px', verticalAlign: 'top' } : {};

    // Chevron icon SVG (fallback; primary chevron is CSS background-image on the select)
    const ChevronIcon = () => (
        <svg
            className="cf7apps-select-chevron"
            width="12"
            height="12"
            viewBox="0 0 12 12"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                d="M3 5L6 8L9 5"
                stroke="#666"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );

    const selectWrapperStyle = isAppShell
        ? { position: 'relative', display: 'block', width: '100%' }
        : { position: 'relative', display: 'inline-block' };

    const selectChromeStyle = {
        appearance: 'none',
        WebkitAppearance: 'none',
        MozAppearance: 'none',
        paddingRight: '30px',
    };

    if (isLabelInline) {
        return (
            <div className="cf7apps-form-group cf7apps-settings" style={{ display: 'flex', alignItems: 'center', gap: '16px', width: isInline ? 'auto' : '100%' }}>
                <div style={{ minWidth: '200px' }}><label><b>{label}</b></label></div>
                <div style={{ flex: 1 }}>
                    <div className="cf7apps-select-wrap" style={selectWrapperStyle}>
                        <select
                            className={`cf7apps-form-input ${className}`}
                            name={name}
                            onChange={onChange}
                            value={selectValue}
                            disabled={disabled}
                            style={selectChromeStyle}
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
                        <ChevronIcon />
                    </div>
                    <CF7AppsHelpText description={description} />
                </div>
            </div>
        );
    }

    return (
        <div className={`cf7apps-form-group cf7apps-settings${isAppShell ? ' cf7apps-app-field-group cf7apps-app-select-group' : ''}`} style={wrapperStyle}>
            {label && (
                isAppShell ? (
                    <label className="cf7apps-app-field-label">{label}</label>
                ) : (
                    <div>
                        <label><b>{label}</b></label>
                    </div>
                )
            )}
            <div>
                <div className="cf7apps-select-wrap" style={selectWrapperStyle}>
                    <select
                        className={`cf7apps-form-input ${className}`}
                        name={name}
                        onChange={onChange}
                        value={selectValue}
                        disabled={disabled}
                        style={
                            isAppShell
                                ? {
                                    ...selectChromeStyle,
                                    width: '100%',
                                }
                                : selectChromeStyle
                        }
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
                    <ChevronIcon />
                </div>
            </div>
            <CF7AppsHelpText description={description} />
        </div>
    );
}

export default CF7AppsSelectField;

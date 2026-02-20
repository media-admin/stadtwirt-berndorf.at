import { useEffect, useState, useCallback } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import CF7AppsSkeletonLoader from "../components/CF7AppsSkeletonLoader";
import { useParams } from "react-router";
import { getApps, saveSettings } from "../api/api";
import { TabContext, TabList, TabPanel } from "@mui/lab";
import { Box, Tab } from "@mui/material";
import CF7AppsToggle from "../components/CF7AppsToggle";
import CF7AppsTextField from "../components/CF7AppsTextField";
import CF7AppsNumberField from "../components/CF7AppsNumberField";
import CF7AppsRadioField from "../components/CF7AppsRadioField";
import CF7AppsHelpText from "../components/CF7AppsHelpText";
import { Button } from "@wordpress/components";
import CF7AppsTemplates from "../templates/CF7AppsTemplates";
import { toast } from 'react-toastify';
import parse from 'html-react-parser';
import CF7AppsSelectField from "../components/CF7AppsSelectField";
import CF7AppsNotice from "../components/CF7AppsNotice";

const TextareaField = ({ fieldKey, field, className, description, disabled, openMap, setOpenMap, formData, handleInputChange }) => {
    const open = (openMap && openMap[ fieldKey ] !== undefined) ? openMap[ fieldKey ] : ! field.collapsible;

    const toggleOpen = () => {
        setOpenMap( prev => ( {
            ...prev,
            [ fieldKey ]: ! prev[ fieldKey ]
        } ) );
    };

    // Chevron icon SVG
    const ChevronIcon = ({ isOpen = false }) => (
        <svg 
            width="12" 
            height="12" 
            viewBox="0 0 12 12" 
            fill="none" 
            xmlns="http://www.w3.org/2000/svg"
            style={{ 
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

    return (
        <div className="cf7apps-form-group cf7apps-settings">
            { field.collapsible ? (
                <div 
                    style={ { 
                        display: 'flex', 
                        alignItems: 'center', 
                        justifyContent: 'space-between', 
                        gap: '8px', 
                        width: '500px',
                        padding: '10px 12px',
                        backgroundColor: field.collapsed_button_color ? `${field.collapsed_button_color}20` : '#e8f4f8',
                        borderRadius: '4px',
                        cursor: disabled ? 'not-allowed' : 'pointer',
                        opacity: disabled ? 0.6 : 1,
                        border: 'none',
                        transition: 'background-color 0.2s ease'
                    } }
                    onClick={ toggleOpen }
                    onMouseEnter={(e) => {
                        if (!disabled) {
                            e.currentTarget.style.backgroundColor = field.collapsed_button_color ? `${field.collapsed_button_color}30` : '#d4e8f0';
                        }
                    }}
                    onMouseLeave={(e) => {
                        e.currentTarget.style.backgroundColor = field.collapsed_button_color ? `${field.collapsed_button_color}20` : '#e8f4f8';
                    }}
                    role="button"
                    tabIndex={0}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            toggleOpen();
                        }
                    }}
                    aria-expanded={ !! open }
                >
                    <div><label style={{ margin: 0, cursor: disabled ? 'not-allowed' : 'pointer', fontWeight: '500' }}>{ field.title }</label></div>
                    <ChevronIcon isOpen={open} />
                </div>
            ) : (
                <div><label><b>{ field.title }</b></label></div>
            ) }

            { open && (
                <div style={ { marginTop: '10px' } }>
                    { field.pre_text && (
                        <div className="cf7apps-pre-text" style={ { marginBottom: '8px', color: '#444' } }>
                            { parse( String( field.pre_text ) ) }
                        </div>
                    ) }
                    <textarea
                        className={ `cf7apps-form-input ${ className }` }
                        name={ fieldKey }
                        value={ formData[ fieldKey ] || '' }
                        onChange={ handleInputChange }
                        rows={ 3 }
                        disabled={ disabled }
                        style={ { width: '500px', minHeight: '120px', boxSizing: 'border-box', padding: '12px' } }
                    ></textarea>

                    { field.post_text && (
                        <div className="cf7apps-post-text" style={ { width: '500px',marginTop: '8px', color: '#444' } }>
                            { parse( String( field.post_text ) ) }
                        </div>
                    ) }

                    <CF7AppsHelpText description={ description } />
                </div>
            ) }
        </div>
    );
};

const AppSettings = () => {
    let { app } = useParams();

    const [appSettings, setAppSettings] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [tabValue, setTabValue] = useState('1');
    const [hasTabs, setHasTabs] = useState(false);
    const [showAcfNotice, setShowAcfNotice] = useState(false);
    const [formData, setFormData] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [notice, setNotice] = useState({ show: false, text: '' });
    const [ openMap, setOpenMap ] = useState( {} );

    // Scroll to top when ACF notice is shown
    useEffect(() => {
        if (showAcfNotice && appSettings && appSettings.requires_acf && !appSettings.acf_available) {
            // Use setTimeout to ensure DOM has updated
            setTimeout(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
        }
    }, [showAcfNotice]);

    useEffect(() => {

        // need to set the tab value from the url hash
        const hash = window.location.hash;
        const explodedHash = hash.split('/');
        if(explodedHash.length > 3) {
            setTabValue(explodedHash[3]);
        }

        async function fetchAppSettings() {
            if(app !== undefined) {
                setIsLoading(true);
                
                const appSettings = await getApps(app);
                
                if (!appSettings) {
                    setIsLoading(false);
                    return;
                }
                
                let hasTabs = Object.keys(appSettings.setting_tabs).length > 0 ? true : false;
                let _formData = {};
                
                setHasTabs(hasTabs);

                if(hasTabs) {
                    let settingsTabs = appSettings['setting_tabs'];
                    let settings = appSettings['admin_settings']['general'];

                    Object.keys(settingsTabs).map((tabKey, tabIndex) => {
                        Object.keys(settings['fields'][tabKey]).map((fieldKey, fieldIndex) => {
                            if(fieldKey !== 'template') {
                                if( settings['fields'][tabKey][fieldKey].type === 'text' || settings['fields'][tabKey][fieldKey].type === 'number' || 'radio' === settings['fields'][tabKey][fieldKey].type ) {
                                    if ( settings['fields'][tabKey][fieldKey].value !== undefined ) {
                                        _formData[fieldKey] = settings['fields'][tabKey][fieldKey].value;
                                    } else {
                                        _formData[fieldKey] = settings['fields'][tabKey][fieldKey].default;
                                    }
                                }
                                else if(settings['fields'][tabKey][fieldKey].type === 'checkbox') {
                                    _formData[fieldKey] = settings['fields'][tabKey][fieldKey].checked;
                                } else if ( settings['fields'][tabKey][fieldKey].type === 'textarea' ) {
                                    _formData[fieldKey] = settings['fields'][tabKey][fieldKey].value !== undefined ? settings['fields'][tabKey][fieldKey].value : (settings['fields'][tabKey][fieldKey].default ? settings['fields'][tabKey][fieldKey].default : '');
                                }
                            }
                        });
                    });
                }
                else {
                    let settings = appSettings['admin_settings']['general'];

                    Object.keys(settings['fields']).map((fieldKey, fieldIndex) => {
                        if(fieldKey !== 'template') {
                            let field = settings['fields'][ fieldKey ];
                            if( field.type === 'text' || field.type === 'number' || 'radio' === field.type ) {
                                if ( field.value ) {
                                    _formData[fieldKey] = field.value;
                                } else {
                                    _formData[fieldKey] = field.default;
                                }
                            } else if( field.type === 'checkbox' ) {
                                if ( field.checked ) {
                                    _formData[fieldKey] = field.checked;
                                } else {
                                    _formData[fieldKey] = field.default;
                                }
                            } else if ( field.type === 'textarea' ) {
                                _formData[fieldKey] = field.value !== undefined ? field.value : (field.default ? field.default : '');
                            }

                            if ( settings['fields'][ fieldKey ].sub_fields ) {
                                Object.keys( settings['fields'][ fieldKey ].sub_fields ).map( ( subFieldKey ) => {
                                    const subField = settings['fields'][ fieldKey ].sub_fields[ subFieldKey ];
                                    if ( subField.type === 'text' || subField.type === 'number' ) {
                                        if ( subField.value ) {
                                            _formData[ subFieldKey ] = subField.value;
                                        } else {
                                            _formData[ subFieldKey ] = subField.default;
                                        }
                                    } else if ( subField.type === 'checkbox' ) {
                                        if ( subField.checked ) {
                                            _formData[ subFieldKey ] = subField.checked;
                                        } else {
                                            _formData[ subFieldKey ] = subField.default;
                                        }
                                    } else if ( 'select' === subField.type ) {
                                        if ( subField.selected ) {
                                            _formData[ subFieldKey ] = subField.selected;
                                        } else {
                                            _formData[ subFieldKey ] = subField.default;
                                        }
                                    }
                                } );
                            }
                        }
                    });
                }
                setFormData(_formData);
                // initialize openMap for textarea fields across tabs
                let _openMap = {};
                if ( hasTabs ) {
                    Object.keys(appSettings.setting_tabs).map((tabKey) => {
                        const tabSettings = appSettings.admin_settings['general']['fields'][tabKey];
                        Object.keys(tabSettings).map((fieldKey) => {
                            const field = tabSettings[fieldKey];
                            if ( field && field.type === 'textarea' ) {
                                _openMap[fieldKey] = ! field.collapsible;
                            }
                        });
                    });
                } else {
                    const settings = appSettings.admin_settings['general']['fields'];
                    Object.keys(settings).map((fieldKey) => {
                        const field = settings[fieldKey];
                        if ( field && field.type === 'textarea' ) {
                            _openMap[fieldKey] = ! field.collapsible;
                        }
                    });
                }
                setOpenMap( _openMap );
                setAppSettings(appSettings);
                setIsLoading(false);
            }
        }
        
        const timer = setTimeout(() => {
            fetchAppSettings();
        }, 1);

        return () => clearTimeout(timer);
    }, [app]);

    /**
     * Handles the input change event.
     * 
     * @param {Object} e - The event object.
     * 
     * @returns {void}
     * 
     * @since 3.0.0
     */
    const handleInputChange = (e) => {
        const { name, value, required } = e.target;
        setFormData((prev) => ({
            ...prev,
            [name]: value
        }));
    }

    /**
     * Saves the app settings.
     * 
     * @returns {void}
     * 
     * @since 3.0.0
     */
    const saveAppSettings = async () => {
        let missingRequired = false;
        let requiredMessage = '';

        // Check if the app is enabled (adjust the key if needed)
        const isEnabled = ( formData.is_enabled === undefined || formData.is_enabled === false ) ? false : true;

        // Check if ACF is required but not available when trying to enable
        if (isEnabled && appSettings.requires_acf && !appSettings.acf_available) {
            toast.error( __( 'This integration requires the Advanced Custom Fields plugin to be installed and active.', 'cf7apps' ) );
            return;
        }

        // Only validate required fields if app is enabled
        if (isEnabled) {
            if (hasTabs) {
                Object.keys(appSettings.setting_tabs).some((tabKey) => {
                    const tabSettings = appSettings.admin_settings['general']['fields'][tabKey];
                    return Object.keys(tabSettings).some((fieldKey) => {
                        const field = tabSettings[fieldKey];
                        if (field && field.required && (formData[fieldKey] === '' || formData[fieldKey] === undefined)) {
                            missingRequired = true;
                            requiredMessage = field.required_message || __( 'Please fill all required fields.', 'cf7apps' );
                            return true;
                        }
                        return false;
                    });
                });
            } else {
                const fields = appSettings.admin_settings['general']['fields'];
                Object.keys(fields).some((fieldKey) => {
                    const field = fields[fieldKey];
                    if (field && field.required && (formData[fieldKey] === '' || formData[fieldKey] === undefined)) {
                        missingRequired = true;
                        requiredMessage = field.required_message || __( 'Please fill all required fields.', 'cf7apps' );
                        return true;
                    } else {

                        if (field && field.sub_fields) {


                            if ( field.sub_fields[ formData[ fieldKey ] ] && field.sub_fields[ formData[ fieldKey ] ].required && (formData[ formData[ fieldKey ] ] === '' || formData[ formData[ fieldKey ] ] === undefined)) {
                                missingRequired = true;
                                requiredMessage = field.sub_fields[ formData[ fieldKey ] ].required_message || __( 'Please fill all required fields.', 'cf7apps' );
                                return true;
                            }

                        }

                    }
                    return false;
                });
            }

            if (missingRequired) {
                setNotice({ show: true, text: requiredMessage });
                toast.error( __( 'Error! Please fill all required fields.', 'cf7apps' ) );
                setIsSaving(false);
                return;
            }
        }

        // Validate webhook URL format (only for webhook app)
        if ( app === 'webhook' && isEnabled ) {
            const webhookUrl = formData['webhook_url'] ? formData['webhook_url'].trim() : '';
            
            // Check if webhook URL is empty
            if ( webhookUrl === '' ) {
                const errorMessage = __( 'Webhook URL cannot be empty.', 'cf7apps' );
                setNotice({ show: true, text: errorMessage });
                toast.error( errorMessage );
                setIsSaving(false);
                return;
            }
            
            // Check if webhook URL has correct format
            if ( !webhookUrl.startsWith('http://') && !webhookUrl.startsWith('https://') ) {
                const errorMessage = __( 'Webhook URL must start with http:// or https://', 'cf7apps' );
                setNotice({ show: true, text: errorMessage });
                toast.error( errorMessage );
                setIsSaving(false);
                return;
            }
        }

        setNotice({ show: false, text: '' });
        setIsSaving(true);

        const response = await saveSettings(app, formData);

        if(response) {
            toast.success( __( 'Great! Settings Saved Successfully', 'cf7apps' ) );
        }
        else {
            toast.error( __( 'Error! Something Went Wrong', 'cf7apps' ) );
        }

        setIsSaving(false);
    }

    const handleTabChange = ( e, newValue ) => {
        setTabValue(newValue);
        const explodedHash = window.location.hash.split('/');
        explodedHash[3] = newValue;
        window.location.hash = explodedHash.join('/');
    };

    /**
     * Settings of the App.
     * 
     * @returns {JSX.Element}
     * 
     * @since 3.0.0
     */

    const Settings = () => {
        if(hasTabs) {
            // Tabs
            return (
                <div className="cf7apps-form">
                    <TabContext value={tabValue}>
                        <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                            <TabList onChange={ handleTabChange } className="cf7apps-settings-tablist">
                                {
                                    Object.keys(appSettings.setting_tabs).map((tabKey, tabIndex) => {
                                        return (
                                            <Tab
                                                key={ tabKey }
                                                label={ appSettings.setting_tabs[tabKey] }
                                                value={`${tabIndex + 1}`}
                                                className="cf7apps-settings-tab"
                                            />
                                        )
                                    })
                                }
                            </TabList>
                        </Box>
                        {
                            Object.keys(appSettings.setting_tabs).map((tabKey, tabIndex) => {
                                const tabSettings = appSettings.admin_settings['general']['fields'][tabKey];

                                return (
                                    <TabPanel key={ tabKey } value={`${tabIndex + 1}`}>
                                        {
                                            Object.keys(tabSettings).map((fieldKey, fieldIndex) => {
                                                const field = tabSettings[fieldKey];
                                                const className = field.class === undefined ? '' : field.class;
                                                const help = field.help;
                                                const palceholder = field.placeholder === undefined ? '' : field.placeholder;

                                                if(field.type === 'notice') {
                                                    return (
                                                        <CF7AppsNotice
                                                            key={ fieldKey }
                                                            type={className}
                                                            text={field.text}
                                                        />
                                                    )
                                                }
                                                else if(fieldKey === 'title') {
                                                    return (
                                                        <h3 key={ fieldKey }>{tabSettings.title}</h3>
                                                    )
                                                }
                                                else if(fieldKey === 'description') {
                                                    return (
                                                        <p key={ fieldKey } className="cf7apps-help-text">{tabSettings.description}</p>
                                                    )
                                                }
                                                else if(field.type === 'text') {
                                                    return (
                                                        <CF7AppsTextField
                                                            key={ fieldKey }
                                                            label={field.title}
                                                            description={ parse( String(field.description) ) }
                                                            className={className}
                                                            placeholder={palceholder}
                                                            value={formData[fieldKey]}
                                                            name={fieldKey}
                                                            onChange={handleInputChange}
                                                            required={field.required}
                                                        />
                                                    )
                                                }
                                                else if(field.type === 'number') {
                                                    return (
                                                        <CF7AppsNumberField
                                                            key={ fieldKey }
                                                            label={field.title}
                                                            description={ parse( String(field.description) ) }
                                                            className={className}
                                                            name={fieldKey}
                                                            placeholder={palceholder}
                                                            value={formData[fieldKey]}
                                                            onChange={handleInputChange}
                                                            min={field.min}
                                                        />
                                                    )
                                                } 
                                                else if(field.type === 'checkbox') {
                                                    return (
                                                        <CF7AppsToggle
                                                            key={ fieldKey }
                                                            help={help} 
                                                            label={field.title}
                                                            className={className}
                                                            isSelected={formData[fieldKey]}
                                                            description={ parse( String(field.description) ) }
                                                            disabled={field.disabled}
                                                            onChange={(e) => {
                                                                // Show red warning notice if trying to enable without ACF
                                                                if (fieldKey === 'is_enabled' && 
                                                                    appSettings.requires_acf && 
                                                                    !appSettings.acf_available && 
                                                                    !formData[fieldKey]) {
                                                                    // Reset notice state to ensure useEffect triggers
                                                                    setShowAcfNotice(false);
                                                                    // Use setTimeout to ensure state reset before setting to true
                                                                    setTimeout(() => {
                                                                        setShowAcfNotice(true);
                                                                        // Scroll to top immediately
                                                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                                                    }, 10);
                                                                    // Hide notice after 5 seconds
                                                                    setTimeout(() => setShowAcfNotice(false), 5000);
                                                                    return;
                                                                }
                                                                // Hide notice if enabling successfully
                                                                setShowAcfNotice(false);
                                                                setFormData({
                                                                    ...formData,
                                                                    [fieldKey]: ! formData[fieldKey]
                                                                });
                                                            }}
                                                        />
                                                    )
                                                }
                                                else if(field.type === 'select') {
                                                    return (
                                                        <CF7AppsSelectField
                                                            key={ fieldKey }
                                                            label={field.title}
                                                            className={className}
                                                            name={fieldKey}
                                                            selected={formData['selected']}
                                                            options={field.options}
                                                            description={ parse( String(field.description) ) }
                                                        />
                                                    )
                                                }
                                                else if ( field.type === 'textarea' ) {
                                                    return (
                                                        <TextareaField
                                                            key={ fieldKey }
                                                            fieldKey={ fieldKey }
                                                            field={ field }
                                                            className={ className }
                                                            description={ parse( String(field.description) ) }
                                                            disabled={ field.disabled }
                                                            openMap={ openMap }
                                                            setOpenMap={ setOpenMap }
                                                            formData={ formData }
                                                            handleInputChange={ handleInputChange }
                                                        />
                                                    )
                                                }
                                                else if(field.type === 'save_button') {
                                                    return (
                                                        <div key={ fieldKey } className="cf7apps-form-group">
                                                            <Button 
                                                                className="cf7apps-btn tertiary-primary"
                                                                onClick={saveAppSettings}
                                                                isBusy={isSaving}
                                                            >
                                                                { field.text }
                                                            </Button>
                                                        </div>
                                                    )    
                                                }
                                                else if(fieldKey === 'template') {
                                                    const Template = CF7AppsTemplates[field];

                                                    // passing app settings to template for enable and disable the entries app.
                                                    return(
                                                        <Template
                                                            key={ fieldKey }
                                                            appSettings={ appSettings }
                                                            formData={ formData }
                                                        />
                                                    )
                                                }
                                                else {
                                                  //  console.log(fieldKey);
                                                }
                                            })
                                        }
                                    </TabPanel>
                                )
                            })
                        }
                    </TabContext>
                </div>
            );
        } else {
            // No Tabs
            return (
                <div className="cf7apps-form">
                    <div className="MuiTabPanel-root">
                        {
                            notice.show && (
                                <CF7AppsNotice
                                    type='warning'
                                    text={ notice.text }
                                />
                            )
                        }
                        {
                            Object.keys(appSettings.admin_settings['general']['fields']).map((fieldKey, fieldIndex) => {
                                const field = appSettings.admin_settings['general']['fields'][fieldKey];
                                const className = field.class === undefined ? '' : field.class;
                                const help = field.help;
                                const palceholder = field.placeholder === undefined ? '' : field.placeholder;

                                if(fieldKey === 'title') {
                                    return (
                                        <h3>{appSettings.admin_settings['general']['fields'].title}</h3>
                                    )
                                }
                                else if(fieldKey === 'description') {
                                    return (
                                        <p className="cf7apps-help-text">{appSettings.admin_settings['general']['fields'].description}</p>
                                    )
                                }
                                if(field.type === 'notice') {
                                    return (
                                        <CF7AppsNotice
                                            type={className}
                                            text={field.text}
                                        />
                                    )
                                }
                                else if(field.type === 'text') {
                                    return (
                                        <CF7AppsTextField
                                            label={field.title}
                                            description={ parse( String(field.description) ) }
                                            className={className}
                                            placeholder={palceholder}
                                            value={formData[fieldKey]}
                                            name={fieldKey}
                                            onChange={handleInputChange}
                                            required={field.required}
                                        />
                                    )
                                }
                                else if(field.type === 'number') {
                                    return (
                                        <CF7AppsNumberField
                                            label={field.title}
                                            description={ parse( String(field.description) ) }
                                            className={className}
                                            name={fieldKey}
                                            placeholder={palceholder}
                                            value={formData[fieldKey]}
                                            onChange={handleInputChange}
                                            min={field.min}
                                        />
                                    )
                                } 
                                else if(field.type === 'checkbox') {
                                    return (
                                        <CF7AppsToggle
                                            help={help} 
                                            label={field.title}
                                            className={className}
                                            isSelected={formData[fieldKey]}
                                            description={ parse( String(field.description) ) }
                                            disabled={field.disabled}
                                                            onChange={(e) => {
                                                                // Show red warning notice if trying to enable without ACF
                                                                if (fieldKey === 'is_enabled' && 
                                                                    appSettings.requires_acf && 
                                                                    !appSettings.acf_available && 
                                                                    !formData[fieldKey]) {
                                                                    // Reset notice state to ensure useEffect triggers
                                                                    setShowAcfNotice(false);
                                                                    // Use setTimeout to ensure state reset before setting to true
                                                                    setTimeout(() => {
                                                                        setShowAcfNotice(true);
                                                                        // Scroll to top immediately
                                                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                                                    }, 10);
                                                                    // Hide notice after 5 seconds
                                                                    setTimeout(() => setShowAcfNotice(false), 5000);
                                                                    return;
                                                                }
                                                                // Hide notice if enabling successfully
                                                                setShowAcfNotice(false);
                                                                setFormData({
                                                                    ...formData,
                                                                    [fieldKey]: ! formData[fieldKey]
                                                                });
                                                            }}
                                        />
                                    )
                                }
                                else if(field.type === 'select') { 
                                    return (
                                        <CF7AppsSelectField
                                            label={field.title}
                                            className={className}
                                            name={fieldKey}
                                            selected={field['selected']}
                                            options={field.options}
                                            onChange={handleInputChange}
                                            description={ parse( String(field.description) ) }
                                        />
                                    )
                                    } else if ( field.type === 'textarea' ) {
                                        return (
                                            <TextareaField
                                                key={ fieldKey }
                                                fieldKey={ fieldKey }
                                                field={ field }
                                                className={ className }
                                                description={ parse( String(field.description) ) }
                                                disabled={ field.disabled }
                                                openMap={ openMap }
                                                setOpenMap={ setOpenMap }
                                                formData={ formData }
                                                handleInputChange={ handleInputChange }
                                            />
                                        )
                                }
                                else if(field.type === 'save_button') {
                                    return (
                                        <div className="cf7apps-form-group">
                                            <Button 
                                                className="cf7apps-btn tertiary-primary"
                                                onClick={saveAppSettings}
                                                isBusy={isSaving}
                                            >
                                                { field.text }
                                            </Button>
                                        </div>
                                    )    
                                }
                                else if(fieldKey === 'template') {
                                    const Template = CF7AppsTemplates[field];
                                    return(
                                        <Template />
                                    )
                                }
                                else if ( 'radio' === field.type ) {
                                    let subField = field.sub_fields && field.sub_fields[ formData[ fieldKey ] ];
                                        if ( subField ) {
                                            // Ensure radio sub-field reflects current saved value
                                            const currentSubKey = formData[ fieldKey ];
                                            const currentSubValue = formData[ currentSubKey ];

                                            if ( subField.type === 'select' ) {
                                                // Select inputs depend on 'selected' prop
                                                subField['selected'] = currentSubValue;
                                            } else {
                                                // Text/number inputs depend on 'value' prop
                                                subField['value'] = currentSubValue;
                                            }
                                        }
                                    return (
                                        <>
                                            <CF7AppsRadioField
                                                label={field.title}
                                                className={className}
                                                options={field.options}
                                                name={fieldKey}
                                                onChange={handleInputChange}
                                                value={formData[fieldKey]}
                                                subFields={ subField }
                                            />
                                        </>
                                    );
                                }
                                else {
                                 //   console.log(field);
                                }
                            })
                        }
                    </div>
                </div>
            );
        }
    }

    return (
        ! isLoading && appSettings && appSettings.id === app
        ?
        <div className="cf7apps-body">
            <div className="cf7apps-app-setting-header">
                <div className="cf7apps-container">
                    <h1>{ appSettings.id === 'acf-integration' ? appSettings.title : sprintf( __( '%s Settings', 'cf7apps' ), appSettings.title ) }</h1>
                </div>
            </div>
            {showAcfNotice && appSettings.requires_acf && !appSettings.acf_available && (
                <div className="cf7apps-container" style={{ marginTop: '20px', marginBottom: '20px' }}>
                    <CF7AppsNotice
                        type="danger"
                        text={sprintf(
                            __( 'This integration requires the Advanced Custom Fields plugin to be installed and active. %s', 'cf7apps' ),
                            '<a href="' + (window.location.origin + '/wp-admin/plugin-install.php?s=advanced-custom-fields&tab=search&type=term') + '" style="text-decoration: underline; font-weight: bold;">' + __( 'Install ACF Plugin', 'cf7apps' ) + '</a>'
                        )}
                    />
                </div>
            )}
            <div className="cf7apps-app-setting-section">
                <div className="cf7apps-container">
                    { Settings() }
                </div>
            </div>
        </div>
        :
        <div className="cf7apps-body-loading">
            <CF7AppsSkeletonLoader height={800} width='100%' />
        </div>
    );
}

export default AppSettings;
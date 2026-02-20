import CF7AppsNotice from '../../../../../src/components/CF7AppsNotice';
import CF7AppsSkeletonLoader from '../components/CF7AppsSkeletonLoader';
import CF7AppsDisabledOverlay from '../components/CF7AppsDisabledOverlay';
import { useParams } from 'react-router';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getApps, saveSettings } from '../api'
import CF7AppsTextField from '../../../../../src/components/CF7AppsTextField';
import CF7AppsHelpText from '../../../../../src/components/CF7AppsHelpText';
import CF7AppsNumberField from '../../../../../src/components/CF7AppsNumberField';
import CF7AppsToggle from '../../../../../src/components/CF7AppsToggle';
import CF7AppsSelectField from '../../../../../src/components/CF7AppsSelectField';
import { Button } from '@wordpress/components';
import CF7AppsRadioField from '../../../../../src/components/CF7AppsRadioField';
import parse from 'html-react-parser';
import {toast} from "react-toastify";
import { useLocation } from 'react-router';

const TextareaField = ({ fieldKey, field, className, description, disabled, openMap, setOpenMap, formData, handleInputChange, isWebhookDisabled }) => {
    const open = (openMap && openMap[ fieldKey ] !== undefined) ? openMap[ fieldKey ] : ! field.collapsible;
    console.log(isWebhookDisabled);
    const toggleOpen = () => {
        if (isWebhookDisabled) return;
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
                        cursor: isWebhookDisabled ? 'not-allowed' : 'pointer',
                        opacity: isWebhookDisabled ? 0.6 : 1,
                        border: 'none',
                        transition: 'background-color 0.2s ease'
                    } }
                    onClick={ toggleOpen }
                    onMouseEnter={(e) => {
                        if (!isWebhookDisabled) {
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
                    <div><label style={{ margin: 0, cursor: isWebhookDisabled ? 'not-allowed' : 'pointer', fontWeight: '500' }}>{ field.title }</label></div>
                    <ChevronIcon isOpen={open} />
                </div>
            ) : (
                <div><label><b>{ field.title }</b></label></div>
            ) }

            { open && (
                <div style={ { marginTop: '10px'} }>
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
                        <div className="cf7apps-post-text" style={ { marginTop: '8px', color: '#444' } }>
                            { parse( String( field.post_text ) ) }
                        </div>
                    ) }

                    <CF7AppsHelpText description={ description } />
                </div>
            ) }
        </div>
    );
};

const CF7AppsSettings = () => {
    let { app } = useParams();
    const location = useLocation();

    const [ isLoading, setIsLoading ] = useState( true );
    const [ appSettings, setAppSettings ] = useState( false );
    const [ formData, setFormData ] = useState( {} );
    const [ openMap, setOpenMap ] = useState( {} );
    const [ isSaving, setIsSaving ] = useState( false );
    const [ notice, setNotice ] = useState( { show: false, text: '' } );

    app = app ? app : 'cf7-redirection';
    useEffect( () => {
        setIsLoading(true); // Reset loading state when app changes
        getApps( app, CF7AppsInternalSettings.formID )
            .then( ( appSettings ) => {
                let settings = appSettings['admin_settings']['general'];
                let _formData = {};
                Object.keys( settings['fields'] ).map( ( fieldKey ) => {
                    const field = settings['fields'][ fieldKey ];

                        if ( 'template' !== fieldKey ) {
                        if ( 'text' === field.type || 'number' === field.type || 'radio' === field.type ) {
                            if ( field.value ) {
                                _formData[ fieldKey ] = field.value;
                            } else {
                                _formData[ fieldKey ] = field.default;
                            }
                        } else if ( 'checkbox' === field.type ) {
                            // Check if checked property is explicitly set (could be true or false)
                            if ( field.checked !== undefined ) {
                                _formData[ fieldKey ] = field.checked;
                            } else {
                                _formData[ fieldKey ] = field.default;
                            }
                        }

                        // textarea initial value
                        else if ( 'textarea' === field.type ) {
                            if ( field.value ) {
                                _formData[ fieldKey ] = field.value;
                            } else {
                                _formData[ fieldKey ] = field.default ? field.default : '';
                            }
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
                                    // Check if checked property is explicitly set (could be true or false)
                                    if ( subField.checked !== undefined ) {
                                        _formData[ subFieldKey ] = subField.checked;
                                    } else {
                                        _formData[ subFieldKey ] = subField.default;
                                    }

                                } else if ( subField.type === 'select' ) {
                                    if ( subField.selected ) {
                                        _formData[ subFieldKey ] = subField.selected;
                                    } else {
                                        _formData[ subFieldKey ] = subField.default;
                                    }
                                }
                            } );
                        }

                    }

                } );

                setFormData( _formData );
                // initialize openMap for textarea fields so open state survives rerenders
                let _openMap = {};
                Object.keys( settings['fields'] ).map( ( fieldKey ) => {
                    const field = settings['fields'][ fieldKey ];
                    if ( field && field.type === 'textarea' ) {
                        _openMap[ fieldKey ] = ! field.collapsible;
                    }
                } );
                setOpenMap( _openMap );
                setAppSettings( appSettings );
                setIsLoading( false );

            } ).catch( ( error ) => {
                setIsLoading( false );
            } );
    }, [ app, location.pathname ] );

    const handleInputChange = ( e ) => {
        const { name, value } = e.target;
        setFormData( ( prev ) => ( {
            ...prev,
            [ name ]: value,
        } ) );
    }

    const saveAppSettings = () => {

        let missingRequired = false;
        let requiredMessage = '';

        Object.keys(appSettings.admin_settings['general']['fields']).some((fieldKey) => {
            const field = appSettings.admin_settings['general']['fields'][fieldKey];
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

        if (missingRequired) {
            setNotice({ show: true, text: requiredMessage });
            toast.error( __( 'Error! Please fill all required fields.', 'cf7apps' ) );
            setIsSaving(false);
            return;
        }

        // Validate webhook URL format (only for webhook app)
        if ( app === 'webhook' && formData['is_enabled'] ) {
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

        // Clear any previous warning once validation passes
        setNotice({ show: false, text: '' });

        setIsSaving( true );
        saveSettings( app, formData, CF7AppsInternalSettings.formID )
            .then( response => {
                setIsSaving( false );
                toast.success( __( 'Great! Settings Saved Successfully', 'cf7apps' ) );
            } ).catch( error => {
                setIsSaving( false );
                toast.error( __( 'Error! Something Went Wrong', 'cf7apps' ) );
        } );
    };


    const Settings = () => {
        return (
            <div className={ 'cf7apps-form' }>
                <div className={ 'MuiTabPanel-root' }>
                    {
                        notice.show && <CF7AppsNotice
                            type={ 'warning' }
                            text={ notice.text }
                        />
                    }

                    {
                        Object.keys( appSettings.admin_settings['general']['fields'] ).map( fieldKey => {
                            const field = appSettings.admin_settings['general']['fields'][ fieldKey ];
                            const className =  undefined === field.class ? '' : field.class;
                            const help = field.help;
                            const placeholder = undefined === field.placeholder ? '' : field.placeholder;

                            // Hide fields when webhook is not enabled (only for webhook app)
                            if ( app === 'webhook' ) {
                                const webhookEnabled = formData['is_enabled'];
                                if ( !webhookEnabled && fieldKey !== 'notice' && fieldKey !== 'is_enabled' && field.type !== 'save_button' ) {
                                    return null;
                                }
                            }

                            // Hide fields when redirection is not enabled (only for redirection app)
                            if ( app === 'cf7-redirection' ) {
                                const redirectionEnabled = formData['enable_redirection'];
                                if ( redirectionEnabled !== true && fieldKey !== 'notice' && fieldKey !== 'enable_redirection' && field.type !== 'save_button' ) {
                                    return null;
                                }
                            }

                            if ( 'title' === fieldKey ) {
                                return (
                                    <h3 key={fieldKey}>{ appSettings.admin_settings['general']['fields'].title }</h3>
                                );
                            } else if ( 'description' === fieldKey ) {
                                return (
                                    <p key={fieldKey} className={ 'cf7apps-help-text' }>
                                        { appSettings.admin_settings['general']['fields'].description }
                                    </p>
                                );
                            } else if ( 'notice' === field.type ) {
                                return (
                                    <CF7AppsNotice
                                        key={fieldKey}
                                        type={ className }
                                        text={ field.text }
                                    />
                                );
                            } else if ( 'text' === field.type ) {
                                return (
                                    <CF7AppsTextField
                                        key={fieldKey}
                                        label={ field.title }
                                        description={ parse( String( field.description ) ) }
                                        className={ className }
                                        placeholder={ placeholder }
                                        value={ formData[ fieldKey ] }
                                        name={ fieldKey }
                                        onChange={ handleInputChange }
                                        required={ field.required }
                                        disabled={ field.disabled }
                                    />
                                );
                            } else if ( 'number' === field.type ) {
                                return (
                                    <CF7AppsNumberField
                                        key={fieldKey}
                                        label={ field.title }
                                        description={ parse( String( field.description ) ) }
                                        className={ className }
                                        name={ fieldKey }
                                        placeholder={ placeholder }
                                        value={ formData[ fieldKey ] }
                                        onChange={ handleInputChange }
                                        disabled={ field.disabled }
                                        min={ field.min }
                                    />
                                );
                            } else if ( 'checkbox' === field.type ) {
                                return (
                                    <CF7AppsToggle
                                        key={fieldKey}
                                        help={ help }
                                        label={ field.title }
                                        className={ className }
                                        isSelected={ formData[ fieldKey ] }
                                        onChange={ ( e ) => {
                                            setFormData( ( prevFormData ) => ( {
                                                ...prevFormData,
                                                [ fieldKey ]: ! prevFormData[ fieldKey ]
                                            } ) );
                                        } }
                                        disabled={ field.disabled }
                                    />
                                );
                            } else if ( 'select' === field.type ) {
                                return (
                                    <CF7AppsSelectField
                                        key={fieldKey}
                                        label={ field.title }
                                        className={ className }
                                        name={ fieldKey }
                                        selected={ field['selected'] }
                                        options={ field.options }
                                        onChange={ handleInputChange }
                                        description={ parse( String( field.description ) ) }
                                        disabled={ field.disabled }
                                    />
                                )
                            } else if ( 'textarea' === field.type ) {
                                const isWebhookDisabled = app === 'webhook' && !formData['is_enabled'];
                                return (
                                    <TextareaField
                                        key={ fieldKey }
                                        fieldKey={ fieldKey }
                                        field={ field }
                                        className={ className }
                                        description={ parse( String( field.description ) ) }
                                        disabled={ field.disabled }
                                        openMap={ openMap }
                                        setOpenMap={ setOpenMap }
                                        formData={ formData }
                                        handleInputChange={ handleInputChange }
                                        isWebhookDisabled={ isWebhookDisabled }
                                    />
                                )
                            } else if ( 'save_button' === field.type ) {
                                return (
                                    <div key={fieldKey} className="cf7apps-form-group">
                                        <Button
                                            className="cf7apps-btn tertiary-primary"
                                            onClick={ saveAppSettings }
                                            isBusy={ isSaving }
                                            disabled={ field.disabled }
                                        >
                                            { field.text }
                                        </Button>
                                    </div>
                                )
                            } else if ( 'template' === fieldKey ) {
                                console.log( field )
                            } else if ( 'radio' === field.type ) {

                                const subKey = formData[ fieldKey ];
                                const subField = field.sub_fields && field.sub_fields[ subKey ];
                                subField['value'] = formData[ subKey ];
                                return (
                                    <div key={ fieldKey }>
                                        <CF7AppsRadioField
                                            label={ field.title }
                                            className={ className }
                                            options={ field.options }
                                            name={ fieldKey }
                                            onChange={ handleInputChange }
                                            value={ formData[ fieldKey ] }
                                            subFields={ subField }
                                            disabled={ field.disabled }
                                        />
                                    </div>
                                );

                            } else {
                              //  console.log( field )
                            }
                        } )
                    }
                </div>
            </div>
        );
    }

    return (
        ! isLoading && appSettings && appSettings.id === app
        ?
            <div className={ 'cf7apps-body' }>
                <div className={ 'cf7apps-app-setting-header' }>
                    <div className={ 'cf7apps-container' }>
                        <h1>{ sprintf( __( '%s Settings', 'cf7apps' ), appSettings.title ) }</h1>
                    </div>
                </div>

                <div className={ 'cf7apps-app-setting-section' }>
                    <div className={ 'cf7apps-container' }>
                        <div className={ 'cf7apps-settings-content-wrapper' }>
                            <div className={ appSettings.is_enabled ? 'cf7apps-enabled-content' : 'cf7apps-disabled-content' }>
                                { Settings() }
                            </div>
                            { ( appSettings.is_enabled !== true ) && (
                                <CF7AppsDisabledOverlay 
                                    appId={ appSettings.id } 
                                    appTitle={ appSettings.title } 
                                />
                            ) }
                        </div>
                    </div>
                </div>
            </div>
        :
            <div className={ 'cf7apps-body-loading' }>
                <CF7AppsSkeletonLoader height={ 800 } width={ '100%' } />
            </div>
    );
};

export default CF7AppsSettings;
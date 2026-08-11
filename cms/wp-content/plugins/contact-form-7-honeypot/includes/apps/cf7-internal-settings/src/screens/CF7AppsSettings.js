import CF7AppsNotice from '../../../../../src/components/CF7AppsNotice';
import CF7AppsFormNotice from '../components/CF7AppsFormNotice';
import CF7AppsSkeletonLoader from '../components/CF7AppsSkeletonLoader';
import CF7AppsDisabledOverlay from '../components/CF7AppsDisabledOverlay';
import { useParams } from 'react-router';
import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { getApps, saveSettings } from '../api'
import CF7AppsTextField from '../../../../../src/components/CF7AppsTextField';
import CF7AppsHelpText from '../../../../../src/components/CF7AppsHelpText';
import CF7AppsNumberField from '../../../../../src/components/CF7AppsNumberField';
import CF7AppsFormToggle from '../components/CF7AppsFormToggle';
import CF7AppsSelectField from '../../../../../src/components/CF7AppsSelectField';
import { Button } from '@wordpress/components';
import CF7AppsRadioField from '../../../../../src/components/CF7AppsRadioField';
import parse from 'html-react-parser';
import { toast } from "react-toastify";
import { useLocation } from 'react-router';
import { getWebhookDataTypeOptions } from '../../../../../src/utils/webhookUiUtils';
import { getFormEditorSettingsPanelStyle, normalizeParentMenu } from '../../../../../src/utils/appCardTheme';
import CF7AppsAppSettingsHeader from '../../../../../src/components/CF7AppsAppSettingsHeader';
import CF7AppsAppSettingsTabs from '../../../../../src/components/CF7AppsAppSettingsTabs';
import CF7AppsTemplates from '../../../../../src/templates/CF7AppsTemplates';

const hydrateFieldValue = ( field, fieldKey, formState ) => {
    if ( fieldKey === 'template' || typeof field !== 'object' || ! field?.type ) {
        return;
    }

    if ( field.type === 'text' || field.type === 'number' || field.type === 'radio' ) {
        if ( field.value !== undefined && field.value !== null && field.value !== '' ) {
            formState[ fieldKey ] = field.value;
        } else {
            formState[ fieldKey ] = field.default;
        }
    } else if ( field.type === 'checkbox' ) {
        if ( field.checked !== undefined ) {
            formState[ fieldKey ] = field.checked;
        } else {
            formState[ fieldKey ] = field.default;
        }
    } else if ( field.type === 'textarea' ) {
        formState[ fieldKey ] = field.value !== undefined
            ? field.value
            : ( field.default ? field.default : '' );
    } else if ( field.type === 'select' ) {
        if ( field.selected !== undefined && field.selected !== null && field.selected !== '' ) {
            formState[ fieldKey ] = field.selected;
        } else if ( field.default !== undefined ) {
            formState[ fieldKey ] = field.default;
        }
    }

    if ( field.sub_fields ) {
        Object.keys( field.sub_fields ).forEach( ( subFieldKey ) => {
            const subField = field.sub_fields[ subFieldKey ];

            if ( subField.type === 'text' || subField.type === 'number' ) {
                if ( subField.value ) {
                    formState[ subFieldKey ] = subField.value;
                } else {
                    formState[ subFieldKey ] = subField.default;
                }
            } else if ( subField.type === 'checkbox' ) {
                if ( subField.checked !== undefined ) {
                    formState[ subFieldKey ] = subField.checked;
                } else {
                    formState[ subFieldKey ] = subField.default;
                }
            } else if ( subField.type === 'select' ) {
                if ( subField.selected ) {
                    formState[ subFieldKey ] = subField.selected;
                } else {
                    formState[ subFieldKey ] = subField.default;
                }
            }
        } );
    }
};

const FormSettingsHeader = ({ appSettings }) => {
    const parentKey = normalizeParentMenu(appSettings?.parent_menu);
    const isIntegrationLike = [
        'integration',
        'integrations',
        'payment',
        'payments',
        'payment-gate',
    ].includes(parentKey);
    const settingsTitle = isIntegrationLike
        ? sprintf(__('%s Integration Settings', 'cf7apps'), appSettings.title)
        : sprintf(__('%s Settings', 'cf7apps'), appSettings.title);

    return (
        <div className="cf7apps-form-settings-header">
            <h2 className="cf7apps-form-settings-title">{settingsTitle}</h2>
        </div>
    );
};

const TextareaField = ({ fieldKey, field, className, description, disabled, openMap, setOpenMap, formData, handleInputChange, isWebhookDisabled, isAppShell = false }) => {
    const open = (openMap && openMap[fieldKey] !== undefined) ? openMap[fieldKey] : !field.collapsible;
    const toggleOpen = () => {
        if (isWebhookDisabled) return;
        setOpenMap(prev => ({
            ...prev,
            [fieldKey]: !prev[fieldKey]
        }));
    };
    // Chevron rendered via CSS ::after on the trigger (stroke SVG was hard to see in WP admin).
    const ChevronIcon = ({ isOpen = false }) => (
        <span
            className={`cf7apps-collapsible-chevron${isOpen ? ' is-open' : ''}`}
            aria-hidden="true"
        />
    );

    return (
        <div className={`cf7apps-form-group cf7apps-settings${ isAppShell ? ' cf7apps-app-field-group' : '' }`}>
            {field.collapsible ? (
                <div
                    className="cf7apps-settings-collapsible-trigger cf7apps-form-settings-collapsible"
                    style={isAppShell ? undefined : {
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '8px',
                        width: '100%',
                        maxWidth: '500px',
                        padding: '10px 12px',
                        borderRadius: '6px',
                        cursor: isWebhookDisabled ? 'not-allowed' : 'pointer',
                        opacity: isWebhookDisabled ? 0.6 : 1,
                        border: 'none',
                        transition: 'background-color 0.2s ease',
                        ...(field.collapsed_button_color
                            ? {
                                backgroundColor: `${field.collapsed_button_color}20`,
                            }
                            : {}),
                    }}
                    onClick={toggleOpen}
                    onMouseEnter={isAppShell ? undefined : (e) => {
                        if (!isWebhookDisabled && field.collapsed_button_color) {
                            e.currentTarget.style.backgroundColor = `${field.collapsed_button_color}30`;
                        }
                    }}
                    onMouseLeave={isAppShell ? undefined : (e) => {
                        if (field.collapsed_button_color) {
                            e.currentTarget.style.backgroundColor = `${field.collapsed_button_color}20`;
                        }
                    }}
                    role="button"
                    tabIndex={0}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            toggleOpen();
                        }
                    }}
                    aria-expanded={!!open}
                >
                    <div><label style={isAppShell ? undefined : { margin: 0, cursor: isWebhookDisabled ? 'not-allowed' : 'pointer', fontWeight: '500' }}>{field.title}</label></div>
                    <ChevronIcon isOpen={open} />
                </div>
            ) : (
                <div><label><b>{field.title}</b></label></div>
            )}

            {open && (
                <div className={isAppShell ? 'cf7apps-app-collapsible-panel' : undefined} style={isAppShell ? undefined : { marginTop: '10px' }}>
                    {field.pre_text && (
                        <div className="cf7apps-pre-text" style={isAppShell ? undefined : { marginBottom: '8px', color: '#444' }}>
                            {parse(String(field.pre_text))}
                        </div>
                    )}
                    <textarea
                        className={`cf7apps-form-input ${className}`}
                        name={fieldKey}
                        value={formData[fieldKey] || ''}
                        onChange={handleInputChange}
                        rows={3}
                        disabled={disabled}
                        style={isAppShell ? undefined : { width: '100%', maxWidth: '500px', minHeight: '120px', boxSizing: 'border-box', padding: '12px' }}
                    ></textarea>

                    {field.post_text && (
                        <div className="cf7apps-post-text" style={isAppShell ? undefined : { marginTop: '8px', color: '#444' }}>
                            {parse(String(field.post_text))}
                        </div>
                    )}

                    <CF7AppsHelpText description={description} />
                </div>
            )}
        </div>
    );
};

const CF7AppsSettings = () => {
    let { app } = useParams();
    const location = useLocation();

    const [isLoading, setIsLoading] = useState(true);
    const [appSettings, setAppSettings] = useState(false);
    const [formData, setFormData] = useState({});
    const [openMap, setOpenMap] = useState({});
    const [isSaving, setIsSaving] = useState(false);
    const [notice, setNotice] = useState({ show: false, text: '' });
    const [hasTabs, setHasTabs] = useState(false);
    const [tabValue, setTabValue] = useState('1');

    app = app ? app : 'cf7-redirection';
    useEffect(() => {
        const hash = window.location.hash;
        const explodedHash = hash.split('/');
        if (explodedHash.length > 3) {
            setTabValue(explodedHash[3]);
        }
    }, [app, location.pathname]);

    useEffect(() => {
        setIsLoading(true); // Reset loading state when app changes
        getApps(app, CF7AppsInternalSettings.formID)
            .then((appSettings) => {
                let settings = appSettings['admin_settings']['general'];
                let _formData = {};
                const tabsEnabled = Object.keys(appSettings.setting_tabs || {}).length > 0;

                setHasTabs(tabsEnabled);

                if (tabsEnabled) {
                    Object.keys(appSettings.setting_tabs).forEach((tabKey) => {
                        const tabFields = settings['fields'][tabKey] || {};

                        Object.keys(tabFields).forEach((fieldKey) => {
                            hydrateFieldValue(tabFields[fieldKey], fieldKey, _formData);
                        });
                    });
                } else {
                Object.keys(settings['fields']).map((fieldKey) => {
                    const field = settings['fields'][fieldKey];

                    if ('template' !== fieldKey) {
                        hydrateFieldValue(field, fieldKey, _formData);
                    }

                });
                }

                if (app === 'webhook' && String(_formData.method || '').toUpperCase() === 'GET' && _formData.data_type === 'form') {
                    _formData.data_type = 'json';
                }

                setFormData(_formData);
                let _openMap = {};

                if (tabsEnabled) {
                    Object.keys(appSettings.setting_tabs).forEach((tabKey) => {
                        const tabFields = settings['fields'][tabKey] || {};

                        Object.keys(tabFields).forEach((fieldKey) => {
                            const field = tabFields[fieldKey];
                            if (field && field.type === 'textarea') {
                                _openMap[fieldKey] = field.collapsible
                                    ? (app === 'webhook' ? true : false)
                                    : true;
                            }
                        });
                    });
                } else {
                Object.keys(settings['fields']).map((fieldKey) => {
                    const field = settings['fields'][fieldKey];
                    if (field && field.type === 'textarea') {
                        _openMap[fieldKey] = field.collapsible
                            ? (app === 'webhook' ? true : false)
                            : true;
                    }
                });
                }
                setOpenMap(_openMap);
                setAppSettings(appSettings);
                setIsLoading(false);

            }).catch((error) => {
                setIsLoading(false);
            });
    }, [app, location.pathname]);

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData((prev) => {
            const next = {
                ...prev,
                [name]: value,
            };
            if (app === 'webhook' && name === 'method' && String(value).toUpperCase() === 'GET' && prev.data_type === 'form') {
                next.data_type = 'json';
            }
            return next;
        });
    }

    const saveAppSettings = () => {

        let missingRequired = false;
        let requiredMessage = '';

        const validateFields = (fields) => {
            Object.keys(fields).some((fieldKey) => {
                const field = fields[fieldKey];

                if ( ! field || typeof field !== 'object' || ! field.type ) {
                    return false;
                }

                if (field.required && (formData[fieldKey] === '' || formData[fieldKey] === undefined)) {
                    missingRequired = true;
                    requiredMessage = field.required_message || __('Please fill all required fields.', 'cf7apps');
                    return true;
                }

                if (field.sub_fields) {
                    if (field.sub_fields[formData[fieldKey]] && field.sub_fields[formData[fieldKey]].required && (formData[formData[fieldKey]] === '' || formData[formData[fieldKey]] === undefined)) {
                        missingRequired = true;
                        requiredMessage = field.sub_fields[formData[fieldKey]].required_message || __('Please fill all required fields.', 'cf7apps');
                        return true;
                    }
                }

                return false;
            });
        };

        if (hasTabs) {
            Object.keys(appSettings.setting_tabs).forEach((tabKey) => {
                if (missingRequired) {
                    return;
                }

                validateFields(appSettings.admin_settings['general']['fields'][tabKey] || {});
            });
        } else {
            validateFields(appSettings.admin_settings['general']['fields']);
        }

        if (missingRequired) {
            setNotice({ show: true, text: requiredMessage });
            toast.error(__('Error! Please fill all required fields.', 'cf7apps'));
            setIsSaving(false);
            return;
        }

        // Validate webhook URL format (only for webhook app)
        if (app === 'webhook' && formData['is_enabled']) {
            const webhookUrl = formData['webhook_url'] ? formData['webhook_url'].trim() : '';

            // Check if webhook URL is empty
            if (webhookUrl === '') {
                const errorMessage = __('Webhook URL cannot be empty.', 'cf7apps');
                setNotice({ show: true, text: errorMessage });
                toast.error(errorMessage);
                setIsSaving(false);
                return;
            }

            // Check if webhook URL has correct format
            if (!webhookUrl.startsWith('http://') && !webhookUrl.startsWith('https://')) {
                const errorMessage = __('Webhook URL must start with http:// or https://', 'cf7apps');
                setNotice({ show: true, text: errorMessage });
                toast.error(errorMessage);
                setIsSaving(false);
                return;
            }
        }

        // Clear any previous warning once validation passes
        setNotice({ show: false, text: '' });

        setIsSaving(true);
        saveSettings(app, formData, CF7AppsInternalSettings.formID)
            .then(response => {
                setIsSaving(false);
                toast.success(__('Great! Settings Saved Successfully', 'cf7apps'));
            }).catch(error => {
                setIsSaving(false);
                toast.error(__('Error! Something Went Wrong', 'cf7apps'));
            });
    };


    const Settings = () => {
        const isRedirectionSettings = app === 'cf7-redirection';
        const isEntriesSettings = app === 'cf7-entries';
        const isWebhookSettings = app === 'webhook';
        const isAppSettingsShell = isRedirectionSettings || isEntriesSettings || isWebhookSettings;
        const formClassName = [
            'cf7apps-form',
            isAppSettingsShell ? 'cf7apps-app-settings-form' : '',
        ]
            .filter(Boolean)
            .join(' ');

        const renderSaveButton = (fieldKey, field) => {
            const formEditorAssets =
                CF7AppsInternalSettings?.formEditorAssetsURL ||
                `${CF7AppsInternalSettings?.pluginURL || ''}includes/apps/cf7-internal-settings/assets/images/form-editor/`;
            const saveIcon = formEditorAssets
                ? `${formEditorAssets}save-icon.svg`
                : '';

            const saveButton = (
                <div className="cf7apps-form-group cf7apps-form-settings-save">
                    <Button
                        className="cf7apps-btn tertiary-primary cf7apps-form-settings-save-btn"
                        onClick={saveAppSettings}
                        isBusy={isSaving}
                        disabled={field.disabled}
                    >
                        {saveIcon && (
                            <img
                                src={saveIcon}
                                alt=""
                                width={18}
                                height={18}
                                className="cf7apps-form-settings-save-btn__icon"
                                aria-hidden="true"
                            />
                        )}
                        {field.text}
                    </Button>
                </div>
            );

            if (isRedirectionSettings) {
                return (
                    <div key={fieldKey} className="cf7apps-app-settings-save-wrap">
                        {saveButton}
                    </div>
                );
            }

            if (isWebhookSettings) {
                return (
                    <div key={fieldKey} className="cf7apps-app-settings-save-wrap">
                        {saveButton}
                    </div>
                );
            }

            return (
                <div key={fieldKey}>
                    {saveButton}
                </div>
            );
        };

        const renderCheckboxField = (fieldKey, field, className, help) => {
            if (isAppSettingsShell) {
                return (
                    <div key={fieldKey} className="cf7apps-form-toggle-field">
                        <CF7AppsFormToggle
                            name={fieldKey}
                            label={field.title}
                            help={help}
                            className={className}
                            isSelected={formData[fieldKey]}
                            disabled={field.disabled}
                            onChange={(nextValue) => {
                                setFormData((prevFormData) => ({
                                    ...prevFormData,
                                    [fieldKey]: nextValue,
                                }));
                            }}
                        />
                    </div>
                );
            }

            return (
                <div key={fieldKey} className="cf7apps-form-group cf7apps-form-toggle-field">
                    <CF7AppsFormToggle
                        label={field.title}
                        help={help}
                        isSelected={formData[fieldKey]}
                        onChange={() => {
                            setFormData((prevFormData) => ({
                                ...prevFormData,
                                [fieldKey]: !prevFormData[fieldKey],
                            }));
                        }}
                        disabled={field.disabled}
                    />
                </div>
            );
        };

        const renderSettingsField = (fieldKey, field, tabSettings = null) => {
            const className = undefined === field?.class ? '' : field.class;
            const help = field?.help;
            const placeholder = undefined === field?.placeholder ? '' : field.placeholder;

            if (app === 'webhook') {
                const webhookEnabled = formData['is_enabled'];
                if (!webhookEnabled && fieldKey !== 'notice' && fieldKey !== 'is_enabled' && field?.type !== 'save_button') {
                    return null;
                }
            }

            if (app === 'cf7-redirection') {
                const redirectionEnabled = formData['enable_redirection'];
                if (redirectionEnabled !== true && fieldKey !== 'notice' && fieldKey !== 'enable_redirection' && field?.type !== 'save_button') {
                    return null;
                }
            }

            if ('title' === fieldKey) {
                if (isAppSettingsShell) {
                    return null;
                }

                return (
                    <h3 key={fieldKey} className="cf7apps-form-settings-section-title">
                        {tabSettings?.title || appSettings.admin_settings['general']['fields'].title}
                    </h3>
                );
            }

            if ('description' === fieldKey) {
                if (isAppSettingsShell) {
                    return null;
                }

                return (
                    <p key={fieldKey} className={'cf7apps-help-text'}>
                        {tabSettings?.description || appSettings.admin_settings['general']['fields'].description}
                    </p>
                );
            }

            if (fieldKey === 'template') {
                const Template = CF7AppsTemplates[field];

                if (!Template) {
                    return null;
                }

                return (
                    <Template
                        key={fieldKey}
                        appSettings={appSettings}
                        formData={formData}
                    />
                );
            }

            if ('notice' === field?.type) {
                if (isAppSettingsShell) {
                    return null;
                }

                return (
                    <CF7AppsFormNotice
                        key={fieldKey}
                        type={className}
                        text={field.text}
                    />
                );
            }

            if ('text' === field?.type) {
                return (
                    <CF7AppsTextField
                        key={fieldKey}
                        label={field.title}
                        description={parse(String(field.description))}
                        className={className}
                        placeholder={placeholder}
                        value={formData[fieldKey]}
                        name={fieldKey}
                        onChange={handleInputChange}
                        required={field.required}
                        disabled={field.disabled}
                        variant={isAppSettingsShell ? 'app' : undefined}
                    />
                );
            }

            if ('number' === field?.type) {
                return (
                    <CF7AppsNumberField
                        key={fieldKey}
                        label={field.title}
                        description={parse(String(field.description))}
                        className={className}
                        name={fieldKey}
                        placeholder={placeholder}
                        value={formData[fieldKey]}
                        onChange={handleInputChange}
                        disabled={field.disabled}
                        min={field.min}
                    />
                );
            }

            if ('checkbox' === field?.type) {
                return renderCheckboxField(fieldKey, field, className, help);
            }

            if ('select' === field?.type) {
                const isWebhookDataType = app === 'webhook' && fieldKey === 'data_type';
                const selectOptions = isWebhookDataType
                    ? getWebhookDataTypeOptions(formData.method, field.options)
                    : field.options;
                const selectedVal = formData[fieldKey] !== undefined && formData[fieldKey] !== null
                    ? formData[fieldKey]
                    : field.selected;
                return (
                    <CF7AppsSelectField
                        key={fieldKey}
                        label={field.title}
                        className={className}
                        name={fieldKey}
                        selected={selectedVal}
                        options={selectOptions}
                        onChange={handleInputChange}
                        description={parse(String(field.description))}
                        disabled={field.disabled}
                        variant={isAppSettingsShell ? 'app' : undefined}
                    />
                );
            }

            if ('textarea' === field?.type) {
                const isWebhookDisabled = app === 'webhook' && !formData['is_enabled'];
                return (
                    <TextareaField
                        key={fieldKey}
                        fieldKey={fieldKey}
                        field={field}
                        className={className}
                        description={parse(String(field.description))}
                        disabled={field.disabled}
                        openMap={openMap}
                        setOpenMap={setOpenMap}
                        formData={formData}
                        handleInputChange={handleInputChange}
                        isWebhookDisabled={isWebhookDisabled}
                        isAppShell={isAppSettingsShell}
                    />
                );
            }

            if ('save_button' === field?.type) {
                return renderSaveButton(fieldKey, field);
            }

            if ('radio' === field?.type) {
                const subKey = formData[fieldKey];
                let subField = field.sub_fields && field.sub_fields[subKey];
                if (subField) {
                    const currentSubValue = formData[subKey];
                    if (subField.type === 'select') {
                        subField = { ...subField, selected: currentSubValue };
                    } else {
                        subField = { ...subField, value: currentSubValue };
                    }
                }

                return (
                    <CF7AppsRadioField
                        key={fieldKey}
                        label={field.title}
                        className={className}
                        options={field.options}
                        name={fieldKey}
                        onChange={handleInputChange}
                        value={formData[fieldKey]}
                        subFields={subField}
                        disabled={field.disabled}
                        variant={isAppSettingsShell ? 'app' : undefined}
                    />
                );
            }

            return null;
        };

        const renderTabPanel = (tabKey, tabIndex) => {
            if (`${tabIndex + 1}` !== tabValue) {
                return null;
            }

            const tabSettings = appSettings.admin_settings['general']['fields'][tabKey] || {};

            return (
                <div
                    key={tabKey}
                    className={`MuiTabPanel-root${tabKey === 'entries' ? ' cf7apps-app-settings-tabpanel-entries' : ''}`}
                >
                    {Object.keys(tabSettings).map((fieldKey) =>
                        renderSettingsField(fieldKey, tabSettings[fieldKey], tabSettings)
                    )}
                </div>
            );
        };

        if (hasTabs) {
            return (
                <div className={formClassName}>
                    {notice.show && (
                        <CF7AppsNotice
                            type={'warning'}
                            text={notice.text}
                        />
                    )}

                    {hasTabs && (
                        <CF7AppsAppSettingsTabs
                            tabs={appSettings.setting_tabs}
                            activeTab={tabValue}
                            onChange={setTabValue}
                        />
                    )}

                    {Object.keys(appSettings.setting_tabs).map((tabKey, tabIndex) =>
                        renderTabPanel(tabKey, tabIndex)
                    )}
                </div>
            );
        }

        return (
            <div className={formClassName}>
                <div className={'MuiTabPanel-root'}>
                    {
                        notice.show && !isAppSettingsShell && <CF7AppsNotice
                            type={'warning'}
                            text={notice.text}
                        />
                    }

                    {
                        Object.keys(appSettings.admin_settings['general']['fields']).map(fieldKey => {
                            const field = appSettings.admin_settings['general']['fields'][fieldKey];

                            if (isWebhookSettings && fieldKey === 'data_type') {
                                return null;
                            }

                            if (isWebhookSettings && fieldKey === 'method') {
                                const fields = appSettings.admin_settings['general']['fields'];
                                return (
                                    <div key="webhook-method-row" className="cf7apps-webhook-method-row">
                                        {renderSettingsField('method', fields.method)}
                                        {renderSettingsField('data_type', fields.data_type)}
                                    </div>
                                );
                            }

                            return renderSettingsField(fieldKey, field);
                        })
                    }
                </div>
            </div>
        );
    }

    const assetsBase = CF7AppsInternalSettings?.assetsURL || '';
    const isRedirectionSettings = app === 'cf7-redirection';
    const isEntriesSettings = app === 'cf7-entries';
    const isWebhookSettings = app === 'webhook';
    const isAppSettingsShell = isRedirectionSettings || isEntriesSettings || isWebhookSettings;
    const appSettingsPanelClass = isRedirectionSettings
        ? ' cf7apps-redirection-settings'
        : (isEntriesSettings ? ' cf7apps-entries-settings' : (isWebhookSettings ? ' cf7apps-webhook-settings' : ''));
    const panelStyle =
        appSettings && appSettings.id === app && !isAppSettingsShell
            ? getFormEditorSettingsPanelStyle(appSettings, assetsBase)
            : undefined;

    return (
        !isLoading && appSettings && appSettings.id === app
            ?
            <div
                className={`cf7apps-form-settings-view cf7apps-app-${appSettings.id}${appSettingsPanelClass}`}
                style={panelStyle}
            >
                {isAppSettingsShell ? (
                    <CF7AppsAppSettingsHeader
                        appSettings={appSettings}
                        assetsBase={assetsBase}
                        showBack={false}
                    />
                ) : (
                    <FormSettingsHeader appSettings={appSettings} />
                )}

                <div className="cf7apps-form-settings-body">
                    <div className="cf7apps-settings-content-wrapper">
                        <div className={appSettings.is_enabled ? 'cf7apps-enabled-content' : 'cf7apps-disabled-content'}>
                            {Settings()}
                        </div>
                        {(appSettings.is_enabled !== true) && (
                            <CF7AppsDisabledOverlay
                                appId={appSettings.id}
                                appTitle={appSettings.title}
                            />
                        )}
                    </div>
                </div>
            </div>
            :
            <div className="cf7apps-form-settings-view cf7apps-form-settings-view--loading">
                <CF7AppsSkeletonLoader height={420} width="100%" />
            </div>
    );
};

export default CF7AppsSettings;
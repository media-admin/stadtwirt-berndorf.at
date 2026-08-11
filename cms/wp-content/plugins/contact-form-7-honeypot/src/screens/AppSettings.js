import { useEffect, useState, useCallback } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import CF7AppsSkeletonLoader from "../components/CF7AppsSkeletonLoader";
import { Link, useParams } from "react-router";
import { KeyboardArrowLeft } from "@mui/icons-material";
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
import { getWebhookDataTypeOptions } from "../utils/webhookUiUtils";
import { getAppCardTheme, getAppSettingsPanelStyle } from "../utils/appCardTheme";
import CF7AppsAppSettingsHeader from "../components/CF7AppsAppSettingsHeader";
import CF7AppsAppSettingsTabs from "../components/CF7AppsAppSettingsTabs";
import CF7AppsFormToggle from "../components/CF7AppsFormToggle";

const SettingsPanelHeader = ({ appSettings }) => {
    const assetsBase = CF7Apps?.assetsURL || '';
    const theme = getAppCardTheme(appSettings, assetsBase);
    const docUrl = appSettings.documentation_url;
    const titleStyle = theme.titleColor ? { color: theme.titleColor } : undefined;
    const badgeIconMaskStyle = theme.badgeIconUrl
        ? {
            maskImage: `url(${theme.badgeIconUrl})`,
            WebkitMaskImage: `url(${theme.badgeIconUrl})`,
        }
        : undefined;
    const decorSrc = theme.decorationLayers?.[0];
    const settingsTitle =
        appSettings.id === 'acf-integration'
            ? appSettings.title
            : sprintf(__('%s Settings', 'cf7apps'), appSettings.title);

    return (
        <header className="cf7apps-settings-panel__header">
            {decorSrc && (
                <div className="cf7apps-settings-panel__decor" aria-hidden="true">
                    <img src={decorSrc} alt="" />
                </div>
            )}
            <div className="cf7apps-settings-panel__header-main">
                <div className="cf7apps-settings-panel__badges">
                    <span className="cf7apps-app-card__badge">
                        {theme.badgeIconUrl && (
                            <span
                                className="cf7apps-app-card__badge-icon"
                                style={{
                                    ...badgeIconMaskStyle,
                                    width: `${theme.badgeIconStyle?.width ??
                                        theme.badgeIconStyle?.maxWidth ??
                                        10
                                        }px`,
                                    height: `${theme.badgeIconStyle?.height ?? 10}px`,
                                }}
                                aria-hidden="true"
                            />
                        )}
                        <span className="cf7apps-app-card__badge-label">
                            {theme.label}
                        </span>
                    </span>
                    {theme.isBeta && theme.betaIconUrl && (
                        <span className="cf7apps-app-card__beta-tag">
                            <img
                                className="cf7apps-app-card__beta-tag-icon"
                                src={theme.betaIconUrl}
                                alt=""
                                width={10}
                                height={10}
                                decoding="async"
                            />
                            <span className="cf7apps-app-card__beta-tag-label">
                                {__('Beta', 'cf7apps')}
                            </span>
                        </span>
                    )}
                </div>
                <div className="cf7apps-settings-panel__title-row">
                    <Link
                        to="/"
                        className="cf7apps-settings-panel__back"
                        aria-label={__('Back to all apps', 'cf7apps')}
                    >
                        <KeyboardArrowLeft aria-hidden="true" />
                    </Link>
                    <h2
                        className="cf7apps-settings-panel__title"
                        style={titleStyle}
                    >
                        {settingsTitle}
                    </h2>
                </div>
                {appSettings.description && (
                    <p className="cf7apps-settings-panel__desc">
                        {appSettings.description}{' '}
                        {docUrl && (
                            <a
                                className="cf7apps-app-card__doc-link"
                                href={docUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {__('Documentation', 'cf7apps')}
                            </a>
                        )}
                    </p>
                )}
            </div>
        </header>
    );
};

const TextareaField = ({ fieldKey, field, className, description, disabled, openMap, setOpenMap, formData, handleInputChange, isAppShell = false }) => {
    const open = (openMap && openMap[fieldKey] !== undefined) ? openMap[fieldKey] : !field.collapsible;

    const toggleOpen = () => {
        setOpenMap(prev => ({
            ...prev,
            [fieldKey]: !prev[fieldKey]
        }));
    };

    // Chevron rendered via CSS background on .cf7apps-collapsible-chevron.
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
                    className="cf7apps-settings-collapsible-trigger"
                    style={isAppShell ? undefined : {
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '8px',
                        width: '100%',
                        maxWidth: '500px',
                        padding: '10px 12px',
                        borderRadius: '6px',
                        cursor: disabled ? 'not-allowed' : 'pointer',
                        opacity: disabled ? 0.6 : 1,
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
                        if (!disabled && field.collapsed_button_color) {
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
                    <div><label style={isAppShell ? undefined : { margin: 0, cursor: disabled ? 'not-allowed' : 'pointer', fontWeight: '500' }}>{field.title}</label></div>
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
                        style={isAppShell ? undefined : { width: '500px', minHeight: '120px', boxSizing: 'border-box', padding: '12px' }}
                    ></textarea>

                    {field.post_text && (
                        <div className="cf7apps-post-text" style={isAppShell ? undefined : { width: '500px', marginTop: '8px', color: '#444' }}>
                            {parse(String(field.post_text))}
                        </div>
                    )}

                    <CF7AppsHelpText description={description} />
                </div>
            )}
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
    const [openMap, setOpenMap] = useState({});

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
        if (explodedHash.length > 3) {
            setTabValue(explodedHash[3]);
        }

        async function fetchAppSettings() {
            if (app !== undefined) {
                setIsLoading(true);

                const appSettings = await getApps(app);

                if (!appSettings) {
                    setIsLoading(false);
                    return;
                }

                let hasTabs = Object.keys(appSettings.setting_tabs).length > 0 ? true : false;
                let _formData = {};

                setHasTabs(hasTabs);

                if (hasTabs) {
                    let settingsTabs = appSettings['setting_tabs'];
                    let settings = appSettings['admin_settings']['general'];

                    Object.keys(settingsTabs).map((tabKey, tabIndex) => {
                        Object.keys(settings['fields'][tabKey]).map((fieldKey, fieldIndex) => {
                            if (fieldKey !== 'template') {
                                if (settings['fields'][tabKey][fieldKey].type === 'text' || settings['fields'][tabKey][fieldKey].type === 'number' || 'radio' === settings['fields'][tabKey][fieldKey].type) {
                                    if (settings['fields'][tabKey][fieldKey].value !== undefined) {
                                        _formData[fieldKey] = settings['fields'][tabKey][fieldKey].value;
                                    } else {
                                        _formData[fieldKey] = settings['fields'][tabKey][fieldKey].default;
                                    }
                                }
                                else if (settings['fields'][tabKey][fieldKey].type === 'checkbox') {
                                    _formData[fieldKey] = settings['fields'][tabKey][fieldKey].checked;
                                } else if (settings['fields'][tabKey][fieldKey].type === 'textarea') {
                                    _formData[fieldKey] = settings['fields'][tabKey][fieldKey].value !== undefined ? settings['fields'][tabKey][fieldKey].value : (settings['fields'][tabKey][fieldKey].default ? settings['fields'][tabKey][fieldKey].default : '');
                                } else if (settings['fields'][tabKey][fieldKey].type === 'select') {
                                    const f = settings['fields'][tabKey][fieldKey];
                                    if (f.selected !== undefined && f.selected !== null && f.selected !== '') {
                                        _formData[fieldKey] = f.selected;
                                    } else {
                                        _formData[fieldKey] = f.default !== undefined ? f.default : '';
                                    }
                                }
                            }
                        });
                    });
                }
                else {
                    let settings = appSettings['admin_settings']['general'];

                    Object.keys(settings['fields']).map((fieldKey, fieldIndex) => {
                        if (fieldKey !== 'template') {
                            let field = settings['fields'][fieldKey];
                            if (field.type === 'text' || field.type === 'number' || 'radio' === field.type) {
                                if (field.value !== undefined && field.value !== null) {
                                    _formData[fieldKey] = field.value;
                                } else {
                                    _formData[fieldKey] = field.default !== undefined ? field.default : '';
                                }
                            } else if (field.type === 'checkbox') {
                                if (field.checked) {
                                    _formData[fieldKey] = field.checked;
                                } else {
                                    _formData[fieldKey] = field.default;
                                }
                            } else if (field.type === 'textarea') {
                                _formData[fieldKey] = field.value !== undefined ? field.value : (field.default ? field.default : '');
                            } else if (field.type === 'select') {
                                if (field.selected !== undefined && field.selected !== null && field.selected !== '') {
                                    _formData[fieldKey] = field.selected;
                                } else {
                                    _formData[fieldKey] = field.default !== undefined ? field.default : '';
                                }
                            }

                            if (settings['fields'][fieldKey].sub_fields) {
                                Object.keys(settings['fields'][fieldKey].sub_fields).map((subFieldKey) => {
                                    const subField = settings['fields'][fieldKey].sub_fields[subFieldKey];
                                    if (subField.type === 'text' || subField.type === 'number') {
                                        if (subField.value !== undefined && subField.value !== null) {
                                            _formData[subFieldKey] = subField.value;
                                        } else {
                                            _formData[subFieldKey] = subField.default !== undefined ? subField.default : '';
                                        }
                                    } else if (subField.type === 'checkbox') {
                                        if (subField.checked) {
                                            _formData[subFieldKey] = subField.checked;
                                        } else {
                                            _formData[subFieldKey] = subField.default;
                                        }
                                    } else if ('select' === subField.type) {
                                        if (subField.selected) {
                                            _formData[subFieldKey] = subField.selected;
                                        } else {
                                            _formData[subFieldKey] = subField.default;
                                        }
                                    }
                                });
                            }
                        }
                    });
                }
                if (app === 'webhook' && String(_formData.method || '').toUpperCase() === 'GET' && _formData.data_type === 'form') {
                    _formData.data_type = 'json';
                }
                setFormData(_formData);
                // initialize openMap for textarea fields across tabs
                let _openMap = {};
                if (hasTabs) {
                    Object.keys(appSettings.setting_tabs).map((tabKey) => {
                        const tabSettings = appSettings.admin_settings['general']['fields'][tabKey];
                        Object.keys(tabSettings).map((fieldKey) => {
                            const field = tabSettings[fieldKey];
                            if (field && field.type === 'textarea') {
                                _openMap[fieldKey] = field.collapsible
                                    ? (app === 'webhook' ? true : false)
                                    : true;
                            }
                        });
                    });
                } else {
                    const settings = appSettings.admin_settings['general']['fields'];
                    Object.keys(settings).map((fieldKey) => {
                        const field = settings[fieldKey];
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
        const { name, value } = e.target;
        setFormData((prev) => {
            const next = {
                ...prev,
                [name]: value
            };
            if (app === 'webhook' && name === 'method' && String(value).toUpperCase() === 'GET' && prev.data_type === 'form') {
                next.data_type = 'json';
            }
            return next;
        });
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
        const isEnabled = (formData.is_enabled === undefined || formData.is_enabled === false) ? false : true;

        // Check if ACF is required but not available when trying to enable
        if (isEnabled && appSettings.requires_acf && !appSettings.acf_available) {
            toast.error(__('This integration requires the Advanced Custom Fields plugin to be installed and active.', 'cf7apps'));
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
                            requiredMessage = field.required_message || __('Please fill all required fields.', 'cf7apps');
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
                        requiredMessage = field.required_message || __('Please fill all required fields.', 'cf7apps');
                        return true;
                    } else {

                        if (field && field.sub_fields) {


                            if (field.sub_fields[formData[fieldKey]] && field.sub_fields[formData[fieldKey]].required && (formData[formData[fieldKey]] === '' || formData[formData[fieldKey]] === undefined)) {
                                missingRequired = true;
                                requiredMessage = field.sub_fields[formData[fieldKey]].required_message || __('Please fill all required fields.', 'cf7apps');
                                return true;
                            }

                        }

                    }
                    return false;
                });
            }

            if (missingRequired) {
                setNotice({ show: true, text: requiredMessage });
                toast.error(__('Error! Please fill all required fields.', 'cf7apps'));
                setIsSaving(false);
                return;
            }
        }

        // Validate webhook URL format (only for webhook app)
        if (app === 'webhook' && isEnabled) {
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

        setNotice({ show: false, text: '' });
        setIsSaving(true);

        const response = await saveSettings(app, formData);

        if (response) {
            toast.success(__('Great! Settings Saved Successfully', 'cf7apps'));
        }
        else {
            toast.error(__('Error! Something Went Wrong', 'cf7apps'));
        }

        setIsSaving(false);
    }

    const handleTabChange = (e, newValue) => {
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
        const isRedirectionSettings = app === 'cf7-redirection';
        const isEntriesSettings = app === 'cf7-entries';
        const isWebhookSettings = app === 'webhook';
        const isAcfSettings = app === 'acf-integration';
        const isAiFormSettings = app === 'cf7-ai-form-generator';
        const isHoneypotSettings = app === 'honeypot';
        const isHcaptchaSettings = app === 'hcaptcha';
        const isAppSettingsShell = isRedirectionSettings || isEntriesSettings || isWebhookSettings || isAcfSettings || isAiFormSettings || isHoneypotSettings || isHcaptchaSettings;
        const formClassName = [
            'cf7apps-form',
            isAppSettingsShell ? 'cf7apps-app-settings-form' : '',
        ]
            .filter(Boolean)
            .join(' ');

        const renderSaveButton = (fieldKey, field) => {
            const saveIcon = CF7Apps?.assetsURL
                ? `${CF7Apps.assetsURL}/images/dashboard/save-icon.svg`
                : '';

            const saveButton = (
                <div className="cf7apps-form-group cf7apps-form-settings-save">
                    <Button
                        className="cf7apps-btn tertiary-primary"
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

            if (isWebhookSettings || isAcfSettings || isAiFormSettings || isHoneypotSettings || isHcaptchaSettings) {
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
                                setFormData({
                                    ...formData,
                                    [fieldKey]: nextValue,
                                });
                            }}
                        />
                    </div>
                );
            }

            return (
                <CF7AppsToggle
                    key={fieldKey}
                    name={fieldKey}
                    help={help}
                    label={field.title}
                    className={className}
                    isSelected={formData[fieldKey]}
                    description={parse(String(field.description))}
                    disabled={field.disabled}
                    onChange={(e) => {
                        if (fieldKey === 'is_enabled' &&
                            appSettings.requires_acf &&
                            !appSettings.acf_available &&
                            !formData[fieldKey]) {
                            setShowAcfNotice(false);
                            setTimeout(() => {
                                setShowAcfNotice(true);
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            }, 10);
                            setTimeout(() => setShowAcfNotice(false), 10000);
                            return;
                        }
                        setShowAcfNotice(false);
                        setFormData({
                            ...formData,
                            [fieldKey]: !formData[fieldKey]
                        });
                    }}
                />
            );
        };

        const handleAppSettingsTabChange = (newValue) => {
            setTabValue(newValue);
            const explodedHash = window.location.hash.split('/');
            explodedHash[3] = newValue;
            window.location.hash = explodedHash.join('/');
        };

        const renderTabField = (fieldKey, field, tabSettings) => {
            const className = field?.class === undefined ? '' : field.class;
            const help = field?.help;
            const palceholder = field?.placeholder === undefined ? '' : field.placeholder;

            if (fieldKey === 'title' || fieldKey === 'description') {
                return isAppSettingsShell ? null : (
                    fieldKey === 'title'
                        ? <h3 key={fieldKey}>{tabSettings.title}</h3>
                        : <p key={fieldKey} className="cf7apps-help-text">{tabSettings.description}</p>
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

            if (field?.type === 'notice') {
                return isAppSettingsShell ? null : (
                    <CF7AppsNotice
                        key={fieldKey}
                        type={className}
                        text={field.text}
                    />
                );
            }

            if (field?.type === 'text') {
                return (
                    <CF7AppsTextField
                        key={fieldKey}
                        label={field.title}
                        description={parse(String(field.description))}
                        className={className}
                        placeholder={palceholder}
                        value={formData[fieldKey]}
                        name={fieldKey}
                        onChange={handleInputChange}
                        required={field.required}
                        variant={isAppSettingsShell ? 'app' : undefined}
                    />
                );
            }

            if (field?.type === 'number') {
                return (
                    <CF7AppsNumberField
                        key={fieldKey}
                        label={field.title}
                        description={parse(String(field.description))}
                        className={className}
                        name={fieldKey}
                        placeholder={palceholder}
                        value={formData[fieldKey]}
                        onChange={handleInputChange}
                        min={field.min}
                        variant={isAppSettingsShell ? 'app' : undefined}
                    />
                );
            }

            if (field?.type === 'checkbox') {
                return renderCheckboxField(fieldKey, field, className, help || field.description);
            }

            if (field?.type === 'select') {
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

            if (field?.type === 'textarea') {
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
                        isAppShell={isAppSettingsShell}
                    />
                );
            }

            if (field?.type === 'save_button') {
                return renderSaveButton(fieldKey, field);
            }

            if (field?.type === 'radio') {
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

        if (hasTabs) {
            if (isEntriesSettings || isHoneypotSettings) {
                return (
                    <div className={formClassName}>
                        <CF7AppsAppSettingsTabs
                            tabs={appSettings.setting_tabs}
                            activeTab={tabValue}
                            onChange={handleAppSettingsTabChange}
                        />
                        {
                            Object.keys(appSettings.setting_tabs).map((tabKey, tabIndex) => {
                                if (`${tabIndex + 1}` !== tabValue) {
                                    return null;
                                }

                                const tabSettings = appSettings.admin_settings['general']['fields'][tabKey] || {};

                                return (
                                    <div
                                        key={tabKey}
                                        className={`MuiTabPanel-root cf7apps-app-settings-tabpanel cf7apps-app-settings-tabpanel-${tabKey}`}
                                    >
                                        {Object.keys(tabSettings).map((fieldKey) =>
                                            renderTabField(fieldKey, tabSettings[fieldKey], tabSettings)
                                        )}
                                    </div>
                                );
                            })
                        }
                    </div>
                );
            }

            // Tabs
            return (
                <div className="cf7apps-form">
                    <TabContext value={tabValue}>
                        <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                            <TabList onChange={handleTabChange} className="cf7apps-settings-tablist">
                                {
                                    Object.keys(appSettings.setting_tabs).map((tabKey, tabIndex) => {
                                        return (
                                            <Tab
                                                key={tabKey}
                                                label={appSettings.setting_tabs[tabKey]}
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
                                    <TabPanel key={tabKey} value={`${tabIndex + 1}`}>
                                        {
                                            Object.keys(tabSettings).map((fieldKey, fieldIndex) => {
                                                const field = tabSettings[fieldKey];
                                                const className = field.class === undefined ? '' : field.class;
                                                const help = field.help;
                                                const palceholder = field.placeholder === undefined ? '' : field.placeholder;

                                                if (field.type === 'notice') {
                                                    return (
                                                        <CF7AppsNotice
                                                            key={fieldKey}
                                                            type={className}
                                                            text={field.text}
                                                        />
                                                    )
                                                }
                                                else if (fieldKey === 'title') {
                                                    return (
                                                        <h3 key={fieldKey}>{tabSettings.title}</h3>
                                                    )
                                                }
                                                else if (fieldKey === 'description') {
                                                    return (
                                                        <p key={fieldKey} className="cf7apps-help-text">{tabSettings.description}</p>
                                                    )
                                                }
                                                else if (field.type === 'text') {
                                                    return (
                                                        <CF7AppsTextField
                                                            key={fieldKey}
                                                            label={field.title}
                                                            description={parse(String(field.description))}
                                                            className={className}
                                                            placeholder={palceholder}
                                                            value={formData[fieldKey]}
                                                            name={fieldKey}
                                                            onChange={handleInputChange}
                                                            required={field.required}
                                                        />
                                                    )
                                                }
                                                else if (field.type === 'number') {
                                                    return (
                                                        <CF7AppsNumberField
                                                            key={fieldKey}
                                                            label={field.title}
                                                            description={parse(String(field.description))}
                                                            className={className}
                                                            name={fieldKey}
                                                            placeholder={palceholder}
                                                            value={formData[fieldKey]}
                                                            onChange={handleInputChange}
                                                            min={field.min}
                                                        />
                                                    )
                                                }
                                                else if (field.type === 'checkbox') {
                                                    return (
                                                        <CF7AppsToggle
                                                            key={fieldKey}
                                                            name={fieldKey}
                                                            help={help}
                                                            label={field.title}
                                                            className={className}
                                                            isSelected={formData[fieldKey]}
                                                            description={parse(String(field.description))}
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
                                                                    setTimeout(() => setShowAcfNotice(false), 10000);
                                                                    return;
                                                                }
                                                                // Hide notice if enabling successfully
                                                                setShowAcfNotice(false);
                                                                setFormData({
                                                                    ...formData,
                                                                    [fieldKey]: !formData[fieldKey]
                                                                });
                                                            }}
                                                        />
                                                    )
                                                }
                                                else if (field.type === 'select') {
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
                                                        />
                                                    )
                                                }
                                                else if (field.type === 'textarea') {
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
                                                        />
                                                    )
                                                }
                                                else if (field.type === 'save_button') {
                                                    return (
                                                        <div key={fieldKey} className="cf7apps-form-group">
                                                            <Button
                                                                className="cf7apps-btn tertiary-primary"
                                                                onClick={saveAppSettings}
                                                                isBusy={isSaving}
                                                            >
                                                                {field.text}
                                                            </Button>
                                                        </div>
                                                    )
                                                }
                                                else if (fieldKey === 'template') {
                                                    const Template = CF7AppsTemplates[field];

                                                    // passing app settings to template for enable and disable the entries app.
                                                    return (
                                                        <Template
                                                            key={fieldKey}
                                                            appSettings={appSettings}
                                                            formData={formData}
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
            const generalFields = appSettings.admin_settings['general']['fields'];

            return (
                <div className={formClassName}>
                    <div className="MuiTabPanel-root">
                        {
                            notice.show && !isAppSettingsShell && (
                                <CF7AppsNotice
                                    type='warning'
                                    text={notice.text}
                                />
                            )
                        }
                        {
                            Object.keys(generalFields).map((fieldKey) => {
                                const field = generalFields[fieldKey];

                                if (isWebhookSettings && fieldKey === 'data_type') {
                                    return null;
                                }

                                if (isWebhookSettings && fieldKey === 'method') {
                                    return (
                                        <div key="webhook-method-row" className="cf7apps-webhook-method-row">
                                            {renderTabField('method', generalFields.method)}
                                            {renderTabField('data_type', generalFields.data_type)}
                                        </div>
                                    );
                                }

                                return renderTabField(fieldKey, field);
                            })
                        }
                    </div>
                </div>
            );
        }
    }

    const assetsBase = CF7Apps?.assetsURL || '';
    const isRedirectionSettings = app === 'cf7-redirection';
    const isEntriesSettings = app === 'cf7-entries';
    const isWebhookSettings = app === 'webhook';
    const isAcfSettings = app === 'acf-integration';
    const isAiFormSettings = app === 'cf7-ai-form-generator';
    const isHoneypotSettings = app === 'honeypot';
    const isHcaptchaSettings = app === 'hcaptcha';
    const isAppSettingsShell = isRedirectionSettings || isEntriesSettings || isWebhookSettings || isAcfSettings || isAiFormSettings || isHoneypotSettings || isHcaptchaSettings;
    const appSettingsPanelClass = isRedirectionSettings
        ? ' cf7apps-redirection-settings'
        : (isEntriesSettings
            ? ' cf7apps-entries-settings'
            : (isWebhookSettings
                ? ' cf7apps-webhook-settings'
                : (isAcfSettings
                    ? ' cf7apps-acf-settings'
                    : (isAiFormSettings
                        ? ' cf7apps-ai-form-settings'
                        : (isHoneypotSettings
                            ? ' cf7apps-honeypot-settings'
                            : (isHcaptchaSettings ? ' cf7apps-hcaptcha-settings' : ''))))));
    const panelStyle =
        appSettings && appSettings.id === app && !isAppSettingsShell
            ? getAppSettingsPanelStyle(appSettings, assetsBase)
            : undefined;

    return (
        !isLoading && appSettings && appSettings.id === app
            ?
            <div className="cf7apps-dashboard cf7apps-dashboard-settings">
                <div
                    className={`cf7apps-dashboard__panel cf7apps-settings-panel cf7apps-app-${appSettings.id}${appSettingsPanelClass}`}
                    style={panelStyle}
                >
                    {isAppSettingsShell ? (
                        <CF7AppsAppSettingsHeader appSettings={appSettings} />
                    ) : (
                        <SettingsPanelHeader appSettings={appSettings} />
                    )}
                    {isAcfSettings && appSettings.requires_acf && !appSettings.acf_available && (
                        <div className="cf7apps-acf-install-notice">
                            <CF7AppsNotice
                                type="warning"
                                text={sprintf(
                                    __('Advanced Custom Fields (ACF) is required for this integration. Install and activate the ACF plugin to continue. %s', 'cf7apps'),
                                    '<a class="cf7apps-acf-notice-link" href="' + (window.location.origin + '/wp-admin/plugin-install.php?s=advanced-custom-fields&tab=search&type=term') + '">' + __('Install ACF Plugin', 'cf7apps') + '</a>'
                                )}
                            />
                        </div>
                    )}
                    {showAcfNotice && appSettings.requires_acf && !appSettings.acf_available && !isAcfSettings && (
                        <div className="cf7apps-acf-install-notice">
                            <CF7AppsNotice
                                type="warning"
                                text={sprintf(
                                    __('Advanced Custom Fields (ACF) is required for this integration. Install and activate the ACF plugin to continue. %s', 'cf7apps'),
                                    '<a class="cf7apps-acf-notice-link" href="' + (window.location.origin + '/wp-admin/plugin-install.php?s=advanced-custom-fields&tab=search&type=term') + '">' + __('Install ACF Plugin', 'cf7apps') + '</a>'
                                )}
                            />
                        </div>
                    )}
                    <div className="cf7apps-app-setting-section">
                        {Settings()}
                    </div>
                </div>
            </div>
            :
            <div className="cf7apps-dashboard cf7apps-dashboard-settings">
                <div className="cf7apps-dashboard__panel cf7apps-settings-panel">
                    <CF7AppsSkeletonLoader height={420} width="100%" />
                </div>
            </div>
    );
}

export default AppSettings;
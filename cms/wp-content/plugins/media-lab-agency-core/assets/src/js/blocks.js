/**
 * Media Lab – Native Gutenberg Blocks
 *
 * Registriert CTA-Banner, Accordion/FAQ und Icon+Text als native Blocks.
 * Kein JSX – verwendet wp.element.createElement() direkt.
 *
 * Build: Vite → assets/dist/js/blocks.js
 *
 * @since 1.6.0
 */

const { registerBlockType } = wp.blocks;
const { __ }                 = wp.i18n;
const { el }                 = wp.element;
const {
    RichText,
    InspectorControls,
    BlockControls,
    AlignmentToolbar,
    URLInput,
    PanelColorSettings,
} = wp.blockEditor;
const {
    PanelBody,
    TextControl,
    SelectControl,
    ToggleControl,
    Button,
    Tooltip,
} = wp.components;

// =============================================================================
// CTA-Banner
// =============================================================================

registerBlockType( 'medialab/cta-banner', {
    edit( { attributes, setAttributes } ) {
        const { title, text, buttonText, buttonUrl, buttonStyle, bgColor, textAlign } = attributes;

        const bgMap = {
            primary:   'var(--color-primary, #ff0000)',
            dark:      'var(--color-dark, #1a1a1a)',
            light:     'var(--color-light, #f5f5f5)',
            white:     '#ffffff',
        };

        return el( 'div', null,
            el( InspectorControls, null,
                el( PanelBody, { title: __( 'Einstellungen', 'media-lab-agency-core' ), initialOpen: true },
                    el( SelectControl, {
                        label:    __( 'Hintergrundfarbe', 'media-lab-agency-core' ),
                        value:    bgColor,
                        options:  [
                            { label: 'Primärfarbe', value: 'primary' },
                            { label: 'Dunkel',      value: 'dark' },
                            { label: 'Hell',        value: 'light' },
                            { label: 'Weiß',        value: 'white' },
                        ],
                        onChange: val => setAttributes( { bgColor: val } ),
                    } ),
                    el( SelectControl, {
                        label:    __( 'Button-Stil', 'media-lab-agency-core' ),
                        value:    buttonStyle,
                        options:  [
                            { label: 'Primary', value: 'primary' },
                            { label: 'Outline', value: 'outline' },
                            { label: 'White',   value: 'white' },
                        ],
                        onChange: val => setAttributes( { buttonStyle: val } ),
                    } ),
                ),
            ),
            el( 'section', {
                    className: `ml-block-cta-banner ml-cta-banner--${bgColor} ml-cta-banner--${textAlign}`,
                    style: { backgroundColor: bgMap[ bgColor ] },
                },
                el( 'div', { className: 'ml-cta-banner__inner container' },
                    el( RichText, {
                        tagName:     'h2',
                        className:   'ml-cta-banner__title',
                        value:       title,
                        onChange:    val => setAttributes( { title: val } ),
                        placeholder: __( 'Titel eingeben…', 'media-lab-agency-core' ),
                    } ),
                    el( RichText, {
                        tagName:     'p',
                        className:   'ml-cta-banner__text',
                        value:       text,
                        onChange:    val => setAttributes( { text: val } ),
                        placeholder: __( 'Beschreibung (optional)…', 'media-lab-agency-core' ),
                    } ),
                    el( 'div', { className: 'ml-cta-banner__actions' },
                        el( RichText, {
                            tagName:     'span',
                            className:   `btn btn--${buttonStyle} ml-cta-banner__btn`,
                            value:       buttonText,
                            onChange:    val => setAttributes( { buttonText: val } ),
                            placeholder: __( 'Button-Text…', 'media-lab-agency-core' ),
                            allowedFormats: [],
                        } ),
                    ),
                    el( 'div', { className: 'ml-cta-banner__url-input' },
                        el( 'label', null, __( 'Button-URL:', 'media-lab-agency-core' ) ),
                        el( URLInput, {
                            value:    buttonUrl,
                            onChange: val => setAttributes( { buttonUrl: val } ),
                        } ),
                    ),
                ),
            ),
        );
    },

    save( { attributes } ) {
        const { title, text, buttonText, buttonUrl, buttonStyle, bgColor, textAlign } = attributes;
        return el( 'section', { className: `ml-block-cta-banner ml-cta-banner--${bgColor} ml-cta-banner--${textAlign}` },
            el( 'div', { className: 'ml-cta-banner__inner container' },
                el( RichText.Content, { tagName: 'h2', className: 'ml-cta-banner__title', value: title } ),
                text && el( RichText.Content, { tagName: 'p', className: 'ml-cta-banner__text', value: text } ),
                buttonText && buttonUrl && el( 'div', { className: 'ml-cta-banner__actions' },
                    el( 'a', { href: buttonUrl, className: `btn btn--${buttonStyle} ml-cta-banner__btn` },
                        el( RichText.Content, { value: buttonText } )
                    ),
                ),
            ),
        );
    },
} );

// =============================================================================
// Accordion / FAQ
// =============================================================================

registerBlockType( 'medialab/accordion', {
    edit( { attributes, setAttributes, clientId } ) {
        const { title, allowMultiple } = attributes;

        // Lokaler State für Items im Editor (gespeichert via save)
        return el( 'div', null,
            el( InspectorControls, null,
                el( PanelBody, { title: __( 'Einstellungen', 'media-lab-agency-core' ) },
                    el( ToggleControl, {
                        label:    __( 'Mehrere gleichzeitig öffnen', 'media-lab-agency-core' ),
                        checked:  allowMultiple,
                        onChange: val => setAttributes( { allowMultiple: val } ),
                    } ),
                ),
            ),
            el( 'div', { className: 'ml-block-accordion' },
                el( RichText, {
                    tagName:     'h2',
                    className:   'ml-accordion__title',
                    value:       title,
                    onChange:    val => setAttributes( { title: val } ),
                    placeholder: __( 'Accordion-Titel (optional)…', 'media-lab-agency-core' ),
                } ),
                el( 'p', { className: 'ml-accordion__editor-hint' },
                    __( '→ Accordion-Items über ACF-Felder in der Seitenleiste befüllen.', 'media-lab-agency-core' )
                ),
            ),
        );
    },

    save( { attributes } ) {
        const { title, allowMultiple } = attributes;
        return el( 'div', {
                className:           'ml-block-accordion',
                'data-allow-multiple': allowMultiple ? 'true' : 'false',
            },
            title && el( RichText.Content, { tagName: 'h2', className: 'ml-accordion__title', value: title } ),
            el( 'div', { className: 'ml-accordion__items' }, null ),
        );
    },
} );

// =============================================================================
// Icon + Text
// =============================================================================

registerBlockType( 'medialab/icon-text', {
    edit( { attributes, setAttributes } ) {
        const { icon, title, text, iconColor, layout } = attributes;

        const layoutOptions = [
            { label: __( 'Icon oben',  'media-lab-agency-core' ), value: 'top'   },
            { label: __( 'Icon links', 'media-lab-agency-core' ), value: 'left'  },
        ];

        return el( 'div', null,
            el( InspectorControls, null,
                el( PanelBody, { title: __( 'Icon-Einstellungen', 'media-lab-agency-core' ) },
                    el( TextControl, {
                        label:    __( 'Icon (Emoji oder SVG-Klasse)', 'media-lab-agency-core' ),
                        value:    icon,
                        onChange: val => setAttributes( { icon: val } ),
                        help:     __( 'Emoji: ⭐ 🚀 ✅  oder Dashicon: dashicons-star-filled', 'media-lab-agency-core' ),
                    } ),
                    el( TextControl, {
                        label:    __( 'Icon-Farbe (CSS-Wert)', 'media-lab-agency-core' ),
                        value:    iconColor,
                        onChange: val => setAttributes( { iconColor: val } ),
                        help:     __( 'z.B. #ff0000 oder var(--color-primary)', 'media-lab-agency-core' ),
                    } ),
                    el( SelectControl, {
                        label:    __( 'Layout', 'media-lab-agency-core' ),
                        value:    layout,
                        options:  layoutOptions,
                        onChange: val => setAttributes( { layout: val } ),
                    } ),
                ),
            ),
            el( 'div', { className: `ml-block-icon-text ml-icon-text--${layout}` },
                el( 'div', {
                        className: 'ml-icon-text__icon',
                        style: iconColor ? { color: iconColor } : {},
                    },
                    icon,
                ),
                el( 'div', { className: 'ml-icon-text__body' },
                    el( RichText, {
                        tagName:     'h3',
                        className:   'ml-icon-text__title',
                        value:       title,
                        onChange:    val => setAttributes( { title: val } ),
                        placeholder: __( 'Titel…', 'media-lab-agency-core' ),
                    } ),
                    el( RichText, {
                        tagName:     'p',
                        className:   'ml-icon-text__text',
                        value:       text,
                        onChange:    val => setAttributes( { text: val } ),
                        placeholder: __( 'Beschreibung…', 'media-lab-agency-core' ),
                    } ),
                ),
            ),
        );
    },

    save( { attributes } ) {
        const { icon, title, text, iconColor, layout } = attributes;
        return el( 'div', { className: `ml-block-icon-text ml-icon-text--${layout}` },
            el( 'div', {
                    className:   'ml-icon-text__icon',
                    style:       iconColor ? { color: iconColor } : {},
                    'aria-hidden': 'true',
                },
                icon,
            ),
            el( 'div', { className: 'ml-icon-text__body' },
                el( RichText.Content, { tagName: 'h3', className: 'ml-icon-text__title', value: title } ),
                el( RichText.Content, { tagName: 'p',  className: 'ml-icon-text__text',  value: text } ),
            ),
        );
    },
} );

/**
 * Media Lab – Native Gutenberg Blocks
 *
 * Registriert CTA-Banner, Accordion/FAQ und Icon+Text als native Blocks.
 * Kein JSX – verwendet wp.element.createElement() direkt.
 *
 * Build: Vite → assets/dist/js/blocks.js
 *
 * Fix: wp.domReady()-Wrapper stellt sicher, dass alle wp.*-Globals
 * initialisiert sind, bevor registerBlockType() aufgerufen wird.
 *
 * @since 1.6.0
 */

wp.domReady( function () {

    const { registerBlockType } = wp.blocks;
    const { __ }                = wp.i18n;
    const { createElement: el, Fragment } = wp.element; // wp.element exportiert kein "el" – Alias auf createElement
    const {
        RichText,
        InspectorControls,
        BlockControls,
        AlignmentToolbar,
        URLInput,
        PanelColorSettings,
        InnerBlocks,
        MediaUpload,
        MediaUploadCheck,
        useBlockProps,
    } = wp.blockEditor;
    const {
        PanelBody,
        TextControl,
        SelectControl,
        ToggleControl,
        Button,
        Tooltip,
        RangeControl,
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

            const blockProps = useBlockProps( {
                className: `ml-block-cta-banner ml-cta-banner--${bgColor} ml-cta-banner--${textAlign}`,
                style: { backgroundColor: bgMap[ bgColor ] },
            } );

            return el( Fragment, null,
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
                el( 'section', blockProps,
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
            const blockProps = useBlockProps.save( {
                className: `ml-block-cta-banner ml-cta-banner--${bgColor} ml-cta-banner--${textAlign}`,
            } );
            return el( 'section', blockProps,
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

            const blockProps = useBlockProps( { className: 'ml-block-accordion' } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Einstellungen', 'media-lab-agency-core' ) },
                        el( ToggleControl, {
                            label:    __( 'Mehrere gleichzeitig öffnen', 'media-lab-agency-core' ),
                            checked:  allowMultiple,
                            onChange: val => setAttributes( { allowMultiple: val } ),
                        } ),
                    ),
                ),
                el( 'div', blockProps,
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
            const blockProps = useBlockProps.save( {
                className: 'ml-block-accordion',
                'data-allow-multiple': allowMultiple ? 'true' : 'false',
            } );
            return el( 'div', blockProps,
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
                { label: __( 'Icon oben',  'media-lab-agency-core' ), value: 'top'  },
                { label: __( 'Icon links', 'media-lab-agency-core' ), value: 'left' },
            ];

            const blockProps = useBlockProps( { className: `ml-block-icon-text ml-icon-text--${layout}` } );

            return el( Fragment, null,
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
                el( 'div', blockProps,
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
            const blockProps = useBlockProps.save( { className: `ml-block-icon-text ml-icon-text--${layout}` } );
            return el( 'div', blockProps,
                el( 'div', {
                        className:    'ml-icon-text__icon',
                        style:        iconColor ? { color: iconColor } : {},
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

    // =============================================================================
    // Parallax-Sektion
    //
    // Migration von ACF-PHP-Block (seit 1.16.0): ACF rendert Feldgruppen immer
    // in der Inspector-Sidebar, unabhängig von mode/position – daher hier als
    // native Block mit echten InnerBlocks im Canvas.
    // =============================================================================

    registerBlockType( 'medialab/parallax', {
        edit( { attributes, setAttributes } ) {
            const {
                imageId, imageUrl, imageAlt,
                speed, overlayColor, overlayOpacity, minHeight,
                contentAlign, contentWidth,
            } = attributes;

            const onSelectImage = ( media ) => {
                setAttributes( {
                    imageId:  media.id,
                    imageUrl: ( media.sizes && media.sizes.large && media.sizes.large.url ) || media.url,
                    imageAlt: media.alt || '',
                } );
            };

            const onRemoveImage = () => setAttributes( { imageId: 0, imageUrl: '', imageAlt: '' } );

            const blockProps = useBlockProps( {
                className: `ml-block-parallax ml-parallax--align-${contentAlign} ml-parallax--width-${contentWidth}`,
                style: { minHeight: minHeight + 'px', position: 'relative' },
            } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Hintergrundbild', 'media-lab-agency-core' ), initialOpen: true },
                        el( MediaUploadCheck, null,
                            el( MediaUpload, {
                                onSelect:     onSelectImage,
                                allowedTypes: [ 'image' ],
                                value:        imageId,
                                render( { open } ) {
                                    return el( 'div', null,
                                        imageUrl && el( 'img', {
                                            src: imageUrl,
                                            alt: imageAlt,
                                            style: {
                                                width: '100%',
                                                height: 'auto',
                                                borderRadius: '2px',
                                                marginBottom: '8px',
                                                cursor: 'pointer',
                                            },
                                            onClick: open,
                                        } ),
                                        el( Button, { onClick: open, variant: 'secondary', style: { marginBottom: '8px' } },
                                            imageUrl
                                                ? __( 'Bild ändern', 'media-lab-agency-core' )
                                                : __( 'Bild auswählen', 'media-lab-agency-core' )
                                        ),
                                        imageUrl && el( Button, { onClick: onRemoveImage, variant: 'link', isDestructive: true, style: { marginLeft: '8px' } },
                                            __( 'Entfernen', 'media-lab-agency-core' )
                                        ),
                                    );
                                },
                            } ),
                        ),
                    ),
                    el( PanelBody, { title: __( 'Effekt', 'media-lab-agency-core' ) },
                        el( RangeControl, {
                            label:    __( 'Parallax-Intensität', 'media-lab-agency-core' ),
                            value:    speed, min: 0, max: 100, step: 5,
                            onChange: val => setAttributes( { speed: val } ),
                        } ),
                        el( RangeControl, {
                            label:    __( 'Mindesthöhe (px)', 'media-lab-agency-core' ),
                            value:    minHeight, min: 100, max: 1200, step: 50,
                            onChange: val => setAttributes( { minHeight: val } ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Overlay', 'media-lab-agency-core' ) },
                        el( PanelColorSettings, {
                            title:       __( 'Overlay-Farbe', 'media-lab-agency-core' ),
                            initialOpen: true,
                            colorSettings: [ {
                                value:    overlayColor,
                                onChange: val => setAttributes( { overlayColor: val || '#000000' } ),
                                label:    __( 'Farbe', 'media-lab-agency-core' ),
                            } ],
                        } ),
                        el( RangeControl, {
                            label:    __( 'Overlay-Deckkraft', 'media-lab-agency-core' ),
                            value:    overlayOpacity, min: 0, max: 100, step: 5,
                            onChange: val => setAttributes( { overlayOpacity: val } ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Inhalt', 'media-lab-agency-core' ) },
                        el( SelectControl, {
                            label:    __( 'Ausrichtung', 'media-lab-agency-core' ),
                            value:    contentAlign,
                            options: [
                                { label: __( 'Oben', 'media-lab-agency-core' ),  value: 'top' },
                                { label: __( 'Mitte', 'media-lab-agency-core' ), value: 'center' },
                                { label: __( 'Unten', 'media-lab-agency-core' ), value: 'bottom' },
                            ],
                            onChange: val => setAttributes( { contentAlign: val } ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Breite', 'media-lab-agency-core' ),
                            value:    contentWidth,
                            options: [
                                { label: __( 'Eng (640px)', 'media-lab-agency-core' ),    value: 'narrow' },
                                { label: __( 'Mittel (960px)', 'media-lab-agency-core' ), value: 'medium' },
                                { label: __( 'Voll', 'media-lab-agency-core' ),           value: 'full' },
                            ],
                            onChange: val => setAttributes( { contentWidth: val } ),
                        } ),
                    ),
                ),
                el( 'section', blockProps,
                    el( 'div', {
                        className: 'ml-parallax__bg',
                        'aria-hidden': 'true',
                        style: imageUrl ? { backgroundImage: `url(${imageUrl})` } : { background: '#f0f0f0' },
                    } ),
                    ! imageUrl && el( 'p', { style: { textAlign: 'center', padding: '2rem', color: '#aaa', position: 'relative' } },
                        __( 'Parallax-Sektion – bitte Hintergrundbild oben im Inspector wählen.', 'media-lab-agency-core' )
                    ),
                    overlayOpacity > 0 && el( 'div', {
                        className: 'ml-parallax__overlay',
                        'aria-hidden': 'true',
                        style: { backgroundColor: overlayColor, opacity: overlayOpacity / 100 },
                    } ),
                    el( 'div', { className: 'ml-parallax__content' },
                        el( 'div', { className: 'ml-parallax__inner' },
                            el( InnerBlocks, {
                                template: [ [ 'core/paragraph', { placeholder: __( 'Inhalt über dem Bild…', 'media-lab-agency-core' ) } ] ],
                                templateLock: false,
                            } ),
                        ),
                    ),
                ),
            );
        },

        save( { attributes } ) {
            const {
                imageUrl, speed, overlayColor, overlayOpacity, minHeight,
                contentAlign, contentWidth,
            } = attributes;

            const dataAttrs = { 'data-parallax-speed': speed };
            if ( imageUrl ) dataAttrs[ 'data-parallax-img' ] = imageUrl;

            const blockProps = useBlockProps.save( Object.assign( {
                className: `ml-block-parallax ml-parallax--align-${contentAlign} ml-parallax--width-${contentWidth}`,
                style: { minHeight: minHeight + 'px' },
                'aria-label': __( 'Parallax-Sektion', 'media-lab-agency-core' ),
            }, dataAttrs ) );

            return el( 'section', blockProps,
                el( 'div', {
                    className: 'ml-parallax__bg',
                    'aria-hidden': 'true',
                    style: imageUrl ? { backgroundImage: `url(${imageUrl})` } : undefined,
                } ),
                overlayOpacity > 0 && el( 'div', {
                    className: 'ml-parallax__overlay',
                    'aria-hidden': 'true',
                    style: { backgroundColor: overlayColor, opacity: overlayOpacity / 100 },
                } ),
                el( 'div', { className: 'ml-parallax__content' },
                    el( 'div', { className: 'ml-parallax__inner' },
                        el( InnerBlocks.Content ),
                    ),
                ),
            );
        },
    } );

    // =============================================================================
    // Slider (Parent) + Folie (Child)
    //
    // Migration von ACF-Repeater (seit 1.16.0): Folien sind jetzt echte
    // InnerBlocks (medialab/slide) statt Repeater-Zeilen in der Sidebar –
    // Hinzufügen/Sortieren/Löschen direkt im Canvas über die normale
    // Block-Toolbar. Erzeugte Markup ist 1:1 identisch zur bisherigen
    // PHP-Ausgabe, sodass das bestehende Frontend-JS (block-slider.js) und
    // CSS unverändert weiterlaufen.
    // =============================================================================

    registerBlockType( 'medialab/slider', {
        edit( { attributes, setAttributes } ) {
            const {
                autoplay, autoplayDelay, loop, navigation, pagination,
                effect, speed, slidesPerView, spaceBetween, centered,
            } = attributes;

            const blockProps = useBlockProps( { className: 'ml-block-slider ml-block-slider--editor' } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Wiedergabe', 'media-lab-agency-core' ), initialOpen: true },
                        el( ToggleControl, {
                            label:    __( 'Autoplay', 'media-lab-agency-core' ),
                            checked:  autoplay,
                            onChange: val => setAttributes( { autoplay: val } ),
                        } ),
                        autoplay && el( RangeControl, {
                            label:    __( 'Autoplay-Delay (ms)', 'media-lab-agency-core' ),
                            value:    autoplayDelay, min: 500, max: 10000, step: 500,
                            onChange: val => setAttributes( { autoplayDelay: val } ),
                        } ),
                        el( ToggleControl, {
                            label:    __( 'Endlos-Loop', 'media-lab-agency-core' ),
                            checked:  loop,
                            onChange: val => setAttributes( { loop: val } ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Übergangseffekt', 'media-lab-agency-core' ),
                            value:    effect,
                            options: [
                                { label: __( 'Schieben', 'media-lab-agency-core' ),     value: 'slide' },
                                { label: __( 'Überblenden', 'media-lab-agency-core' ),  value: 'fade' },
                                { label: __( 'Coverflow', 'media-lab-agency-core' ),    value: 'coverflow' },
                            ],
                            onChange: val => setAttributes( { effect: val } ),
                        } ),
                        el( RangeControl, {
                            label:    __( 'Transition (ms)', 'media-lab-agency-core' ),
                            value:    speed, min: 100, max: 2000, step: 100,
                            onChange: val => setAttributes( { speed: val } ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Layout', 'media-lab-agency-core' ) },
                        el( RangeControl, {
                            label:    __( 'Sichtbare Folien', 'media-lab-agency-core' ),
                            value:    slidesPerView, min: 1, max: 6, step: 1,
                            onChange: val => setAttributes( { slidesPerView: val } ),
                        } ),
                        el( RangeControl, {
                            label:    __( 'Abstand zwischen Folien (px)', 'media-lab-agency-core' ),
                            value:    spaceBetween, min: 0, max: 100, step: 4,
                            onChange: val => setAttributes( { spaceBetween: val } ),
                        } ),
                        el( ToggleControl, {
                            label:    __( 'Aktive Folie zentrieren', 'media-lab-agency-core' ),
                            checked:  centered,
                            onChange: val => setAttributes( { centered: val } ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Navigation', 'media-lab-agency-core' ) },
                        el( ToggleControl, {
                            label:    __( 'Pfeile anzeigen', 'media-lab-agency-core' ),
                            checked:  navigation,
                            onChange: val => setAttributes( { navigation: val } ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Seitenzahlen', 'media-lab-agency-core' ),
                            value:    pagination,
                            options: [
                                { label: __( 'Punkte', 'media-lab-agency-core' ), value: 'bullets' },
                                { label: __( 'Leiste', 'media-lab-agency-core' ), value: 'progressbar' },
                                { label: __( 'Aus', 'media-lab-agency-core' ),    value: 'none' },
                            ],
                            onChange: val => setAttributes( { pagination: val } ),
                        } ),
                    ),
                ),
                el( 'div', blockProps,
                    el( 'p', { className: 'ml-slider__editor-hint', style: { color: '#6b7280', fontSize: '.8125rem', margin: '0 0 8px' } },
                        __( 'Folien über den Block-Inserter (+) unten hinzufügen, per Drag & Drop sortieren. Die Swiper-Vorschau (Pfeile/Autoplay) erscheint im Frontend.', 'media-lab-agency-core' )
                    ),
                    el( 'div', { className: 'ml-slider__wrapper' },
                        el( InnerBlocks, {
                            allowedBlocks: [ 'medialab/slide' ],
                            template:      [ [ 'medialab/slide' ] ],
                            templateLock:  false,
                            orientation:   'horizontal',
                        } ),
                    ),
                ),
            );
        },

        save( { attributes } ) {
            const {
                autoplay, autoplayDelay, loop, navigation, pagination,
                effect, speed, slidesPerView, spaceBetween, centered,
            } = attributes;

            const swiperConfig = {
                loop, speed, effect,
                slidesPerView, spaceBetween,
                centeredSlides: centered,
                grabCursor: true,
                a11y: { enabled: true },
            };
            if ( autoplay ) {
                swiperConfig.autoplay = { delay: autoplayDelay, disableOnInteraction: false, pauseOnMouseEnter: true };
            }
            if ( navigation ) swiperConfig.navigation = true;
            if ( pagination !== 'none' ) {
                swiperConfig.pagination = { clickable: true, type: pagination === 'progressbar' ? 'progressbar' : 'bullets' };
            }

            const classes = [
                'ml-block-slider',
                `ml-slider--effect-${effect}`,
                navigation ? 'ml-slider--has-nav' : '',
                pagination !== 'none' ? 'ml-slider--has-pagination' : '',
            ].filter( Boolean ).join( ' ' );

            const blockProps = useBlockProps.save( { className: classes } );

            return el( 'div', blockProps,
                el( 'div', {
                        className: 'swiper ml-slider__swiper',
                        'data-swiper': JSON.stringify( swiperConfig ),
                    },
                    el( 'div', { className: 'swiper-wrapper ml-slider__wrapper' },
                        el( InnerBlocks.Content ),
                    ),
                    navigation && el( 'button', {
                        className: 'swiper-button-prev ml-slider__btn',
                        'aria-label': __( 'Vorherige Folie', 'media-lab-agency-core' ),
                    } ),
                    navigation && el( 'button', {
                        className: 'swiper-button-next ml-slider__btn',
                        'aria-label': __( 'Nächste Folie', 'media-lab-agency-core' ),
                    } ),
                    pagination !== 'none' && el( 'div', { className: 'swiper-pagination ml-slider__pagination' } ),
                ),
            );
        },
    } );

    registerBlockType( 'medialab/slide', {
        edit( { attributes, setAttributes } ) {
            const { imageId, imageUrl, imageAlt, heading, text, buttonText, buttonUrl, buttonTarget } = attributes;

            const onSelectImage = ( media ) => {
                setAttributes( {
                    imageId:  media.id,
                    imageUrl: ( media.sizes && media.sizes.large && media.sizes.large.url ) || media.url,
                    imageAlt: media.alt || '',
                } );
            };
            const onRemoveImage = () => setAttributes( { imageId: 0, imageUrl: '', imageAlt: '' } );
            const blockProps = useBlockProps( { className: 'ml-slider__slide ml-slider__slide--editor' } );

            return el( 'div', blockProps,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Folie', 'media-lab-agency-core' ), initialOpen: true },
                        el( TextControl, {
                            label:    __( 'Button-URL', 'media-lab-agency-core' ),
                            value:    buttonUrl,
                            onChange: val => setAttributes( { buttonUrl: val } ),
                            placeholder: 'https://',
                        } ),
                        el( ToggleControl, {
                            label:    __( 'Link in neuem Tab öffnen', 'media-lab-agency-core' ),
                            checked:  buttonTarget,
                            onChange: val => setAttributes( { buttonTarget: val } ),
                        } ),
                        el( TextControl, {
                            label:    __( 'CSS-Klasse (optional)', 'media-lab-agency-core' ),
                            value:    attributes.customClass,
                            onChange: val => setAttributes( { customClass: val } ),
                        } ),
                    ),
                ),
                el( 'div', { className: 'ml-slider__slide-media' },
                    el( MediaUploadCheck, null,
                        el( MediaUpload, {
                            onSelect:     onSelectImage,
                            allowedTypes: [ 'image' ],
                            value:        imageId,
                            render( { open } ) {
                                return imageUrl
                                    ? el( 'div', { style: { position: 'relative' } },
                                        el( 'img', {
                                            src: imageUrl, alt: imageAlt,
                                            className: 'ml-slider__slide-img',
                                            style: { cursor: 'pointer' },
                                            onClick: open,
                                        } ),
                                        el( Button, { onClick: onRemoveImage, variant: 'link', isDestructive: true },
                                            __( 'Bild entfernen', 'media-lab-agency-core' )
                                        ),
                                    )
                                    : el( Button, { onClick: open, variant: 'secondary' },
                                        __( 'Bild auswählen', 'media-lab-agency-core' )
                                    );
                            },
                        } ),
                    ),
                ),
                el( 'div', { className: 'ml-slider__slide-content' },
                    el( RichText, {
                        tagName:     'h3',
                        className:   'ml-slider__slide-heading',
                        value:       heading,
                        onChange:    val => setAttributes( { heading: val } ),
                        placeholder: __( 'Überschrift…', 'media-lab-agency-core' ),
                    } ),
                    el( RichText, {
                        tagName:     'div',
                        className:   'ml-slider__slide-text',
                        value:       text,
                        onChange:    val => setAttributes( { text: val } ),
                        placeholder: __( 'Text…', 'media-lab-agency-core' ),
                    } ),
                    el( RichText, {
                        tagName:        'span',
                        className:      'btn ml-slider__slide-btn',
                        value:          buttonText,
                        onChange:       val => setAttributes( { buttonText: val } ),
                        placeholder:    __( 'Button-Text…', 'media-lab-agency-core' ),
                        allowedFormats: [],
                    } ),
                ),
            );
        },

        save( { attributes } ) {
            const { imageUrl, imageAlt, heading, text, buttonText, buttonUrl, buttonTarget, customClass } = attributes;
            const hasContent = heading || text || buttonText;
            const classes = [ 'swiper-slide', 'ml-slider__slide', customClass ].filter( Boolean ).join( ' ' );
            const blockProps = useBlockProps.save( { className: classes } );

            return el( 'div', blockProps,
                imageUrl && el( 'div', { className: 'ml-slider__slide-media' },
                    el( 'img', {
                        src: imageUrl, alt: imageAlt,
                        className: 'ml-slider__slide-img',
                        loading: 'lazy',
                        draggable: 'false',
                    } ),
                ),
                hasContent && el( 'div', { className: 'ml-slider__slide-content' },
                    heading && el( RichText.Content, { tagName: 'h3', className: 'ml-slider__slide-heading', value: heading } ),
                    text && el( RichText.Content, { tagName: 'div', className: 'ml-slider__slide-text', value: text } ),
                    buttonText && buttonUrl && el( 'a', {
                            href: buttonUrl,
                            className: 'btn ml-slider__slide-btn',
                            target: buttonTarget ? '_blank' : '_self',
                            rel: buttonTarget ? 'noopener noreferrer' : undefined,
                        },
                        el( RichText.Content, { value: buttonText } ),
                    ),
                ),
            );
        },
    } );

} ); // end wp.domReady

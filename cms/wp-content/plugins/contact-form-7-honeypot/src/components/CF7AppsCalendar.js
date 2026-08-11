import { useState, useEffect, useRef } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import { DateRangePicker } from 'react-date-range';

const CF7AppsCalendar = ( { selection, onSelect, selectedDate, placeHolder } ) => {
    const [ active, setActive ] = useState( false );
    const [ popupStyle, setPopupStyle ] = useState( {} );
    const wrapperRef = useRef( null );

    const handleSelect = ( e ) => {
        onSelect( e );
    };

    useEffect( () => {
        if ( ! active || ! wrapperRef.current ) {
            return;
        }

        const updatePosition = () => {
            const rect = wrapperRef.current.getBoundingClientRect();
            const popupWidth = 680;
            const left = Math.min(
                Math.max( 8, rect.right - popupWidth ),
                window.innerWidth - popupWidth - 8
            );

            setPopupStyle( {
                position: 'fixed',
                top: `${ rect.bottom + 4 }px`,
                left: `${ left }px`,
                zIndex: 100000,
            } );
        };

        updatePosition();
        window.addEventListener( 'resize', updatePosition );
        window.addEventListener( 'scroll', updatePosition, true );

        return () => {
            window.removeEventListener( 'resize', updatePosition );
            window.removeEventListener( 'scroll', updatePosition, true );
        };
    }, [ active ] );

    useEffect( () => {
        const handleClickOutside = ( e ) => {
            if (
                active
                && ! e.target.closest( '.cf7apps-calendar-wrapper' )
                && ! e.target.closest( '.cf7apps-calendar-popup' )
            ) {
                setActive( false );
            }
        };

        document.addEventListener( 'click', handleClickOutside );

        return () => {
            document.removeEventListener( 'click', handleClickOutside );
        };
    }, [ active ] );

    const calendarPopup = active && typeof document !== 'undefined'
        ? createPortal(
            <div className="cf7apps-calendar-popup" style={ popupStyle }>
                <DateRangePicker
                    onChange={ handleSelect }
                    ranges={ selection }
                />
            </div>,
            document.body
        )
        : null;

    return (
        <div
            ref={ wrapperRef }
            className="cf7apps-calendar-wrapper"
            style={ { display: 'inline-block', position: 'relative' } }
        >
            <input
                type="text"
                onClick={ ( e ) => {
                    e.preventDefault();
                    e.stopPropagation();
                    setActive( ! active );
                } }
                readOnly
                className="cf7apps-form-input"
                value={ selectedDate }
                placeholder={ placeHolder }
            />
            { calendarPopup }
        </div>
    );
};

export default CF7AppsCalendar;

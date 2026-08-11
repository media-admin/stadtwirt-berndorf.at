import { createContext, useContext, useState, useCallback } from '@wordpress/element';

export const NAV_EXPANDED_WIDTH = 240;
export const NAV_COLLAPSED_WIDTH = 50;
const NAV_STORAGE_KEY = 'cf7apps_nav_pinned_expanded';

const CF7AppsNavContext = createContext( null );

export const CF7AppsNavProvider = ( { children } ) => {
	const [ isPinnedExpanded, setIsPinnedExpanded ] = useState( () => {
		try {
			const stored = window.localStorage.getItem( NAV_STORAGE_KEY );
			if ( stored === '0' ) {
				return false;
			}
		} catch ( e ) {
			// Ignore storage errors.
		}
		return true;
	} );
	const [ isHovering, setIsHovering ] = useState( false );

	const isPanelOpen = isPinnedExpanded || isHovering;
	const isHoverOverlay = ! isPinnedExpanded && isHovering;
	const shellWidth = isPinnedExpanded ? NAV_EXPANDED_WIDTH : NAV_COLLAPSED_WIDTH;

	const persistPinnedState = useCallback( ( expanded ) => {
		try {
			window.localStorage.setItem( NAV_STORAGE_KEY, expanded ? '1' : '0' );
		} catch ( e ) {
			// Ignore storage errors.
		}
	}, [] );

	const togglePinned = useCallback( () => {
		setIsPinnedExpanded( ( prev ) => {
			const next = ! prev;
			persistPinnedState( next );
			if ( next ) {
				setIsHovering( false );
			}
			return next;
		} );
	}, [ persistPinnedState ] );

	const startPanelHover = useCallback( () => {
		if ( ! isPinnedExpanded ) {
			setIsHovering( true );
		}
	}, [ isPinnedExpanded ] );

	const endPanelHover = useCallback( () => {
		setIsHovering( false );
	}, [] );

	const value = {
		isPinnedExpanded,
		isPanelOpen,
		isHoverOverlay,
		shellWidth,
		togglePinned,
		startPanelHover,
		endPanelHover,
	};

	return (
		<CF7AppsNavContext.Provider value={ value }>
			{ children }
		</CF7AppsNavContext.Provider>
	);
};

export const useCF7AppsNav = () => {
	const context = useContext( CF7AppsNavContext );
	if ( ! context ) {
		throw new Error( 'useCF7AppsNav must be used within CF7AppsNavProvider' );
	}
	return context;
};

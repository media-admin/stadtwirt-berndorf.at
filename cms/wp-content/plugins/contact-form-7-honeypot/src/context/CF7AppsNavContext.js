import { createContext, useCallback, useContext, useState } from '@wordpress/element';

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
	const [ isPanelHovering, setIsPanelHovering ] = useState( false );

	const isPanelOpen = isPinnedExpanded || isPanelHovering;
	const isHoverOverlay = ! isPinnedExpanded && isPanelHovering;
	const shellWidth = isPinnedExpanded ? NAV_EXPANDED_WIDTH : NAV_COLLAPSED_WIDTH;
	const panelClassNames = [
		'cf7apps-side-panel',
		isPanelOpen ? 'cf7apps-side-panel-open' : 'cf7apps-side-panel-collapsed',
		isHoverOverlay ? 'cf7apps-side-panel-hover-overlay' : '',
	]
		.filter( Boolean )
		.join( ' ' );

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
				setIsPanelHovering( false );
			}

			return next;
		} );
	}, [ persistPinnedState ] );

	const startPanelHover = useCallback( () => {
		if ( ! isPinnedExpanded ) {
			setIsPanelHovering( true );
		}
	}, [ isPinnedExpanded ] );

	const endPanelHover = useCallback( () => {
		setIsPanelHovering( false );
	}, [] );

	const value = {
		isPinnedExpanded,
		isPanelOpen,
		isPanelHovering,
		isHoverOverlay,
		shellWidth,
		panelClassNames,
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

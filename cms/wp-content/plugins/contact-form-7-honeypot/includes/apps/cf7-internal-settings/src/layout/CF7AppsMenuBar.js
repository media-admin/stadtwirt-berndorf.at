import { useEffect, useState } from '@wordpress/element';
import CF7AppsSkeletonLoader from '../components/CF7AppsSkeletonLoader';
import { NavLink, useLocation } from 'react-router';
import { getMenu } from '../api';
import { __ } from '@wordpress/i18n';
import { Tooltip } from '@wordpress/components';
import {
	getActiveParentMenu,
	isParentMenuActive,
	getParentMenuIconFile,
	getParentMenuLabel,
} from '../utils/menuParent';
import {
	useCF7AppsNav,
	NAV_EXPANDED_WIDTH,
	NAV_COLLAPSED_WIDTH,
} from '../context/CF7AppsNavContext';

const PanelToggleIcon = ( { isPanelOpen, isPinnedExpanded, assetsBase } ) => {
	if ( ! isPanelOpen ) {
		if ( assetsBase ) {
			return (
				<img
					className="cf7apps-form-menu__panel-toggle-icon cf7apps-form-menu__panel-toggle-icon--menu"
					src={ `${ assetsBase }menu-lines.svg` }
					alt=""
					width={ 18 }
					height={ 14 }
					aria-hidden="true"
				/>
			);
		}

		return (
			<svg
				className="cf7apps-form-menu__panel-toggle-icon cf7apps-form-menu__panel-toggle-icon--menu"
				width="18"
				height="14"
				viewBox="0 0 18 14"
				fill="none"
				aria-hidden="true"
			>
				<rect width="18" height="2" rx="1" fill="currentColor" />
				<rect y="6" width="18" height="2" rx="1" fill="currentColor" />
				<rect y="12" width="18" height="2" rx="1" fill="currentColor" />
			</svg>
		);
	}

	const iconClassName = isPinnedExpanded
		? 'cf7apps-form-menu__panel-toggle-icon'
		: 'cf7apps-form-menu__panel-toggle-icon is-expand';

	if ( assetsBase ) {
		return (
			<img
				className={ iconClassName }
				src={ `${ assetsBase }double-chevron.svg` }
				alt=""
				width={ 15 }
				height={ 12 }
				aria-hidden="true"
			/>
		);
	}

	return (
		<svg
			className={ iconClassName }
			width="15"
			height="12"
			viewBox="0 0 15 12.092"
			fill="none"
			aria-hidden="true"
		>
			<path
				fillRule="evenodd"
				clipRule="evenodd"
				d="M6.72054 0.279217C6.89933 0.458232 6.99975 0.700894 6.99975 0.953901C6.99975 1.20691 6.89933 1.44957 6.72054 1.62858L2.30327 6.04585L6.72054 10.4631C6.89947 10.6421 7 10.8847 7 11.1378C7 11.3909 6.89947 11.6336 6.72054 11.8125C6.5416 11.9914 6.29891 12.092 6.04585 12.092C5.7928 12.092 5.55011 11.9914 5.37117 11.8125L0.279217 6.72054C0.100425 6.54152 0 6.29886 0 6.04585C0 5.79285 0.100425 5.55018 0.279217 5.37117L5.37117 0.279217C5.55018 0.100426 5.79285 0 6.04585 0C6.29886 0 6.54152 0.100426 6.72054 0.279217Z"
				fill="currentColor"
			/>
			<path
				fillRule="evenodd"
				clipRule="evenodd"
				d="M6.72054 0.279217C6.89933 0.458232 6.99975 0.700894 6.99975 0.953901C6.99975 1.20691 6.89933 1.44957 6.72054 1.62858L2.30327 6.04585L6.72054 10.4631C6.89947 10.6421 7 10.8847 7 11.1378C7 11.3909 6.89947 11.6336 6.72054 11.8125C6.5416 11.9914 6.29891 12.092 6.04585 12.092C5.7928 12.092 5.55011 11.9914 5.37117 11.8125L0.279217 6.72054C0.100425 6.54152 0 6.29886 0 6.04585C0 5.79285 0.100425 5.55018 0.279217 5.37117L5.37117 0.279217C5.55018 0.100426 5.79285 0 6.04585 0C6.29886 0 6.54152 0.100426 6.72054 0.279217Z"
				fill="currentColor"
				transform="translate(8 0)"
			/>
		</svg>
	);
};

const MenuChevron = ( { expanded, assetsBase } ) => {
	if ( assetsBase ) {
		return (
			<img
				className={
					expanded
						? 'cf7apps-form-menu__chevron-img is-expanded'
						: 'cf7apps-form-menu__chevron-img'
				}
				src={ `${ assetsBase }chevron.svg` }
				alt=""
				width={ 7 }
				height={ 12 }
				aria-hidden="true"
			/>
		);
	}

	return (
		<svg
			className={ expanded ? 'cf7apps-form-menu__chevron is-expanded' : 'cf7apps-form-menu__chevron' }
			width="7"
			height="12"
			viewBox="0 0 7 12"
			fill="none"
			aria-hidden="true"
		>
			<path
				d="M1 1L6 6L1 11"
				stroke="currentColor"
				strokeWidth="1.5"
				strokeLinecap="round"
				strokeLinejoin="round"
			/>
		</svg>
	);
};

const getFirstRouteForParent = ( parentMenu, menuItems ) => {
	const routes = menuItems[ parentMenu ];

	if ( ! routes ) {
		return '/';
	}

	const firstKey = Object.keys( routes )[ 0 ];

	return firstKey ? `/settings/${ firstKey }` : '/';
};

const CF7AppsMenuBar = () => {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ menuItems, setMenuItems ] = useState( {} );
	const [ expandedParent, setExpandedParent ] = useState( null );
	const location = useLocation();
	const activeParentMenu = getActiveParentMenu( location, menuItems );
	const {
		isPinnedExpanded,
		isPanelOpen,
		isHoverOverlay,
		shellWidth,
		togglePinned,
		startPanelHover,
		endPanelHover,
	} = useCF7AppsNav();

	useEffect( () => {
		setIsLoading( true );

		getMenu().then( ( response ) => {
			if ( response ) {
				setMenuItems( response );
			}
			setIsLoading( false );
		} );
	}, [] );

	useEffect( () => {
		if ( activeParentMenu ) {
			setExpandedParent( activeParentMenu );
		}
	}, [ activeParentMenu ] );

	const formEditorAssets =
		CF7AppsInternalSettings?.formEditorAssetsURL ||
		`${ CF7AppsInternalSettings?.pluginURL || '' }includes/apps/cf7-internal-settings/assets/images/form-editor/`;

	const getParentIcon = ( menu, iconOnly = false ) => {
		const iconFile = getParentMenuIconFile( menu );
		const size = iconOnly ? 18 : 18;

		if ( ! iconFile || ! formEditorAssets ) {
			return null;
		}

		return (
			<img
				className="cf7apps-form-menu__icon"
				src={ `${ formEditorAssets }${ iconFile }` }
				alt=""
				width={ size }
				height={ size }
			/>
		);
	};

	const toggleParent = ( menu ) => {
		setExpandedParent( ( current ) => ( current === menu ? null : menu ) );
	};

	const panelClassNames = [
		'cf7apps-form-menu',
		isPanelOpen ? 'cf7apps-form-menu--open' : 'cf7apps-form-menu--icons',
		isHoverOverlay ? 'cf7apps-form-menu--hover-overlay' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	const panelWidth = isPanelOpen ? NAV_EXPANDED_WIDTH : NAV_COLLAPSED_WIDTH;
	const parentMenus = Object.keys( menuItems );

	const renderExpandedMenu = () => (
		<div className="cf7apps-form-menu__list">
			{ parentMenus.map( ( parentMenu ) => {
				const isActiveParent = isParentMenuActive(
					parentMenu,
					activeParentMenu
				);
				const isExpanded = expandedParent === parentMenu;

				return (
					<div
						key={ parentMenu }
						className="cf7apps-form-menu__group"
					>
						<button
							type="button"
							className={
								isActiveParent
									? 'cf7apps-form-menu__parent is-active'
									: 'cf7apps-form-menu__parent'
							}
							onClick={ () => toggleParent( parentMenu ) }
							aria-expanded={ isExpanded }
						>
							<span className="cf7apps-form-menu__parent-left">
								{ getParentIcon( parentMenu ) }
								<span className="cf7apps-form-menu__parent-label">
									{ getParentMenuLabel( parentMenu ) }
								</span>
							</span>
							<MenuChevron expanded={ isExpanded } assetsBase={ formEditorAssets } />
						</button>

						{ isExpanded && (
							<div className="cf7apps-form-menu__children">
								{ Object.entries(
									menuItems[ parentMenu ]
								).map( ( [ route, submenu ] ) => (
									<NavLink
										key={ route }
										to={ `/settings/${ route }` }
										className={ ( { isActive } ) =>
											isActive
												? 'cf7apps-form-menu__child is-active'
												: 'cf7apps-form-menu__child'
										}
									>
										<span
											className="cf7apps-form-menu__child-spacer"
											aria-hidden="true"
										/>
										{ submenu }
									</NavLink>
								) ) }
							</div>
						) }
					</div>
				);
			} ) }
		</div>
	);

	const renderCollapsedMenu = () => (
		<div className="cf7apps-form-menu__icon-only">
			{ parentMenus.map( ( parentMenu ) => {
				const isActiveParent = isParentMenuActive(
					parentMenu,
					activeParentMenu
				);
				const firstRoute = getFirstRouteForParent(
					parentMenu,
					menuItems
				);

				return (
					<Tooltip key={ parentMenu } text={ getParentMenuLabel( parentMenu ) }>
						<NavLink
							to={ firstRoute }
							className={
								isActiveParent
									? 'cf7apps-form-menu__icon-only-item is-active'
									: 'cf7apps-form-menu__icon-only-item'
							}
							aria-label={ getParentMenuLabel( parentMenu ) }
							onClick={ () => setExpandedParent( parentMenu ) }
						>
							{ getParentIcon( parentMenu, true ) }
						</NavLink>
					</Tooltip>
				);
			} ) }
		</div>
	);

	return (
		<aside
			className="cf7apps-form-settings-sidebar"
			style={ { width: `${ shellWidth }px` } }
			aria-label={ __( 'CF7 Apps form settings navigation', 'cf7apps' ) }
		>
			<div
				className="cf7apps-form-nav-shell"
				style={ { width: `${ shellWidth }px` } }
				onMouseEnter={ startPanelHover }
				onMouseLeave={ endPanelHover }
			>
				<nav
					className={ panelClassNames }
					style={ { width: `${ panelWidth }px` } }
				>
					<button
						type="button"
						className="cf7apps-form-menu__panel-toggle"
						onClick={ togglePinned }
						aria-expanded={ isPinnedExpanded }
						aria-label={
							isPinnedExpanded
								? __( 'Collapse sidebar', 'cf7apps' )
								: __( 'Expand sidebar', 'cf7apps' )
						}
					>
						{ isPanelOpen && (
							<span className="cf7apps-form-menu__panel-toggle-label">
								{ __( 'CF7 Apps', 'cf7apps' ) }
							</span>
						) }
						<PanelToggleIcon
							isPanelOpen={ isPanelOpen }
							isPinnedExpanded={ isPinnedExpanded }
							assetsBase={ formEditorAssets }
						/>
					</button>

					<div className="cf7apps-form-menu__body">
						{ isLoading ? (
							<div className="cf7apps-nav-loading">
								<CF7AppsSkeletonLoader
									count={ 1 }
									height={ 40 }
									width={ isPanelOpen ? 205 : 30 }
								/>
								<br />
								<CF7AppsSkeletonLoader count={ 3 } height={ 20 } />
							</div>
						) : isPanelOpen ? (
							renderExpandedMenu()
						) : (
							renderCollapsedMenu()
						) }
					</div>
				</nav>
			</div>
		</aside>
	);
};

export default CF7AppsMenuBar;

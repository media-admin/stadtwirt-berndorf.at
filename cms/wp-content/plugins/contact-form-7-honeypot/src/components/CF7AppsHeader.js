import { useEffect, useState } from "@wordpress/element";
import CF7AppsSkeletonLoader from "./CF7AppsSkeletonLoader";
import { Link, useLocation, useNavigate } from "react-router";
import { Tooltip } from "@wordpress/components";
import { ChevronLeft, MenuIcon } from "@mui/icons-material";
import { __ } from "@wordpress/i18n";
import { useCF7AppsNav } from "../context/CF7AppsNavContext";

const CF7AppsHeader = ( { showNavToggle = true } ) => {
	const path = useLocation();
	const navigate = useNavigate();
	const { isPinnedExpanded, togglePinned } = useCF7AppsNav();
	const [isLoading, setIsLoading] = useState(true);

	useEffect(() => {
		const timer = setTimeout(() => {
			setIsLoading(false);
		}, 500);
		
		return () => clearTimeout(timer);
	}, []);

	const handleBackClick = () => {
		navigate(-1);
	};

	const isSettingsPage = path.pathname.startsWith( '/settings' );
	const showHeaderBack =
		path.pathname !== undefined &&
		path.pathname !== '/' &&
		! isSettingsPage;

	const assetsBase = CF7Apps && CF7Apps.assetsURL ? CF7Apps.assetsURL : "";
	const toggleLabel = isPinnedExpanded
		? __( "Collapse sidebar", "cf7apps" )
		: __( "Expand sidebar", "cf7apps" );

	return (
		<div className="cf7apps-header-wrap">
			{ isLoading ? (
				<div>
					<CF7AppsSkeletonLoader count={ 1 } height={ 48 } />
				</div>
			) : (
				<div className="cf7apps-header">
					<div className="cf7apps-header-inner">
						<div className="cf7apps-header-left">
							{ showNavToggle && (
								<button
									type="button"
									className="cf7apps-nav-toggle cf7apps-expand-menu-btn"
									onClick={ ( e ) => {
										e.preventDefault();
										togglePinned();
									} }
									aria-label={ toggleLabel }
									aria-expanded={ isPinnedExpanded }
								>
									{ isPinnedExpanded ? <ChevronLeft /> : <MenuIcon /> }
								</button>
							) }
							<Link to="/" className="cf7apps-header-logo" title={ __( "CF7 Apps", "cf7apps" ) }>
								{ assetsBase ? (
									<img
										src={ `${ assetsBase }/images/logo.png` }
										alt={ __( "CF7 Apps", "cf7apps" ) }
										className="cf7apps-header-logo-img"
										height={ 36 }
										decoding="async"
									/>
								) : (
									<span className="cf7apps-nav-logo-text">CF7 Apps</span>
								) }
							</Link>
						</div>

						<div className="cf7apps-header-right">
							<Tooltip text={ __( "View documentation", "cf7apps" ) } position="bottom">
								<button
									type="button"
									className="cf7apps-header-icon-button cf7apps-header-doc-button"
									aria-label={ __( "View documentation", "cf7apps" ) }
									onClick={ () =>
										window.open(
											"https://cf7apps.com/docs/?utm_source=plugin&utm_medium=header&utm_campaign=documentation",
											"_blank"
										)
									}
								>
									<img
										src={ `${ assetsBase }/images/book.svg` }
										alt={ __( "Documentation", "cf7apps" ) }
										className="cf7apps-header-icon-img cf7apps-header-doc-icon"
										width={ 20 }
										height={ 17 }
										decoding="async"
									/>
								</button>
							</Tooltip>

							<Tooltip text={ __( "Share your idea with us", "cf7apps" ) } position="bottom">
								<button
									type="button"
									className="cf7apps-header-icon-button"
									aria-label={ __( "Share your idea with us", "cf7apps" ) }
									onClick={ () =>
										window.open(
											"https://cf7apps.com/submit-idea/?utm_source=plugin&utm_medium=header&utm_campaign=idea",
											"_blank"
										)
									}
								>
									<img
										src={ `${ assetsBase }/images/lamp-charge.png` }
										alt={ __( "Share idea", "cf7apps" ) }
										className="cf7apps-header-icon-img"
									/>
								</button>
							</Tooltip>

							{ CF7Apps?.pluginVersion && (
								<span className="cf7apps-header-version">
									V { CF7Apps.pluginVersion }
								</span>
							) }

							{ showHeaderBack && (
								<button
									type="button"
									onClick={ handleBackClick }
									className="cf7apps-btn icon tertiary-secondary cf7apps-header-back-btn"
									aria-label={ __( "Go back", "cf7apps" ) }
								>
									<ChevronLeft />
									{ __( "Back", "cf7apps" ) }
								</button>
							) }
						</div>
					</div>
				</div>
			) }
		</div>
	);
};

export default CF7AppsHeader;

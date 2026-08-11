import { __ } from "@wordpress/i18n";
import { useEffect, useState } from "@wordpress/element";
import CF7AppsSkeletonLoader from "../components/CF7AppsSkeletonLoader";
import { getMenu } from "../api/api";
import { Accordion, AccordionDetails, AccordionSummary, Typography, Tooltip } from "@mui/material";
import { ExpandMore } from "@mui/icons-material";
import { NavLink } from "react-router";
import {
	useCF7AppsNav,
	NAV_EXPANDED_WIDTH,
	NAV_COLLAPSED_WIDTH,
} from "../context/CF7AppsNavContext";
import {
	getActiveParentMenu,
	getParentNavPath,
	getParentMenuIcon,
	getParentHeadingClassName,
	getParentMenuLabel,
	isParentMenuActive,
	isPaymentParentMenu,
} from "../utils/menuParent";

const MenuBar = () => {
	const {
		isPanelOpen,
		isPanelHovering,
		startPanelHover,
		endPanelHover,
		shellWidth,
		panelClassNames,
	} = useCF7AppsNav();
	const [isLoading, setIsLoading] = useState(true);
	const [menuItems, setMenuItems] = useState({});
	const activeParentMenu = getActiveParentMenu();

	useEffect(() => {
		async function fetchMenu() {
			const response = await getMenu();
			if (response) {
				setMenuItems(response);
				setIsLoading(false);
			}
		}

		fetchMenu();
	}, []);

	const panelWidth = isPanelOpen ? NAV_EXPANDED_WIDTH : NAV_COLLAPSED_WIDTH;
	const visibleParentMenus = Object.keys(menuItems).filter(
		(parentMenu) => !isPaymentParentMenu(parentMenu)
	);

	return (
		<div
			className="cf7apps-nav-shell"
			style={{ width: `${shellWidth}px` }}
			onMouseEnter={startPanelHover}
			onMouseLeave={endPanelHover}
		>
			<nav
				className={panelClassNames}
				style={{ width: `${panelWidth}px` }}
				aria-label={__("CF7 Apps navigation", "cf7apps")}
			>
				<div className="cf7apps-nav-panel-body">
					{isLoading ? (
						<div className="cf7apps-nav-loading">
							<CF7AppsSkeletonLoader
								count={1}
								height={40}
								width={isPanelOpen ? 205 : 40}
							/>
							<br />
							<CF7AppsSkeletonLoader count={3} height={20} />
						</div>
					) : !isPanelOpen ? (
						<div className="cf7apps-menu-icon-only">
							{visibleParentMenus.map((parentMenu) => {
								const isActive = isParentMenuActive(
									parentMenu,
									activeParentMenu
								);

								return (
									<Tooltip key={parentMenu} title={parentMenu}>
										<NavLink
											to={getParentNavPath(parentMenu)}
											className={
												isActive
													? "cf7apps-menu-icon-only-item is-active"
													: "cf7apps-menu-icon-only-item"
											}
											aria-label={parentMenu}
										>
											{getParentMenuIcon(parentMenu, true)}
										</NavLink>
									</Tooltip>
								);
							})}
						</div>
					) : (
						<div className="cf7apps-menu-container">
							{visibleParentMenus.map((parentMenu, parentIndex) => (
								<Accordion
									key={parentIndex}
									defaultExpanded
									className="cf7apps-menu-accordion"
									disableGutters
								>
									<AccordionSummary
										expandIcon={<ExpandMore />}
										className="cf7apps-menu-accordion-summary"
									>
										<Typography
											component="span"
											className={getParentHeadingClassName(
												parentMenu
											)}
										>
											{getParentMenuLabel(parentMenu)}
										</Typography>
									</AccordionSummary>
									<AccordionDetails className="cf7apps-menu-routes-container">
										{Object.entries(menuItems[parentMenu]).map(
											([route, subMenu], subMenuIndex) => (
												<div
													className="cf7apps-menu-route"
													key={subMenuIndex}
												>
													<NavLink
														to={`/settings/${route}`}
													>
														{subMenu}
													</NavLink>
												</div>
											)
										)}
									</AccordionDetails>
								</Accordion>
							))}
						</div>
					)}
				</div>
			</nav>
		</div>
	);
};

export default MenuBar;

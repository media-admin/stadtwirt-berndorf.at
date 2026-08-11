/**
 * Post Navigation – Gutenberg Toolbar-Icon + Sidebar + Dokument-Panel
 *
 * Ergänzt den klassischen Prev/Next-Button-Bereich (server-seitig via
 * 'edit_form_top' gerendert – funktioniert NUR im Classic Editor, da dieser
 * Hook zum Classic-Editor-Template gehört und im Block Editor nicht mit
 * rendert wird) um zwei parallele Einstiegspunkte im Block Editor:
 *
 *   1. PluginSidebar – eigenes, dauerhaft sichtbares Icon in der oberen
 *      Editor-Toolbar (+ Menüeintrag im "⋮"-More-Menu).
 *   2. PluginDocumentSettingPanel – zusätzliches Panel im "Dokument"-Tab
 *      der Standard-Seitenleiste (neben Kategorien, Schlagwörtern etc.).
 *
 * Beide SlotFills sind unabhängig voneinander und können parallel
 * registriert werden – kein Konflikt.
 *
 * Daten kommen fertig aufgelöst per wp_localize_script
 * (window.medialabPostNav), keine eigene REST-Abfrage nötig.
 *
 * @since 1.17.0
 * @since 1.17.2 PluginSidebar (Toolbar-Icon) ergänzt.
 * @since 1.17.3 PluginDocumentSettingPanel wieder zusätzlich ergänzt –
 *               beide Einstiegspunkte laufen jetzt parallel.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element || ! wp.components ) return;

	var registerPlugin              = wp.plugins.registerPlugin;
	var PluginSidebar               = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem   = wp.editPost.PluginSidebarMoreMenuItem;
	var PluginDocumentSettingPanel  = wp.editPost.PluginDocumentSettingPanel;
	var el                          = wp.element.createElement;
	var Fragment                    = wp.element.Fragment;
	var Button                      = wp.components.Button;

	var data = window.medialabPostNav || {};
	if ( ! data.prev && ! data.next ) return;

	var SIDEBAR_NAME = 'medialab-post-navigation-sidebar';

	function PostNavContent() {
		var children = [];

		children.push(
			el(
				'div',
				{ className: 'medialab-post-nav-panel__buttons', key: 'buttons' },
				data.prev
					? el( Button, { variant: 'secondary', href: data.prev.url, key: 'prev' }, '\u2190 ' + data.prev.title )
					: el( Button, { variant: 'secondary', disabled: true, key: 'prev' }, data.i18n.prev ),
				data.next
					? el( Button, { variant: 'secondary', href: data.next.url, key: 'next' }, data.next.title + ' \u2192' )
					: el( Button, { variant: 'secondary', disabled: true, key: 'next' }, data.i18n.next )
			)
		);

		if ( data.position ) {
			children.push(
				el( 'p', { className: 'medialab-post-nav-panel__position', key: 'position' }, data.position )
			);
		}

		return children;
	}

	function PostNavPlugins() {
		return el(
			Fragment,
			null,
			// ── Einstiegspunkt 1: Toolbar-Icon + eigene Sidebar ─────────────
			el(
				PluginSidebarMoreMenuItem,
				{ target: SIDEBAR_NAME, icon: 'sort', key: 'more-menu-item' },
				data.i18n.title
			),
			el(
				PluginSidebar,
				{ name: SIDEBAR_NAME, title: data.i18n.title, icon: 'sort', className: 'medialab-post-nav-panel', key: 'sidebar' },
				PostNavContent()
			),
			// ── Einstiegspunkt 2: Panel im "Dokument"-Tab ───────────────────
			el(
				PluginDocumentSettingPanel,
				{ name: 'medialab-post-navigation', title: data.i18n.title, className: 'medialab-post-nav-panel', key: 'document-panel' },
				PostNavContent()
			)
		);
	}

	registerPlugin( 'medialab-post-navigation', { render: PostNavPlugins, icon: 'sort' } );

}( window.wp ) );
